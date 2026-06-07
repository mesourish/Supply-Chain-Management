<?php
 
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Livewire\Volt\Component;
 
new class extends Component {
    public $roles, $permissions;
    public $roleId = null;
    public $name = '';
    public $selectedPermissions = [];
    public $isEditing = false;
    public $searchPermission = '';
    public $activeRoleId = null;
 
    public function mount()
    {
        $this->loadData();
        if ($this->roles->count() > 0) {
            $this->selectRole($this->roles->first()->id);
        }
    }
 
    public function loadData()
    {
        $this->roles = Role::with('permissions')->get();
        
        $allPerms = Permission::all();
        $grouped = [];
        
        foreach($allPerms as $p) {
            $formattedModule = $this->getFormattedModuleName($p->name);
            
            // Filter by search query (supporting acronyms / aliases)
            $search = strtolower(trim($this->searchPermission));
            if ($search) {
                $pNameLower = strtolower($p->name);
                $moduleLower = strtolower($formattedModule);
                $match = false;
                
                if (str_contains($pNameLower, $search) || str_contains($moduleLower, $search)) {
                    $match = true;
                } elseif ($search === 'gl' && str_contains($moduleLower, 'general ledger')) {
                    $match = true;
                } elseif ($search === 'qc' && str_contains($moduleLower, 'quality checks')) {
                    $match = true;
                } elseif ($search === 'po' && str_contains($moduleLower, 'purchase orders')) {
                    $match = true;
                } elseif ($search === 'so' && str_contains($moduleLower, 'sales orders')) {
                    $match = true;
                } elseif ($search === 'bom' && str_contains($moduleLower, 'manufacturing')) {
                    $match = true;
                }
                
                if (!$match) {
                    continue;
                }
            }
            
            $grouped[$formattedModule][] = $p;
        }
 
        // Sort modules alphabetically
        ksort($grouped);
        $this->permissions = $grouped;
    }
 
    public function getFormattedModuleName($permissionName)
    {
        if (str_contains($permissionName, '_l1') || str_contains($permissionName, '_l2') || str_contains($permissionName, 'level')) {
            return 'Level of Action';
        }
        
        $parts = explode(' ', $permissionName);
        $module = count($parts) > 1 ? $parts[1] : $parts[0];
        
        // Format module name professionally
        $formatted = str_replace('_', ' ', $module);
        $formatted = ucwords($formatted);
        
        $lower = strtolower($formatted);
        if ($lower === 'rfqs') {
            return 'RFQs';
        } elseif ($lower === 'crm leads') {
            return 'CRM Leads';
        } elseif ($lower === 'grn') {
            return 'GRN';
        } elseif ($lower === 'general ledger') {
            return 'General Ledger';
        } elseif ($lower === 'quality checks') {
            return 'Quality Checks';
        } elseif ($lower === 'manufacturing') {
            return 'Manufacturing';
        }
        
        return $formatted;
    }
 
    public function getPermissionDisplayName($permissionName)
    {
        if (str_contains($permissionName, '_l1')) {
            return str_replace(['approve_l1', '_'], ['Level 1 Approve', ' '], $permissionName);
        }
        if (str_contains($permissionName, '_l2')) {
            return str_replace(['approve_l2', '_'], ['Level 2 Approve', ' '], $permissionName);
        }
        
        $formattedModule = $this->getFormattedModuleName($permissionName);
        
        // Strip the module words from the permission name (e.g. "view purchase_orders" -> "view")
        $name = str_replace('_', ' ', $permissionName);
        
        $moduleWords = explode(' ', strtolower($formattedModule));
        $nameWords = explode(' ', strtolower($name));
        
        $resultWords = [];
        foreach ($nameWords as $word) {
            if (!in_array($word, $moduleWords)) {
                $resultWords[] = $word;
            }
        }
        
        if (empty($resultWords)) {
            return $name;
        }
        
        return implode(' ', $resultWords);
    }
 
    public function selectRole($id)
    {
        $role = Role::findById($id);
        $this->activeRoleId = $id;
        $this->roleId = $id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->isEditing = true;
    }
 
    public function createNewRole()
    {
        $this->resetInputFields();
        $this->activeRoleId = null;
        $this->isEditing = true;
    }
 
    public function toggleModulePermissions($moduleName)
    {
        // Find all permissions belonging to this module
        $modulePerms = [];
        $allPerms = Permission::all();
 
        foreach($allPerms as $p) {
            $formatted = $this->getFormattedModuleName($p->name);
            if ($formatted === $moduleName) {
                $modulePerms[] = $p->name;
            }
        }
 
        // Determine if all are currently selected
        $allSelected = true;
        foreach ($modulePerms as $perm) {
            if (!in_array($perm, $this->selectedPermissions)) {
                $allSelected = false;
                break;
            }
        }
 
        if ($allSelected) {
            // Remove all
            $this->selectedPermissions = array_diff($this->selectedPermissions, $modulePerms);
        } else {
            // Add all (uniquely)
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $modulePerms));
        }
    }
 
    public function updatedSearchPermission()
    {
        $this->loadData();
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
 
        $this->loadData();
        $this->selectRole($role->id);
        $this->dispatch('toast', type: 'success', message: $this->roleId ? 'Role updated successfully.' : 'Role created successfully.');
    }
 
    public function delete($id)
    {
        if (!auth()->user()->can('manage roles')) abort(403);
        $role = Role::findById($id);
        if ($role->name === 'Super Admin') {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete Super Admin role.');
            return;
        }
        
        $role->delete();
        $this->resetInputFields();
        $this->loadData();
        if ($this->roles->count() > 0) {
            $this->selectRole($this->roles->first()->id);
        }
        $this->dispatch('toast', type: 'success', message: 'Role deleted successfully.');
    }
 
    public function resetInputFields()
    {
        $this->name = '';
        $this->selectedPermissions = [];
        $this->roleId = null;
        $this->activeRoleId = null;
        $this->isEditing = false;
    }
}; ?>

<div class="relative min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-indigo-50/20 text-slate-800">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        
        <!-- Header Banner -->
        <div class="relative rounded-3xl overflow-hidden mb-8 shadow-xl bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 p-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-indigo-200/10">
            <div class="z-10 text-center md:text-left">
                <span class="px-3 py-1 text-xs font-semibold bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/30 uppercase tracking-widest animate-pulse">Administration</span>
                <h1 class="text-4xl font-extrabold text-white mt-3 tracking-tight">Access Control Center</h1>
                <p class="text-indigo-200/70 text-sm mt-1 max-w-xl">Configure access roles, toggle modular permissions, and manage security rules for newly integrated workflows.</p>
            </div>
            <div class="z-10 flex gap-3">
                <button wire:click="createNewRole" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs px-4.5 py-2.5 rounded-xl shadow-md hover:shadow-lg transition duration-200 flex items-center gap-2 border border-indigo-500/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    New Access Role
                </button>
                <a href="{{ url('/admin/users') }}" class="bg-slate-800/80 hover:bg-slate-850 text-indigo-300 font-semibold text-xs px-4 py-2.5 rounded-xl border border-indigo-500/20 transition duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Manage Users
                </a>
            </div>
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl"></div>
        </div>

        <!-- Master Split Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Side: Roles Directory -->
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Access Roles Directory</h2>
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 rounded-md">{{ $roles->count() }} Roles</span>
                    </div>

                    <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        @foreach($roles as $role)
                            @php
                                $isActive = ($activeRoleId === $role->id);
                                $userCount = User::role($role->name)->count();
                            @endphp
                            <button wire:click="selectRole({{ $role->id }})" 
                                    class="w-full text-left p-4 rounded-2xl border transition duration-200 relative group flex justify-between items-center
                                    {{ $isActive ? 'border-indigo-650 bg-indigo-50/30 shadow-sm' : 'border-slate-100 hover:border-slate-300 bg-white hover:bg-slate-50/50' }}">
                                
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold transition
                                        {{ $isActive ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-500 group-hover:bg-indigo-50 group-hover:text-indigo-600' }}">
                                        {{ strtoupper(substr($role->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <h3 class="text-xs font-bold text-slate-800 leading-tight">{{ $role->name }}</h3>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <span class="text-[9px] font-bold text-slate-400 font-mono">{{ $role->permissions->count() }} Perms</span>
                                            <span class="text-[9px] text-slate-300">•</span>
                                            <span class="text-[9px] font-bold text-slate-400 font-mono">{{ $userCount }} Users</span>
                                        </div>
                                    </div>
                                </div>

                                <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>

                                @if($isActive)
                                    <div class="absolute left-0 top-3 bottom-3 w-1 bg-indigo-650 rounded-r-full"></div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Right Side: Config Editor -->
            <div class="lg:col-span-2">
                @if($isEditing || !$roles->count())
                    <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-6 md:p-8">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
                            <h2 class="text-base font-extrabold text-slate-850 flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-indigo-600"></span>
                                </span>
                                {{ $roleId ? 'Configure: ' . $name : 'Create Access Role' }}
                            </h2>

                            <!-- Delete Option -->
                            @if($roleId && $name !== 'Super Admin' && auth()->user()->can('manage roles'))
                                <button wire:click="delete({{ $roleId }})" 
                                        wire:confirm="Are you sure you want to delete the '{{ $name }}' role? This will revoke access for all assigned users."
                                        class="text-xs font-bold text-rose-600 hover:text-rose-800 transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Delete Role
                                </button>
                            @endif
                        </div>

                        <form wire:submit="save" class="space-y-6">
                            <!-- Role Name Input -->
                            <div class="max-w-md">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Role Name *</label>
                                <input wire:model="name" 
                                       type="text" 
                                       placeholder="e.g. Warehouse Lead" 
                                       class="block w-full rounded-xl border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs text-slate-850 bg-slate-50/50 p-3 font-semibold"
                                       {{ $name === 'Super Admin' ? 'disabled' : '' }} 
                                       required />
                                @error('name') <span class="text-rose-500 text-[10px] mt-1.5 block font-bold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Permissions Search & Header -->
                            <div class="border-t border-slate-100 pt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                                    <div>
                                        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-widest">Grant Module Access Privileges</h3>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Toggle privileges or use "Select All" module toggles to configure the role.</p>
                                    </div>
                                    
                                    <!-- Search input -->
                                    <div class="relative w-full sm:w-60">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                        </span>
                                        <input wire:model.live.debounce.150ms="searchPermission" 
                                               type="text" 
                                               placeholder="Filter permissions..." 
                                               class="w-full pl-8 pr-4 py-1.5 text-[11px] bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
                                    </div>
                                </div>

                                <!-- Permissions Matrix Cards -->
                                @if(empty($permissions))
                                    <div class="text-center py-12 text-slate-400 text-xs font-semibold">
                                        No permissions matched your query.
                                    </div>
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        @foreach($permissions as $module => $perms)
                                            <!-- Module Box Card -->
                                            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden hover:shadow transition duration-200 flex flex-col justify-between">
                                                
                                                <!-- Card Header -->
                                                <div class="bg-slate-50/80 px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                                                    <h4 class="font-extrabold text-slate-800 text-[11px] tracking-wide flex items-center gap-1.5">
                                                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                                        {{ $module }}
                                                    </h4>
                                                    
                                                    @if($name !== 'Super Admin')
                                                        <button type="button" 
                                                                wire:click="toggleModulePermissions('{{ $module }}')" 
                                                                class="text-[9px] font-black text-indigo-650 hover:text-indigo-850 uppercase tracking-wider">
                                                            Toggle All
                                                        </button>
                                                    @endif
                                                </div>
 
                                                <!-- Card Content -->
                                                <div class="p-4 space-y-3 bg-white/50 flex-1">
                                                    @foreach($perms as $permission)
                                                        @php
                                                            $isPermActive = in_array($permission->name, $selectedPermissions);
                                                            $displayName = $this->getPermissionDisplayName($permission->name);
                                                        @endphp
                                                        <label class="flex items-center justify-between cursor-pointer group">
                                                            <span class="text-xs font-semibold transition duration-150
                                                                {{ $isPermActive ? 'text-indigo-650' : 'text-slate-500 group-hover:text-slate-800' }}">
                                                                {{ ucfirst($displayName) }}
                                                            </span>
                                                            
                                                            <div class="relative inline-flex items-center">
                                                                <input type="checkbox" 
                                                                       wire:model="selectedPermissions" 
                                                                       value="{{ $permission->name }}" 
                                                                       class="sr-only peer"
                                                                       {{ $name === 'Super Admin' ? 'disabled' : '' }}>
                                                                <div class="w-10 h-6 bg-rose-50 border border-rose-200/80 rounded-full transition-all duration-300 relative
                                                                            peer-checked:bg-emerald-500 peer-checked:border-emerald-600/30
                                                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-rose-500 after:shadow-sm
                                                                            after:rounded-full after:h-5 after:w-5 after:transition-all after:duration-300
                                                                            peer-checked:after:translate-x-4 peer-checked:after:bg-white
                                                                            peer-disabled:opacity-50 peer-disabled:cursor-not-allowed">
                                                                </div>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <!-- Save / Cancel Operations Form Buttons -->
                            @if($name !== 'Super Admin' && auth()->user()->can('manage roles'))
                                <div class="mt-8 pt-6 border-t border-slate-100 flex items-center gap-3">
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition duration-150">
                                        {{ $roleId ? 'Update Role Settings' : 'Create Access Role' }}
                                    </button>
                                </div>
                            @else
                                <div class="mt-8 pt-6 border-t border-slate-100 text-slate-400 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-slate-350"></span>
                                    Super Admin role permissions are managed dynamically.
                                </div>
                            @endif
                        </form>
                    </div>
                @else
                    <!-- No Active Selection State View -->
                    <div class="bg-white border border-slate-200 shadow-sm rounded-3xl p-12 text-center text-slate-500">
                        <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400 mx-auto mb-4 border">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <h3 class="font-bold text-slate-800 text-base mb-1">No Access Role Selected</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto mb-6">Select an existing role from the left directory column to adjust its configurations, or click the button to create a new one.</p>
                        <button wire:click="createNewRole" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition">Create Access Role</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
