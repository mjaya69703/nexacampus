<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public EmployeeAttendanceLocation $location;
    public array $form = [];

    public function mount(int $id): void
    {
        $this->location = EmployeeAttendanceLocation::findOrFail($id);
        $this->form = $this->location->only(['name', 'code', 'address', 'latitude', 'longitude', 'radius_meters', 'is_active']);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());
        $this->location->update($this->payload($validated['form'], 'updated_by'));
        session()->flash('success', 'Lokasi absensi berhasil diperbarui.');
        $this->redirectRoute('admin.organization.employee-attendance-locations.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.organization.employee-attendance-locations.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Edit Lokasi Absensi']);
    }

    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:80', Rule::unique('employee_attendance_locations', 'code')->ignore($this->location->id)],
            'form.address' => ['nullable', 'string'],
            'form.latitude' => ['required', 'numeric', 'between:-90,90'],
            'form.longitude' => ['required', 'numeric', 'between:-180,180'],
            'form.radius_meters' => ['required', 'integer', 'min:10'],
            'form.is_active' => ['boolean'],
        ];
    }

    private function payload(array $form, string $auditField): array
    {
        return [
            'name' => $form['name'],
            'code' => strtoupper($form['code']),
            'address' => $form['address'] ?: null,
            'latitude' => $form['latitude'],
            'longitude' => $form['longitude'],
            'radius_meters' => $form['radius_meters'],
            'is_active' => (bool) $form['is_active'],
            $auditField => auth()->id(),
        ];
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Edit Lokasi Absensi</h3>
            <a href="{{ route('admin.organization.employee-attendance-locations.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
        <div class="card-body">
            @include('components.admin.organization.employee-attendance-locations._form')
        </div>
    </div>
</div>
