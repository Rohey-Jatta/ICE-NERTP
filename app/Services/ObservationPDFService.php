<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use Carbon\Carbon;

class ObservationPDFService
{
    /**
     * Generate PDF report for an observation
     */
    public static function generate($observationId, $monitor)
    {
        $observation = DB::table('monitor_observations')
            ->where('id', $observationId)
            ->where('election_monitor_id', $monitor->id)
            ->leftJoin('polling_stations', 'monitor_observations.polling_station_id', '=', 'polling_stations.id')
            ->leftJoin('administrative_hierarchy as wards', 'polling_stations.ward_id', '=', 'wards.id')
            ->select(
                'monitor_observations.*',
                'polling_stations.name as station_name',
                'polling_stations.code as station_code',
                'wards.name as ward_name'
            )
            ->first();

        if (!$observation) {
            throw new \Exception('Observation not found');
        }

        // Parse documents and photos
        $documents = $observation->documents_paths ? json_decode($observation->documents_paths, true) : [];
        $photos = $observation->photo_paths ? json_decode($observation->photo_paths, true) : [];

        // Generate unique reference number
        $referenceNumber = 'OBS-' . str_pad($observation->id, 8, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5($observation->id . $observation->created_at), 0, 4));

        // Create PDF
        $pdf = PDF::loadView('monitor.observation-pdf', [
            'observation'     => $observation,
            'referenceNumber' => $referenceNumber,
            'documents'       => $documents,
            'photos'          => $photos,
            'monitor'         => $monitor,
            'generatedAt'     => Carbon::now()->format('Y-m-d H:i:s'),
        ], [], [
            'format'       => 'A4',
            'margin_left'  => 10,
            'margin_right' => 10,
            'margin_top'   => 20,
            'margin_bottom'=> 20,
        ]);

        return $pdf;
    }

    /**
     * Generate batch PDF report for multiple observations
     */
    public static function generateBatch($observationIds, $monitor)
    {
        $observations = DB::table('monitor_observations')
            ->whereIn('id', $observationIds)
            ->where('election_monitor_id', $monitor->id)
            ->leftJoin('polling_stations', 'monitor_observations.polling_station_id', '=', 'polling_stations.id')
            ->leftJoin('administrative_hierarchy as wards', 'polling_stations.ward_id', '=', 'wards.id')
            ->select(
                'monitor_observations.*',
                'polling_stations.name as station_name',
                'polling_stations.code as station_code',
                'wards.name as ward_name'
            )
            ->get();

        if ($observations->isEmpty()) {
            throw new \Exception('No observations found');
        }

        $generatedAt = Carbon::now()->format('Y-m-d H:i:s');

        $pdf = PDF::loadView('monitor.observations-batch-pdf', [
            'observations' => $observations,
            'monitor'      => $monitor,
            'generatedAt'  => $generatedAt,
            'count'        => $observations->count(),
        ], [], [
            'format'       => 'A4',
            'margin_left'  => 10,
            'margin_right' => 10,
            'margin_top'   => 20,
            'margin_bottom'=> 20,
        ]);

        return $pdf;
    }
}