<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PollingStation;
use App\Models\User;

class GPSValidationService
{
    public function validateOfficerLocation(
        User $user,
        float $submittedLat,
        float $submittedLng,
        float $accuracyMeters = 0
    ): array {
        $station = PollingStation::where('assigned_officer_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$station) {
            return $this->result(false, 'no_station_assigned',
                'No active polling station is assigned to this officer.'
            );
        }

        if ($accuracyMeters > 200) {
            return $this->result(false, 'gps_accuracy_too_low',
                "GPS accuracy ({$accuracyMeters}m) is too low. Move to open area and try again.",
                ['accuracy_meters' => $accuracyMeters]
            );
        }

        $distanceMeters = $station->getDistanceInMeters($submittedLat, $submittedLng);
        $allowedRadius = $station->election->gps_validation_radius_meters ?? 100;

        $isValid = $distanceMeters <= $allowedRadius;

        $result = $this->result(
            $isValid,
            $isValid ? 'within_radius' : 'outside_radius',
            $isValid
                ? "Location validated. {$distanceMeters}m from station."
                : "You are {$distanceMeters}m from your station. Must be within {$allowedRadius}m.",
            [
                'distance_meters' => $distanceMeters,
                'allowed_radius' => $allowedRadius,
                'station_id' => $station->id,
                'station_code' => $station->code,
                'station_name' => $station->name,
                'station_lat' => $station->latitude,
                'station_lng' => $station->longitude,
                'submitted_lat' => $submittedLat,
                'submitted_lng' => $submittedLng,
                'accuracy_meters' => $accuracyMeters,
            ]
        );

        AuditLog::record(
            action: $isValid ? 'gps.validation.passed' : 'gps.validation.failed',
            event: $isValid ? 'success' : 'failure',
            module: 'GPSValidation',
            auditable: $station,
            extra: array_merge($result['data'], [
                'election_id' => $station->election_id,
                'latitude' => $submittedLat,
                'longitude' => $submittedLng,
                'outcome' => $isValid ? 'success' : 'failure',
                'failure_reason' => $isValid ? null : $result['message'],
            ])
        );

        return $result;
    }

    public function isOfficerAtStation(User $user, float $lat, float $lng): bool
    {
        $station = PollingStation::where('assigned_officer_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$station) return false;

        return $station->isWithinGpsRadius($lat, $lng);
    }

    private function result(bool $valid, string $code, string $message, array $data = []): array
    {
        return [
            'valid' => $valid,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
    }
}
