<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Volt\Component;

new class extends Component {
    public $roles, $permissions;
    public $roleId = null;
    public $name = '';
    public $selectedPermissions = [];
    public $isEditing = false;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->roles = Role::with('permissions')->get();
        // Group permissions by first word (module)
        $allPerms = Permission::all();
        $grouped = [];
        foreach($allPerms as $p) {
            $parts = explode(' ', $p->name);
            $module = count($parts) > 1 ? $parts[1] : $parts[0];
            $grouped[ucfirst($module)][] = $p;
        }
        $this->permissions = $grouped;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|unique:roles,name,' . $this->roleId,
            'selectedPermissions' => 'array',
        ];
    }

    public function save()
    {
        if (!auth()->user()->can('manage roles')) abort(403);
        $this->validate();

        $role = Role::updateOrCreate(
            ['id' => $this->roleId],
            ['name' => $this->name, 'guard_name' => 'web']
        );

        $role->syncPermissions($this->selectedPermissions);

        $this->resetInputFields();
        $this->loadData();
        session()->flash('message', $this->roleId ? 'Role Updated Successfully.' : 'Role Created Successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('manage roles')) abort(403);
        $role = Role::findById($id);
        $this->roleId = $id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->isEditing = true;
    }

    public function delete($id)
    {
        if (!auth()->user()->can('manage roles')) abort(403);
        $role = Role::findById($id);
        if ($role->name === 'Super Admin') {
            session()->flash('error', 'Cannot delete Super Admin role.');
            return;
        }
        $role->delete();
        $this->loadData();
        session()->flash('message', 'Role Deleted Successfully.');
    }

    public function resetInputFields()
    {
        $this->name = '';
        $this->selectedPermissions = [];
        $this->roleId = null;
        $this->isEditing = false;
    }
}; ?>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">{{ $isEditing ? 'Edit Role & Permissions' : 'Create New Role' }}</h2>

            @if (session()->has('message'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('message') }}</span>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <form wire:submit="save">
                <div class="mb-4">
                    <x-input-label for="name" value="Role Name *" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full max-w-md" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <h3 class="text-lg font-medium text-gray-900 mt-6 mb-3">Assign Permissions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 bg-gray-50 p-4 rounded-md border border-gray-200">
                    @foreach($permissions as $module => $perms)
                        <div class="space-y-2">
                            <h4 class="font-semibold text-indigo-700 border-b border-indigo-200 pb-1 mb-2">{{ $module }}</h4>
                            @foreach($perms as $permission)
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700 capitalize">{{ str_replace($module, '', $permission->name) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <x-primary-button>{{ $isEditing ? 'Update Role' : 'Save Role' }}</x-primary-button>
                    @if($isEditing)
                        <x-secondary-button wire:click="resetInputFields">Cancel</x-secondary-button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h2 class="text-2xl font-semibold mb-4">Roles Directory</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($roles as $role)
                    <div class="border rounded-lg p-4 shadow-sm relative {{ $role->name === 'Super Admin' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' }}">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $role->name }}</h3>
                        <div class="flex flex-wrap gap-1 mb-4 h-24 overflow-y-auto">
                            @if($role->name === 'Super Admin')
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">All Permissions</span>
                            @else
                                @foreach($role->permissions as $perm)
                                    <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600">{{ $perm->name }}</span>
                                @endforeach
                            @endif
                        </div>
                        <div class="mt-auto border-t pt-3 flex justify-end space-x-3">
                            <button wire:click="edit({{ $role->id }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Edit</button>
                            @if($role->name !== 'Super Admin')
                                <button wire:click="delete({{ $role->id }})" class="text-red-600 hover:text-red-900 text-sm font-medium" wire:confirm="Are you sure?">Delete</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
