<?php

namespace App\Support\Campus;

use App\Models\Campus\AssetCategory;
use App\Models\Campus\Building;
use App\Models\Campus\CampusAsset;
use App\Models\Campus\CampusLocation;
use App\Models\Campus\FacilityMaintenanceTicket;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AdminCampusExportService
{
    public function payload(string $resource): array
    {
        return match ($resource) {
            'campus-locations' => $this->locations(),
            'buildings' => $this->buildings(),
            'rooms' => $this->rooms(),
            'room-reservations' => $this->reservations(),
            'campus-assets' => $this->assets(),
            'facility-maintenances' => $this->tickets(),
            'asset-categories' => $this->assetCategories(),
            default => throw new InvalidArgumentException('Resource kampus tidak didukung.'),
        };
    }

    public function importHeaders(string $resource): array
    {
        return $this->payload($resource)['headers'];
    }

    private function locations(): array
    {
        return $this->build('Lokasi Kampus', ['Nama', 'Kode', 'Alamat', 'Telepon', 'Utama', 'Gedung', 'Aktif'], CampusLocation::query()
            ->withCount('buildings')
            ->orderBy('name')
            ->get()
            ->map(fn (CampusLocation $location) => [
                $location->name,
                $location->code,
                $location->address,
                $location->phone,
                $this->yesNo($location->is_main),
                $location->buildings_count,
                $this->yesNo($location->is_active),
            ]));
    }

    private function buildings(): array
    {
        return $this->build('Gedung', ['Nama', 'Kode', 'Lokasi', 'Lantai', 'Ruangan', 'Aktif'], Building::query()
            ->with('campusLocation:id,name')
            ->withCount('rooms')
            ->orderBy('name')
            ->get()
            ->map(fn (Building $building) => [
                $building->name,
                $building->code,
                $building->campusLocation?->name,
                $building->floor_count,
                $building->rooms_count,
                $this->yesNo($building->is_active),
            ]));
    }

    private function rooms(): array
    {
        return $this->build('Ruangan', ['Nama', 'Kode', 'Gedung', 'Lantai', 'Kapasitas', 'Tipe', 'Aktif'], Room::query()
            ->with('building:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                $room->name,
                $room->code,
                $room->building?->name,
                $room->floor,
                $room->capacity,
                $room->type,
                $this->yesNo($room->is_active),
            ]));
    }

    private function reservations(): array
    {
        return $this->build('Peminjaman Ruangan', ['Nomor', 'Ruangan', 'Judul', 'Tanggal', 'Jam', 'Status'], RoomReservation::query()
            ->with('room:id,name')
            ->orderByDesc('reservation_date')
            ->get()
            ->map(fn (RoomReservation $reservation) => [
                $reservation->reservation_number,
                $reservation->room?->name,
                $reservation->title,
                $this->date($reservation->reservation_date),
                substr((string) $reservation->start_time, 0, 5).' - '.substr((string) $reservation->end_time, 0, 5),
                $reservation->status,
            ]));
    }

    private function assets(): array
    {
        return $this->build('Aset Kampus', ['Kode', 'Nama', 'Kategori', 'Ruangan', 'Kondisi', 'Status', 'Sumber Dana'], CampusAsset::query()
            ->with('room:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (CampusAsset $asset) => [
                $asset->asset_code,
                $asset->name,
                $asset->category,
                $asset->room?->name,
                $asset->condition,
                $asset->status,
                $asset->funding_source,
            ]));
    }

    private function tickets(): array
    {
        return $this->build('Laporan Kerusakan', ['Tiket', 'Judul', 'Prioritas', 'Status'], FacilityMaintenanceTicket::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (FacilityMaintenanceTicket $ticket) => [
                $ticket->ticket_number,
                $ticket->title,
                $ticket->priority,
                $ticket->status,
            ]));
    }

    private function assetCategories(): array
    {
        return $this->build('Kategori Aset', ['Nama', 'Kode', 'Jml Aset', 'Aktif'], AssetCategory::query()
            ->withCount('assets')
            ->orderBy('name')
            ->get()
            ->map(fn (AssetCategory $category) => [
                $category->name,
                $category->code,
                $category->assets_count,
                $this->yesNo($category->is_active),
            ]));
    }

    private function build(string $title, array $headers, Collection $rows): array
    {
        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows->values()->all(),
        ];
    }

    private function yesNo(mixed $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d M Y');
        }

        return (string) $value;
    }
}
