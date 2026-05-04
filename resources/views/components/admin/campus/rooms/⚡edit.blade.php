<?php

use App\Models\Campus\Building;
use App\Models\Campus\Room;
use Livewire\Component;
use Illuminate\Validation\Rule;

new class extends Component
{
    public int $roomId;
    public array $roomForm = [];
    public array $buildings = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.campus.rooms.index');
    }

    public function mount($id): void
    {
        $room = Room::findOrFail($id);
        $this->roomId = (int) $id;

        $this->buildings = Building::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        $this->roomForm = [
            'building_id' => $room->building_id,
            'name' => $room->name,
            'code' => $room->code,
            'floor' => $room->floor,
            'capacity' => $room->capacity,
            'type' => $room->type,
            'is_active' => (bool) $room->is_active,
            'desc' => $room->desc,
        ];
    }

    public function updateRoom(): void
    {
        $validatedData = $this->validate([
            'roomForm.building_id' => 'nullable|exists:buildings,id',
            'roomForm.name' => 'required|string|max:255',
            'roomForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('rooms', 'code')->ignore($this->roomId),
            ],
            'roomForm.floor' => 'nullable|string',
            'roomForm.capacity' => 'nullable|integer|min:1',
            'roomForm.type' => 'required|in:Classroom,Laboratory,Auditorium,Office,Meeting Room,Library,Other',
            'roomForm.is_active' => 'boolean',
            'roomForm.desc' => 'nullable|string',
        ]);

        $room = Room::findOrFail($this->roomId);

        $payload = [
            'building_id' => $validatedData['roomForm']['building_id'] ?: null,
            'name' => $validatedData['roomForm']['name'],
            'code' => $validatedData['roomForm']['code'] ?: null,
            'floor' => $validatedData['roomForm']['floor'] ?: null,
            'capacity' => $validatedData['roomForm']['capacity'] ?: null,
            'type' => $validatedData['roomForm']['type'],
            'is_active' => (bool) $validatedData['roomForm']['is_active'],
            'desc' => $validatedData['roomForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ];

        $room->update($payload);

        session()->flash('success', 'Ruangan berhasil diperbarui.');
        $this->redirectRoute('admin.campus.rooms.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Campus Management',
            'pages' => 'Edit Ruangan',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Ruangan {{ $roomForm['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="building_id">Gedung</label>
                <select id="building_id" class="form-control" wire:model.defer="roomForm.building_id">
                    <option value="">-- Pilih Gedung --</option>
                    @foreach($buildings as $building)
                        <option value="{{ $building['id'] }}">{{ $building['name'] }}</option>
                    @endforeach
                </select>
                @error('roomForm.building_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Ruangan <span class="text-danger">*</span></label>
                <input type="text" id="name" class="form-control" wire:model.defer="roomForm.name">
                @error('roomForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode Ruangan</label>
                <input type="text" id="code" class="form-control" wire:model.defer="roomForm.code">
                @error('roomForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="floor">Lantai</label>
                <input type="text" id="floor" class="form-control" wire:model.defer="roomForm.floor">
                @error('roomForm.floor')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="capacity">Kapasitas</label>
                <input type="number" id="capacity" class="form-control" wire:model.defer="roomForm.capacity" min="1">
                @error('roomForm.capacity')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="type">Tipe Ruangan <span class="text-danger">*</span></label>
                <select id="type" class="form-control" wire:model.defer="roomForm.type">
                    <option value="Classroom">Classroom</option>
                    <option value="Laboratory">Laboratory</option>
                    <option value="Auditorium">Auditorium</option>
                    <option value="Office">Office</option>
                    <option value="Meeting Room">Meeting Room</option>
                    <option value="Library">Library</option>
                    <option value="Other">Other</option>
                </select>
                @error('roomForm.type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" wire:model.defer="roomForm.desc" rows="4"></textarea>
                @error('roomForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="roomForm.is_active">
                    <label class="form-check-label" for="is_active">
                        Ruangan Aktif
                    </label>
                </div>
            </div>
            <div class="form-group col-lg-12 col-md-12 col-sm-12 mt-4">
                <button type="button" class="btn btn-primary" wire:click="updateRoom">
                    <i class="fa fa-save me-2"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-secondary ms-2" wire:click="cancel">
                    <i class="fa fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
