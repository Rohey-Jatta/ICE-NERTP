<?php

namespace App\Http\Controllers;

use App\Exports\ConstituencyReportExport;
use App\Models\AdministrativeHierarchy;
use App\Models\Election;
use App\Models\PollingStation;
use App\Models\Result;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Constituency report exports (PDF + Excel) for constituency approvers.
 *
 * All datasets are scoped to the authenticated approver's constituency and the
 * current election so historical elections never leak into the report.
 */
class ReportController extends Controller
{
    public const REPORTS = [
        'full'          => 'Full Constituency Results',
        'ward-summary'  => 'Ward Summary Report',
        'turnout'       => 'Turnout Analysis',
        'certification' => 'Certification Status Report',
    ];

    private const CERTIFIED_STATUSES = [
        Result::STATUS_CONSTITUENCY_CERTIFIED,
        Result::STATUS_PENDING_ADMIN_AREA,
        Result::STATUS_ADMIN_AREA_CERTIFIED,
        Result::STATUS_PENDING_NATIONAL,
        Result::STATUS_NATIONALLY_CERTIFIED,
    ];

    public function export(Request $request, string $report, string $format)
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404, 'Unknown report type.');
        abort_unless(in_array($format, ['pdf', 'excel']), 404, 'Unknown export format.');

        $user = $request->user();

        $constituency = AdministrativeHierarchy::where('assigned_approver_id', $user->id)
            ->where('level', 'constituency')
            ->first();
        abort_unless($constituency, 403, 'No constituency is assigned to your account.');

        $election = Election::current();
        abort_unless($election, 404, 'No current election found.');

        [$headings, $rows, $summary] = $this->buildDataset($report, $constituency, $election);

        $title    = self::REPORTS[$report];
        $filename = Str::slug($constituency->name . ' ' . $title . ' ' . now()->format('Y-m-d'));

        if ($format === 'excel') {
            return Excel::download(
                new ConstituencyReportExport($headings, $rows, $title),
                "{$filename}.xlsx"
            );
        }

        $pdf = Pdf::loadView('reports.constituency-report', [
            'title'        => $title,
            'constituency' => $constituency->name,
            'election'     => $election->name,
            'generatedAt'  => now()->format('Y-m-d H:i'),
            'headings'     => $headings,
            'rows'         => $rows,
            'summary'      => $summary,
        ])->setPaper('a4', in_array($report, ['full', 'certification']) ? 'landscape' : 'portrait');

        return $pdf->download("{$filename}.pdf");
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array>, 2: array<string, string>}
     */
    private function buildDataset(string $report, AdministrativeHierarchy $constituency, Election $election): array
    {
        $wards = AdministrativeHierarchy::where('parent_id', $constituency->id)
            ->where('level', 'ward')
            ->orderBy('name')
            ->get();

        $stations = PollingStation::whereIn('ward_id', $wards->pluck('id'))
            ->orderBy('code')
            ->get();

        // Latest result per polling station for the current election only —
        // preferring a nationally certified row, then most recent submission.
        $latestResults = Result::where('election_id', $election->id)
            ->whereIn('polling_station_id', $stations->pluck('id'))
            ->with('candidateVotes.candidate.politicalParty')
            ->get()
            ->groupBy('polling_station_id')
            ->map(fn (Collection $group) => $group
                ->sortByDesc(fn (Result $r) => [
                    $r->certification_status === Result::STATUS_NATIONALLY_CERTIFIED ? 1 : 0,
                    $r->submitted_at?->timestamp ?? 0,
                    $r->id,
                ])
                ->first());

        $wardNames = $wards->pluck('name', 'id');

        return match ($report) {
            'full'          => $this->fullReport($stations, $latestResults, $wardNames),
            'ward-summary'  => $this->wardSummaryReport($wards, $stations, $latestResults),
            'turnout'       => $this->turnoutReport($stations, $latestResults, $wardNames),
            'certification' => $this->certificationReport($stations, $latestResults, $wardNames),
        };
    }

    private function fullReport(Collection $stations, Collection $latestResults, Collection $wardNames): array
    {
        $headings = ['Ward', 'Station Code', 'Polling Station', 'Registered', 'Votes Cast', 'Valid', 'Rejected', 'Turnout %', 'Leading Candidate', 'Status'];

        $rows = $stations->map(function (PollingStation $station) use ($latestResults, $wardNames) {
            $result = $latestResults->get($station->id);
            $leader = $result?->candidateVotes->sortByDesc('votes')->first();

            return [
                $wardNames->get($station->ward_id, '—'),
                $station->code,
                $station->name,
                $station->registered_voters,
                $result?->total_votes_cast ?? '—',
                $result?->valid_votes ?? '—',
                $result?->rejected_votes ?? '—',
                $result ? $result->getTurnoutPercentage() : '—',
                $leader
                    ? sprintf('%s (%s) — %s votes',
                        $leader->candidate->name ?? 'Unknown',
                        $leader->candidate->politicalParty->abbreviation ?? 'IND',
                        number_format($leader->votes))
                    : 'Not reported',
                Result::PUBLIC_STATUS_LABELS[$result?->certification_status ?? Result::STATUS_NOT_REPORTED] ?? '—',
            ];
        })->values()->all();

        return [$headings, $rows, $this->summaryFor($stations, $latestResults)];
    }

    private function wardSummaryReport(Collection $wards, Collection $stations, Collection $latestResults): array
    {
        $headings = ['Ward', 'Stations', 'Reported', 'Certified', 'Registered', 'Votes Cast', 'Valid', 'Rejected', 'Turnout %'];

        $rows = $wards->map(function (AdministrativeHierarchy $ward) use ($stations, $latestResults) {
            $wardStations = $stations->where('ward_id', $ward->id);
            $results      = $wardStations->map(fn ($s) => $latestResults->get($s->id))->filter();

            $registered = (int) $wardStations->sum('registered_voters');
            $cast       = (int) $results->sum('total_votes_cast');

            return [
                $ward->name,
                $wardStations->count(),
                $results->count(),
                $results->whereIn('certification_status', self::CERTIFIED_STATUSES)->count(),
                $registered,
                $cast,
                (int) $results->sum('valid_votes'),
                (int) $results->sum('rejected_votes'),
                $registered > 0 ? round($cast / $registered * 100, 2) : 0,
            ];
        })->values()->all();

        return [$headings, $rows, $this->summaryFor($stations, $latestResults)];
    }

    private function turnoutReport(Collection $stations, Collection $latestResults, Collection $wardNames): array
    {
        $headings = ['Ward', 'Station Code', 'Polling Station', 'Registered', 'Votes Cast', 'Turnout %'];

        $rows = $stations
            ->map(function (PollingStation $station) use ($latestResults, $wardNames) {
                $result = $latestResults->get($station->id);

                return [
                    $wardNames->get($station->ward_id, '—'),
                    $station->code,
                    $station->name,
                    $station->registered_voters,
                    $result?->total_votes_cast ?? 0,
                    $result ? $result->getTurnoutPercentage() : 0,
                ];
            })
            ->sortByDesc(fn ($row) => $row[5])
            ->values()
            ->all();

        return [$headings, $rows, $this->summaryFor($stations, $latestResults)];
    }

    private function certificationReport(Collection $stations, Collection $latestResults, Collection $wardNames): array
    {
        $headings = ['Ward', 'Station Code', 'Polling Station', 'Status', 'Rejections', 'Last Rejection Reason', 'Submitted At'];

        $rows = $stations->map(function (PollingStation $station) use ($latestResults, $wardNames) {
            $result = $latestResults->get($station->id);

            return [
                $wardNames->get($station->ward_id, '—'),
                $station->code,
                $station->name,
                Result::PUBLIC_STATUS_LABELS[$result?->certification_status ?? Result::STATUS_NOT_REPORTED] ?? '—',
                $result?->rejection_count ?? 0,
                $result?->last_rejection_reason ?? '—',
                $result?->submitted_at?->format('Y-m-d H:i') ?? '—',
            ];
        })->values()->all();

        return [$headings, $rows, $this->summaryFor($stations, $latestResults)];
    }

    /**
     * Headline figures shown on the PDF cover block.
     *
     * @return array<string, string>
     */
    private function summaryFor(Collection $stations, Collection $latestResults): array
    {
        $registered = (int) $stations->sum('registered_voters');
        $cast       = (int) $latestResults->sum('total_votes_cast');

        return [
            'Polling Stations'  => number_format($stations->count()),
            'Stations Reported' => number_format($latestResults->count()),
            'Registered Voters' => number_format($registered),
            'Votes Cast'        => number_format($cast),
            'Valid Votes'       => number_format((int) $latestResults->sum('valid_votes')),
            'Rejected Votes'    => number_format((int) $latestResults->sum('rejected_votes')),
            'Turnout'           => ($registered > 0 ? round($cast / $registered * 100, 2) : 0) . '%',
            'Certified'         => number_format($latestResults->whereIn('certification_status', self::CERTIFIED_STATUSES)->count()),
        ];
    }
}
