<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;

new class extends Component {
    use WithFileUploads;

    public $users, $roles;
    public $userId     = null;
    public $isEditing  = false;
    public $showModal  = false;
    public $search     = '';
    public $filterRole = '';
    public $filterStatus = '';
    public $activeTab  = 'personal';

    // Personal Info
    public $name = '', $email = '', $password = '', $phone = '';
    public $job_title = '', $department = '', $employee_id = '';
    public $location = '', $timezone = 'Asia/Dubai', $language = 'en';
    public $bio = '', $linkedin_url = '', $date_of_joining = '';

    // Emergency Contact
    public $emergency_contact_name = '', $emergency_contact_phone = '';

    // System
    public $selectedRole = '', $status = 'active', $two_factor_enabled = false;

    // File uploads
    public $profilePhoto   = null;
    public $signatureImage = null;

    // Existing paths (for edit mode display)
    public $existingProfilePhoto = '';
    public $existingSignature    = '';

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $query = User::with('roles');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('department', 'like', '%' . $this->search . '%')
                  ->orWhere('employee_id', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterRole) {
            $query->whereHas('roles', fn($q) => $q->where('name', $this->filterRole));
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $this->users = $query->orderBy('name')->get();
        $this->roles = Role::orderBy('name')->get();
    }

    public function updatedSearch()  { $this->loadData(); }
    public function updatedFilterRole()   { $this->loadData(); }
    public function updatedFilterStatus() { $this->loadData(); }

    public function rules()
    {
        $rules = [
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255|unique:users,email,' . ($this->userId ?? 'NULL'),
            'selectedRole' => 'required|string|exists:roles,name',
            'phone'        => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:100',
            'department'   => 'nullable|string|max:100',
            'employee_id'  => 'nullable|string|max:50',
            'location'     => 'nullable|string|max:150',
            'timezone'     => 'nullable|string|max:60',
            'language'     => 'nullable|string|max:10',
            'bio'          => 'nullable|string|max:1000',
            'linkedin_url' => 'nullable|url|max:255',
            'date_of_joining'         => 'nullable|date',
            'emergency_contact_name'  => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'status'                  => 'required|in:active,inactive,suspended',
            'profilePhoto'   => 'nullable|image|max:2048',
            'signatureImage' => 'nullable|image|max:2048',
        ];

        if (!$this->isEditing || !empty($this->password)) {
            $rules['password'] = ['required', Rules\Password::defaults()];
        }

        return $rules;
    }

    public function openCreate()
    {
        $this->resetForm();
        $this->showModal = true;
        $this->activeTab = 'personal';
    }

    public function save()
    {
        if (!auth()->user()->can('manage users')) abort(403);
        $this->validate();

        $data = [
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'job_title'  => $this->job_title,
            'department' => $this->department,
            'employee_id'=> $this->employee_id,
            'location'   => $this->location,
            'timezone'   => $this->timezone,
            'language'   => $this->language,
            'bio'        => $this->bio,
            'linkedin_url' => $this->linkedin_url,
            'date_of_joining'         => $this->date_of_joining ?: null,
            'emergency_contact_name'  => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'status'                  => $this->status,
            'two_factor_enabled'      => $this->two_factor_enabled,
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        // Handle profile photo upload
        if ($this->profilePhoto) {
            if ($this->existingProfilePhoto) {
                Storage::disk('public')->delete($this->existingProfilePhoto);
            }
            $data['profile_photo_path'] = $this->profilePhoto->store('profile-photos', 'public');
        }

        // Handle signature upload
        if ($this->signatureImage) {
            if ($this->existingSignature) {
                Storage::disk('public')->delete($this->existingSignature);
            }
            $data['signature_path'] = $this->signatureImage->store('signatures', 'public');
        }

        $user = User::updateOrCreate(['id' => $this->userId], $data);
        $user->syncRoles([$this->selectedRole]);

        $this->resetForm();
        $this->showModal = false;
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: $this->userId ? 'User updated successfully.' : 'User created successfully.');
    }

    public function edit($id)
    {
        if (!auth()->user()->can('manage users')) abort(403);
        $user = User::findOrFail($id);

        $this->userId            = $id;
        $this->name              = $user->name;
        $this->email             = $user->email;
        $this->phone             = $user->phone ?? '';
        $this->job_title         = $user->job_title ?? '';
        $this->department        = $user->department ?? '';
        $this->employee_id       = $user->employee_id ?? '';
        $this->location          = $user->location ?? '';
        $this->timezone          = $user->timezone ?? 'Asia/Dubai';
        $this->language          = $user->language ?? 'en';
        $this->bio               = $user->bio ?? '';
        $this->linkedin_url      = $user->linkedin_url ?? '';
        $this->date_of_joining   = $user->date_of_joining ? $user->date_of_joining->format('Y-m-d') : '';
        $this->emergency_contact_name  = $user->emergency_contact_name ?? '';
        $this->emergency_contact_phone = $user->emergency_contact_phone ?? '';
        $this->status            = $user->status ?? 'active';
        $this->two_factor_enabled = $user->two_factor_enabled ?? false;
        $this->selectedRole      = $user->roles->first()?->name ?? '';
        $this->password          = '';
        $this->existingProfilePhoto = $user->profile_photo_path ?? '';
        $this->existingSignature    = $user->signature_path ?? '';
        $this->isEditing  = true;
        $this->showModal  = true;
        $this->activeTab  = 'personal';
    }

    public function delete($id)
    {
        if (!auth()->user()->can('manage users')) abort(403);
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'You cannot delete yourself.');
            return;
        }
        if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
        if ($user->signature_path)     Storage::disk('public')->delete($user->signature_path);
        $user->delete();
        $this->loadData();
        $this->dispatch('toast', type: 'success', message: 'User deleted successfully.');
    }

    public function resetForm()
    {
        $this->userId              = null;
        $this->isEditing           = false;
        $this->name                = '';
        $this->email               = '';
        $this->password            = '';
        $this->phone               = '';
        $this->job_title           = '';
        $this->department          = '';
        $this->employee_id         = '';
        $this->location            = '';
        $this->timezone            = 'Asia/Dubai';
        $this->language            = 'en';
        $this->bio                 = '';
        $this->linkedin_url        = '';
        $this->date_of_joining     = '';
        $this->emergency_contact_name  = '';
        $this->emergency_contact_phone = '';
        $this->selectedRole        = '';
        $this->status              = 'active';
        $this->two_factor_enabled  = false;
        $this->profilePhoto        = null;
        $this->signatureImage      = null;
        $this->existingProfilePhoto = '';
        $this->existingSignature   = '';
        $this->resetValidation();
    }
}; ?>

<div class="min-h-screen" style="background: #f0f4f8;">

    {{-- ═══ PAGE HEADER ═══ --}}
    <div class="px-6 py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">User Management</h1>
                <p class="text-white/70 text-sm mt-0.5">Manage user accounts, roles, and profile details</p>
            </div>
            @can('manage users')
            <button wire:click="openCreate" id="btn-create-user"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-indigo-700 bg-white shadow-lg hover:shadow-xl transition-all duration-200 hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                New User
            </button>
            @endcan
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-6 space-y-5">

        {{-- ═══ STATS ROW ═══ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
                $totalUsers  = $users->count();
                $activeUsers = $users->where('status', 'active')->count();
                $admins      = $users->filter(fn($u) => $u->roles->where('name','Super Admin')->count() + $u->roles->where('name','Admin')->count() > 0)->count();
                $newThisMonth = $users->filter(fn($u) => $u->created_at && $u->created_at->isCurrentMonth())->count();
            @endphp
            @foreach([
                ['Total Users',  $totalUsers,  '#6366f1', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['Active',       $activeUsers, '#10b981', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['Administrators',$admins,     '#f59e0b', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['New This Month',$newThisMonth,'#8b5cf6','M12 6v6m0 0v6m0-6h6m-6 0H6'],
            ] as [$label, $val, $color, $path])
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background: {{ $color }}18;">
                    <svg class="w-5 h-5" style="color:{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $val }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $label }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ═══ FILTERS & TABLE ═══ --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            {{-- Table Header + Filters --}}
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
                <h2 class="text-base font-semibold text-gray-800">User Directory</h2>
                <div class="flex flex-wrap gap-2">
                    {{-- Search --}}
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input wire:model.live="search" type="text" placeholder="Search users…"
                               class="pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 w-52">
                    </div>
                    {{-- Role Filter --}}
                    <select wire:model.live="filterRole"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-gray-600">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    {{-- Status Filter --}}
                    <select wire:model.live="filterStatus"
                            class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-300 text-gray-600">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Employee ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Joined</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($users as $user)
                        <tr class="hover:bg-indigo-50/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    {{-- Avatar --}}
                                    <div class="w-9 h-9 rounded-xl flex-shrink-0 overflow-hidden"
                                         style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                        @if($user->profile_photo_path)
                                            <img src="{{ asset('storage/'.$user->profile_photo_path) }}"
                                                 class="w-full h-full object-cover" alt="{{ $user->name }}">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-white text-xs font-bold">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}{{ strtoupper(substr(strstr($user->name, ' '), 1, 1)) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400 leading-tight">{{ $user->email }}</p>
                                        @if($user->job_title)
                                            <p class="text-xs text-indigo-500 mt-0.5">{{ $user->job_title }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <span class="text-sm text-gray-600">{{ $user->department ?: '—' }}</span>
                            </td>
                            <td class="px-4 py-3.5 hidden lg:table-cell">
                                <span class="text-xs font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                    {{ $user->employee_id ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                @php $roleName = $user->roles->first()?->name ?? 'No Role'; @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      style="background: #eef2ff; color: #4f46e5;">
                                    {{ $roleName }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                @php
                                    $statusStyles = [
                                        'active'    => 'background:#dcfce7; color:#16a34a;',
                                        'inactive'  => 'background:#f3f4f6; color:#6b7280;',
                                        'suspended' => 'background:#fee2e2; color:#dc2626;',
                                    ];
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                      style="{{ $statusStyles[$user->status ?? 'active'] ?? '' }}">
                                    <span class="w-1.5 h-1.5 rounded-full inline-block"
                                          style="background: currentColor; opacity: 0.7;"></span>
                                    {{ ucfirst($user->status ?? 'active') }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 hidden lg:table-cell">
                                <span class="text-xs text-gray-500">
                                    {{ $user->date_of_joining ? $user->date_of_joining->format('M d, Y') : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('manage users')
                                    <button wire:click="edit({{ $user->id }})"
                                            class="p-1.5 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    @if($user->id !== auth()->id())
                                    <button wire:click="delete({{ $user->id }})"
                                            wire:confirm="Permanently delete {{ $user->name }}? This cannot be undone."
                                            class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <p class="text-sm font-medium">No users found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════
         MODAL — CREATE / EDIT USER
    ═══════════════════════════════════════ --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-2xl shadow-2xl flex flex-col"
             style="background: #fff;">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 flex-shrink-0"
                 style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div>
                    <h2 class="text-lg font-bold text-white">
                        {{ $isEditing ? 'Edit User Profile' : 'Create New User' }}
                    </h2>
                    <p class="text-white/70 text-xs mt-0.5">
                        {{ $isEditing ? 'Update user details and permissions' : 'Fill in all required information' }}
                    </p>
                </div>
                <button wire:click="$set('showModal', false)" class="text-white/70 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tab Nav --}}
            <div class="flex gap-0 px-6 pt-4 flex-shrink-0 border-b border-gray-100">
                @foreach([
                    ['personal',   '👤', 'Personal Info'],
                    ['work',       '💼', 'Work Details'],
                    ['security',   '🔒', 'Security & Access'],
                    ['media',      '🖼️', 'Photo & Signature'],
                ] as [$tab, $icon, $label])
                <button wire:click="$set('activeTab', '{{ $tab }}')"
                        class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                               {{ $activeTab === $tab
                                  ? 'border-indigo-600 text-indigo-700'
                                  : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    <span>{{ $icon }}</span>{{ $label }}
                </button>
                @endforeach
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <form wire:submit="save" id="user-form">

                    {{-- ── TAB: PERSONAL INFO ── --}}
                    @if($activeTab === 'personal')
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                                <input wire:model="name" type="text" id="u-name" placeholder="John Doe"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                                <input wire:model="email" type="email" id="u-email" placeholder="john@company.com"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Phone Number</label>
                                <input wire:model="phone" type="tel" id="u-phone" placeholder="+971 50 000 0000"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Location / City</label>
                                <input wire:model="location" type="text" id="u-location" placeholder="Dubai, UAE"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">LinkedIn URL</label>
                                <input wire:model="linkedin_url" type="url" id="u-linkedin" placeholder="https://linkedin.com/in/..."
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('linkedin_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Timezone</label>
                                <select wire:model="timezone" id="u-timezone"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 bg-white">
                                    <option value="UTC">UTC</option>
                                    <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                                    <option value="Asia/Riyadh">Asia/Riyadh (UTC+3)</option>
                                    <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                                    <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                                    <option value="Europe/London">Europe/London (UTC+0/+1)</option>
                                    <option value="Europe/Berlin">Europe/Berlin (UTC+1/+2)</option>
                                    <option value="America/New_York">America/New York (UTC-5/-4)</option>
                                    <option value="America/Los_Angeles">America/Los Angeles (UTC-8/-7)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Bio / About</label>
                            <textarea wire:model="bio" id="u-bio" rows="3" placeholder="Brief professional summary…"
                                      class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Emergency Contact Name</label>
                                <input wire:model="emergency_contact_name" type="text" id="u-ec-name" placeholder="Jane Doe"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Emergency Contact Phone</label>
                                <input wire:model="emergency_contact_phone" type="tel" id="u-ec-phone" placeholder="+971 55 000 0000"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ── TAB: WORK DETAILS ── --}}
                    @if($activeTab === 'work')
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Job Title</label>
                                <input wire:model="job_title" type="text" id="u-jobtitle" placeholder="Senior Manager"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Department</label>
                                <select wire:model="department" id="u-department"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 bg-white">
                                    <option value="">-- Select Department --</option>
                                    @foreach(['Operations','Finance','Procurement','Sales','Logistics & Fleet','IT / Technology','Human Resources','Legal & Compliance','Marketing','Executive Management'] as $dept)
                                        <option value="{{ $dept }}">{{ $dept }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Employee ID</label>
                                <input wire:model="employee_id" type="text" id="u-empid" placeholder="EMP-0001"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Date of Joining</label>
                                <input wire:model="date_of_joining" type="date" id="u-joining"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('date_of_joining') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Language</label>
                                <select wire:model="language" id="u-language"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 bg-white">
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                    <option value="fr">French</option>
                                    <option value="de">German</option>
                                    <option value="es">Spanish</option>
                                    <option value="hi">Hindi</option>
                                    <option value="ur">Urdu</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ── TAB: SECURITY & ACCESS ── --}}
                    @if($activeTab === 'security')
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">
                                    {{ $isEditing ? 'New Password (leave blank to keep current)' : 'Password' }}
                                    @if(!$isEditing) <span class="text-red-500">*</span> @endif
                                </label>
                                <input wire:model="password" type="password" id="u-password" placeholder="Min 8 characters"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400">
                                @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Assign Role <span class="text-red-500">*</span></label>
                                <select wire:model="selectedRole" id="u-role"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 bg-white">
                                    <option value="">-- Select Role --</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedRole') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Account Status</label>
                            <div class="flex gap-3">
                                @foreach(['active' => ['✅', '#dcfce7', '#16a34a'], 'inactive' => ['⏸️', '#f3f4f6', '#6b7280'], 'suspended' => ['🚫', '#fee2e2', '#dc2626']] as $s => [$icon, $bg, $color])
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" wire:model="status" value="{{ $s }}" class="sr-only">
                                    <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 transition-all text-sm font-medium
                                                {{ $status === $s ? 'border-indigo-400' : 'border-gray-200' }}"
                                         style="{{ $status === $s ? "background: {$bg};" : '' }}">
                                        <span>{{ $icon }}</span>
                                        <span style="{{ $status === $s ? "color: {$color};" : '' }}">{{ ucfirst($s) }}</span>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        @if($isEditing)
                        <div class="rounded-xl p-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <h4 class="text-xs font-semibold text-gray-600 mb-3 uppercase tracking-wider">Login Activity</h4>
                            @php $editUser = $userId ? \App\Models\User::find($userId) : null; @endphp
                            @if($editUser)
                            <div class="grid grid-cols-2 gap-3 text-xs text-gray-600">
                                <div>
                                    <span class="text-gray-400">Last Login:</span>
                                    <span class="font-medium ml-1">{{ $editUser->last_login_at ? $editUser->last_login_at->diffForHumans() : 'Never' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">IP Address:</span>
                                    <span class="font-mono font-medium ml-1">{{ $editUser->last_login_ip ?? '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Account Created:</span>
                                    <span class="font-medium ml-1">{{ $editUser->created_at?->format('M d, Y') ?? '—' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-400">Email Verified:</span>
                                    <span class="font-medium ml-1">{{ $editUser->email_verified_at ? '✅ Yes' : '❌ No' }}</span>
                                </div>
                            </div>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- ── TAB: PHOTO & SIGNATURE ── --}}
                    @if($activeTab === 'media')
                    <div class="space-y-6">
                        {{-- Profile Photo --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-3">Profile Photo</label>
                            <div class="flex items-start gap-5">
                                {{-- Preview --}}
                                <div class="w-24 h-24 rounded-2xl overflow-hidden flex-shrink-0 shadow-md"
                                     style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                    @if($profilePhoto)
                                        <img src="{{ $profilePhoto->temporaryUrl() }}" class="w-full h-full object-cover" alt="Preview">
                                    @elseif($existingProfilePhoto)
                                        <img src="{{ asset('storage/'.$existingProfilePhoto) }}" class="w-full h-full object-cover" alt="Current Photo">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-white text-2xl font-bold">
                                            {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <label for="profile-photo-upload" class="cursor-pointer">
                                        <div class="border-2 border-dashed border-indigo-200 rounded-xl p-5 text-center hover:border-indigo-400 hover:bg-indigo-50/50 transition-colors">
                                            <svg class="w-7 h-7 mx-auto mb-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <p class="text-sm font-medium text-indigo-600">Click to upload photo</p>
                                            <p class="text-xs text-gray-400 mt-1">PNG, JPG, GIF up to 2MB</p>
                                        </div>
                                    </label>
                                    <input id="profile-photo-upload" wire:model="profilePhoto" type="file"
                                           accept="image/*" class="hidden">
                                    @error('profilePhoto') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Signature --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-3">Digital Signature</label>
                            <div class="flex items-start gap-5">
                                {{-- Preview --}}
                                <div class="w-40 h-20 rounded-xl overflow-hidden flex-shrink-0 border border-gray-200 flex items-center justify-center"
                                     style="background: #fff;">
                                    @if($signatureImage)
                                        <img src="{{ $signatureImage->temporaryUrl() }}" class="max-w-full max-h-full object-contain" alt="Signature Preview">
                                    @elseif($existingSignature)
                                        <img src="{{ asset('storage/'.$existingSignature) }}" class="max-w-full max-h-full object-contain" alt="Current Signature">
                                    @else
                                        <p class="text-xs text-gray-400 text-center px-3">No signature<br>uploaded</p>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <label for="signature-upload" class="cursor-pointer">
                                        <div class="border-2 border-dashed border-purple-200 rounded-xl p-5 text-center hover:border-purple-400 hover:bg-purple-50/50 transition-colors">
                                            <svg class="w-7 h-7 mx-auto mb-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                            </svg>
                                            <p class="text-sm font-medium text-purple-600">Upload signature image</p>
                                            <p class="text-xs text-gray-400 mt-1">PNG with transparent background preferred</p>
                                        </div>
                                    </label>
                                    <input id="signature-upload" wire:model="signatureImage" type="file"
                                           accept="image/*" class="hidden">
                                    @error('signatureImage') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                </form>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between px-6 py-4 flex-shrink-0 border-t border-gray-100"
                 style="background: #f8fafc;">
                <button wire:click="$set('showModal', false)"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">
                        {{ $isEditing ? 'Editing: ' . $name : 'New user account' }}
                    </span>
                    <button wire:click="save" wire:loading.attr="disabled"
                            class="flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white rounded-xl shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
                            style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <span wire:loading.remove wire:target="save">
                            {{ $isEditing ? 'Update User' : 'Create User' }}
                        </span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            Saving…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
