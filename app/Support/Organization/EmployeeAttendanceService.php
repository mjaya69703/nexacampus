<?php

namespace App\Support\Organization;

use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceSource;
use App\Models\Organization\EmployeeProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeeAttendanceService
{
    public function checkInSelf(EmployeeProfile $employee, ?int $userId = null, array $payload = []): EmployeeAttendanceRecord
    {
        $source = $this->selfServiceSource();
        $now = now();
        $location = $this->resolveLocation($payload);
        $this->ensureInsideRadius($location);

        $record = $this->selfRecordForToday($employee, $source);

        if ($record) {
            $record->update(array_filter([
                'check_in_at' => $record->check_in_at ?: $now,
                'check_in_location_id' => $location['location']?->id,
                'check_in_latitude' => $payload['latitude'] ?? null,
                'check_in_longitude' => $payload['longitude'] ?? null,
                'check_in_accuracy_meters' => isset($payload['accuracy']) ? (int) round((float) $payload['accuracy']) : null,
                'check_in_distance_meters' => $location['distance'],
                'location_status' => $location['status'],
                'check_in_photo_path' => $payload['photo_path'] ?? $record->check_in_photo_path,
                'updated_by' => $userId,
            ], fn ($value) => $value !== null));

            return $record->refresh();
        }

        return EmployeeAttendanceRecord::create([
            'employee_profile_id' => $employee->id,
            'attendance_date' => $now->toDateString(),
            'employee_attendance_source_id' => $source->id,
            'work_unit_id' => $employee->primary_work_unit_id,
            'status' => 'present',
            'check_in_at' => $now,
            'work_minutes' => 0,
            'check_in_location_id' => $location['location']?->id,
            'check_in_latitude' => $payload['latitude'] ?? null,
            'check_in_longitude' => $payload['longitude'] ?? null,
            'check_in_accuracy_meters' => isset($payload['accuracy']) ? (int) round((float) $payload['accuracy']) : null,
            'check_in_distance_meters' => $location['distance'],
            'location_status' => $location['status'],
            'check_in_photo_path' => $payload['photo_path'] ?? null,
            'notes' => 'Check-in mandiri pegawai.',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function checkOutSelf(EmployeeProfile $employee, ?int $userId = null, array $payload = []): EmployeeAttendanceRecord
    {
        $source = $this->selfServiceSource();
        $now = now();
        $location = $this->resolveLocation($payload);
        $this->ensureInsideRadius($location);

        $record = $this->selfRecordForToday($employee, $source);

        if (! $record) {
            $record = EmployeeAttendanceRecord::create([
                'employee_profile_id' => $employee->id,
                'attendance_date' => $now->toDateString(),
                'employee_attendance_source_id' => $source->id,
                'work_unit_id' => $employee->primary_work_unit_id,
                'status' => 'present',
                'check_in_at' => $now,
                'notes' => 'Check-out mandiri tanpa check-in sebelumnya.',
                'created_by' => $userId,
            ]);
        }

        $checkIn = $record->check_in_at ?: $now;

        $record->update([
            'check_in_at' => $checkIn,
            'check_out_at' => $now,
            'work_minutes' => $this->workMinutes($checkIn, $now),
            'check_out_location_id' => $location['location']?->id,
            'check_out_latitude' => $payload['latitude'] ?? null,
            'check_out_longitude' => $payload['longitude'] ?? null,
            'check_out_accuracy_meters' => isset($payload['accuracy']) ? (int) round((float) $payload['accuracy']) : null,
            'check_out_distance_meters' => $location['distance'],
            'location_status' => $record->location_status === 'inside_radius' && $location['status'] !== 'inside_radius' ? 'partial' : $location['status'],
            'check_out_photo_path' => $payload['photo_path'] ?? $record->check_out_photo_path,
            'updated_by' => $userId,
        ]);

        return $record->refresh();
    }

    public function recordManual(EmployeeProfile $employee, array $payload, ?int $userId = null): EmployeeAttendanceRecord
    {
        $source = EmployeeAttendanceSource::firstOrCreate(
            ['code' => 'MANUAL_ADMIN'],
            [
                'name' => 'Manual Admin',
                'source_type' => 'manual',
                'description' => 'Input absensi pegawai manual dari admin.',
                'is_active' => true,
            ],
        );

        $checkIn = filled($payload['check_in_at'] ?? null) ? Carbon::parse($payload['check_in_at']) : null;
        $checkOut = filled($payload['check_out_at'] ?? null) ? Carbon::parse($payload['check_out_at']) : null;

        return EmployeeAttendanceRecord::updateOrCreate(
            [
                'employee_profile_id' => $employee->id,
                'attendance_date' => $payload['attendance_date'],
                'employee_attendance_source_id' => $source->id,
            ],
            [
                'work_unit_id' => $payload['work_unit_id'] ?? $employee->primary_work_unit_id,
                'status' => $payload['status'],
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'work_minutes' => $this->workMinutes($checkIn, $checkOut),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    private function workMinutes(?CarbonInterface $checkIn, ?CarbonInterface $checkOut): int
    {
        if (! $checkIn || ! $checkOut || $checkOut->lessThanOrEqualTo($checkIn)) {
            return 0;
        }

        return $checkIn->diffInMinutes($checkOut);
    }

    private function selfServiceSource(): EmployeeAttendanceSource
    {
        return EmployeeAttendanceSource::firstOrCreate(
            ['code' => 'EMPLOYEE_SELF'],
            [
                'name' => 'Employee Self Service',
                'source_type' => 'self_service',
                'description' => 'Check-in/check-out mandiri dari halaman pegawai.',
                'is_active' => true,
            ],
        );
    }

    private function selfRecordForToday(EmployeeProfile $employee, EmployeeAttendanceSource $source): ?EmployeeAttendanceRecord
    {
        return EmployeeAttendanceRecord::query()
            ->where('employee_profile_id', $employee->id)
            ->where('employee_attendance_source_id', $source->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();
    }

    private function resolveLocation(array $payload): array
    {
        if (! isset($payload['latitude'], $payload['longitude'])) {
            return ['location' => null, 'distance' => null, 'status' => 'gps_missing'];
        }

        $latitude = (float) $payload['latitude'];
        $longitude = (float) $payload['longitude'];
        $locations = EmployeeAttendanceLocation::where('is_active', true)->get();

        if ($locations->isEmpty()) {
            return ['location' => null, 'distance' => null, 'status' => 'unverified'];
        }

        $nearest = $locations
            ->map(function (EmployeeAttendanceLocation $location) use ($latitude, $longitude) {
                $location->distance_meters = $this->distanceMeters(
                    $latitude,
                    $longitude,
                    (float) $location->latitude,
                    (float) $location->longitude,
                );

                return $location;
            })
            ->sortBy('distance_meters')
            ->first();

        $distance = (int) round($nearest->distance_meters);

        return [
            'location' => $nearest,
            'distance' => $distance,
            'status' => $distance <= (int) $nearest->radius_meters ? 'inside_radius' : 'outside_radius',
        ];
    }

    private function ensureInsideRadius(array $location): void
    {
        if (($location['status'] ?? null) !== 'outside_radius') {
            return;
        }

        $name = $location['location']?->name ?? 'lokasi kantor';
        $distance = $location['distance'] ?? 0;
        $radius = $location['location']?->radius_meters ?? 0;

        throw ValidationException::withMessages([
            'latitude' => "Lokasi kamu di luar radius {$name}. Jarak {$distance}m, radius {$radius}m.",
        ]);
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
