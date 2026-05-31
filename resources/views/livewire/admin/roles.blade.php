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
            
            // Format module name professionally
            $formattedModule = str_replace('_', ' ', $module);
            $formattedModule = ucwords($formattedModule);
            if (strtolower($formattedModule) === 'rfqs') {
                $formattedModule = 'RFQs';
            }
            
            $grouped[$formattedModule][] = $p;
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
        $this->dispatch('toast', type: 'success', message:  $this->roleId ? 'Role Updated Successfully.' : 'Role Created Successfully.');
    }

    public function edit($id)
    {
        $this->dispatch('toast', type: 'success', message:  'Details loaded successfully.');
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
            $this->dispatch('toast', type: 'error', message:  'Cannot delete Super Admin role.');
            return;
        }
        $role->delete();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message:  'Role Deleted Successfully.');
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

            
            

            <form wire:submit="save">
                <div class="mb-4">
                    <x-input-label for="name" value="Role Name *" />
                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full max-w-md" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <h3 class="text-lg font-medium text-gray-900 mt-6 mb-3">Assign Permissions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @foreach($permissions as $module => $perms)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200">
                            <div class="bg-slate-50 px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <h4 class="font-bold text-slate-800 capitalize flex items-center gap-2">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                    {{ str_replace('_', ' ', $module) }}
                                </h4>
                            </div>
                            <div class="p-4 space-y-3">
                                @foreach($perms as $permission)
                                    <label class="flex items-center justify-between cursor-pointer group">
                                        <span class="text-sm font-medium text-slate-600 group-hover:text-indigo-600 transition-colors capitalize">
                                            {{ explode(' ', $permission->name)[0] }}
                                        </span>
                                        <div class="relative inline-flex items-center">
                                            <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="sr-only peer">
                                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
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
