<?php

use Livewire\Component;
use App\Models\Settings\Menu;

new class extends Component
{
    public array $menuForm = [];
    public array $availableParents = [];

    public function cancel()
    {
        $this->redirectRoute('admin.system.menus.index');
    }

    public function mount()
    {
        $this->menuForm = [
            'parent_id' => null,
            'type' => 'link',
            'title' => '',
            'route_name' => '',
            'url' => '',
            'icon' => '',
            'permission_name' => '',
            'sort_order' => 0,
            'is_active' => true,
        ];

        $this->availableParents = Menu::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'title' => $menu->title,
            ])
            ->toArray();
    }

    public function createMenu()
    {
        $validatedData = $this->validate([
            'menuForm.parent_id' => 'nullable|exists:menus,id',
            'menuForm.type' => 'required|in:link,group',
            'menuForm.title' => 'required|string|max:255',
            'menuForm.route_name' => 'nullable|string|max:255',
            'menuForm.url' => 'nullable|string|max:255',
            'menuForm.icon' => 'nullable|string|max:255',
            'menuForm.permission_name' => 'nullable|string|max:255',
            'menuForm.sort_order' => 'required|integer|min:0',
            'menuForm.is_active' => 'boolean',
        ]);

        Menu::create([
            'parent_id' => $validatedData['menuForm']['parent_id'] ?: null,
            'type' => $validatedData['menuForm']['type'],
            'title' => $validatedData['menuForm']['title'],
            'route_name' => $validatedData['menuForm']['route_name'] ?: null,
            'url' => $validatedData['menuForm']['url'] ?: null,
            'icon' => $validatedData['menuForm']['icon'] ?: null,
            'permission_name' => $validatedData['menuForm']['permission_name'] ?: null,
            'sort_order' => $validatedData['menuForm']['sort_order'],
            'is_active' => (bool) $validatedData['menuForm']['is_active'],
        ]);

        session()->flash('success', 'Menu berhasil ditambahkan.');
        $this->redirectRoute('admin.system.menus.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Tambah Menu',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Menu</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="type">Tipe Menu</label>
                <select id="type" class="form-control" wire:model.defer="menuForm.type">
                    <option value="link">Link</option>
                    <option value="group">Group</option>
                </select>
                @error('menuForm.type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="parent_id">Parent Menu</label>
                <select id="parent_id" class="form-control" wire:model.defer="menuForm.parent_id">
                    <option value="">Tanpa Parent</option>
                    @foreach($availableParents as $parent)
                        <option value="{{ $parent['id'] }}">{{ $parent['title'] }}</option>
                    @endforeach
                </select>
                @error('menuForm.parent_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="title">Judul Menu</label>
                <input type="text" id="title" class="form-control" wire:model.defer="menuForm.title" placeholder="Contoh: User Management">
                @error('menuForm.title')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="route_name">Route Name</label>
                <input type="text" id="route_name" class="form-control" wire:model.defer="menuForm.route_name" placeholder="Contoh: admin.system.menus.index">
                @error('menuForm.route_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="url">URL</label>
                <input type="text" id="url" class="form-control" wire:model.defer="menuForm.url" placeholder="Contoh: /manage/users">
                @error('menuForm.url')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="icon">Icon</label>
                <input type="text" id="icon" class="form-control" wire:model.defer="menuForm.icon" placeholder="Contoh: fa fa-users">
                @error('menuForm.icon')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="permission_name">Permission Name</label>
                <input type="text" id="permission_name" class="form-control" wire:model.defer="menuForm.permission_name" placeholder="Contoh: user.viewAny">
                @error('menuForm.permission_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="sort_order">Sort Order</label>
                <input type="number" id="sort_order" class="form-control" wire:model.defer="menuForm.sort_order" min="0">
                @error('menuForm.sort_order')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-4">
                <div class="form-check form-switch mt-3">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="menuForm.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                @error('menuForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createMenu">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan</button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal</button>
            </div>
        </div>
    </div>
</div>