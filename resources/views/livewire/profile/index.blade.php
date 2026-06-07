<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

new class extends Component {
    use WithFileUploads;

    public string $activeTab = 'overview';

    // Personal Info
    public string $name     = '';
    public string $email    = '';
    public string $phone    = '';
    public string $job_title = '';
    public string $department = '';
    public string $employee_id = '';
    public string $location  = '';
    public string $timezone  = 'Asia/Dubai';
    public string $language  = 'en';
    public string $bio       = '';
    public string $linkedin_url = '';
    public string $date_of_joining = '';
    public string $emergency_contact_name = '';
    public string $emergency_contact_phone = '';

    // Password change
    public string $current_password = '';
    public string $new_password     = '';
    public string $new_password_confirmation = '';

    // File uploads
    public $profilePhoto   = null;
    public $signatureImage = null;
    public string $existingPhoto     = '';
    public string $existingSignature = '';

    // Success flags
    public bool $profileSaved   = false;
    public bool $passwordSaved  = false;
    public bool $photoSaved     = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name                    = $user->name;
        $this->email                   = $user->email;
        $this->phone                   = $user->phone ?? '';
        $this->job_title               = $user->job_title ?? '';
        $this->department              = $user->department ?? '';
        $this->employee_id             = $user->employee_id ?? '';
        $this->location                = $user->location ?? '';
        $this->timezone                = $user->timezone ?? 'Asia/Dubai';
        $this->language                = $user->language ?? 'en';
        $this->bio                     = $user->bio ?? '';
        $this->linkedin_url            = $user->linkedin_url ?? '';
        $this->date_of_joining         = $user->date_of_joining ? $user->date_of_joining->format('Y-m-d') : '';
        $this->emergency_contact_name  = $user->emergency_contact_name ?? '';
        $this->emergency_contact_phone = $user->emergency_contact_phone ?? '';
        $this->existingPhoto           = $user->profile_photo_path ?? '';
        $this->existingSignature       = $user->signature_path ?? '';
    }

    public function saveProfile(): void
    {
        $user = Auth::user();
        $validated = $this->validate([
            'name'         => 'required|string|max:255',
            'email'        => ['required', 'email', Rule::unique('users')->ignore($user->id)],
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
        ]);

        if ($user->email !== $this->email) {
            $user->email_verified_at = null;
        }
        $user->fill($validated);
        $user->date_of_joining = $this->date_of_joining ?: null;
        $user->save();

        $this->profileSaved = true;
        $this->dispatch('toast', type: 'success', message: 'Profile updated successfully.');
        $this->profileSaved = false;
    }

    public function saveMedia(): void
    {
        $this->validate([
            'profilePhoto'   => 'nullable|image|max:2048',
            'signatureImage' => 'nullable|image|max:2048',
        ]);

        $user = Auth::user();

        if ($this->profilePhoto) {
            if ($this->existingPhoto) {
                Storage::disk('public')->delete($this->existingPhoto);
            }
            $path = $this->profilePhoto->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
            $this->existingPhoto = $path;
            $this->profilePhoto  = null;
        }

        if ($this->signatureImage) {
            if ($this->existingSignature) {
                Storage::disk('public')->delete($this->existingSignature);
            }
            $path = $this->signatureImage->store('signatures', 'public');
            $user->signature_path    = $path;
            $this->existingSignature = $path;
            $this->signatureImage    = null;
        }

        $user->save();
        $this->photoSaved = true;
        $this->dispatch('toast', type: 'success', message: 'Profile photo & signature updated.');
        $this->photoSaved = false;
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password'            => 'required',
            'new_password'                => ['required', 'confirmed', Rules\Password::defaults()],
            'new_password_confirmation'   => 'required',
        ]);

        $user = Auth::user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Current password is incorrect.');
            return;
        }

        $user->password = Hash::make($this->new_password);
        $user->save();

        $this->current_password            = '';
        $this->new_password                = '';
        $this->new_password_confirmation   = '';
        $this->passwordSaved = true;
        $this->dispatch('toast', type: 'success', message: 'Password changed successfully.');
        $this->passwordSaved = false;
    }

    public function removePhoto(): void
    {
        $user = Auth::user();
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
            $user->save();
            $this->existingPhoto = '';
        }
        $this->dispatch('toast', type: 'success', message: 'Profile photo removed.');
    }

    public function removeSignature(): void
    {
        $user = Auth::user();
        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
            $user->signature_path = null;
            $user->save();
            $this->existingSignature = '';
        }
        $this->dispatch('toast', type: 'success', message: 'Signature removed.');
    }
}; ?>

<div class="min-h-screen" style="background: #f0f4f8;">

    <style>
        .profile-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .tab-btn { padding: 8px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
                   color: #64748b; transition: all .2s; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #fff; color: #6366f1; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .tab-btn:not(.active):hover { background: rgba(255,255,255,0.5); color: #475569; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 5px; }
        .form-input { width: 100%; padding: 10px 12px; font-size: 13.5px; border: 1.5px solid #e2e8f0;
                      border-radius: 10px; outline: none; transition: border-color .2s, box-shadow .2s;
                      background: #fff; color: #1e293b; }
        .form-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
        .form-select { width: 100%; padding: 10px 12px; font-size: 13.5px; border: 1.5px solid #e2e8f0;
                       border-radius: 10px; outline: none; transition: border-color .2s; background: #fff; color: #1e293b; }
        .form-select:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border: 1px solid #f1f5f9; }
        .save-btn { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border: none;
                    padding: 10px 28px; border-radius: 10px; font-size: 13.5px; font-weight: 600;
                    cursor: pointer; transition: all .2s; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
        .save-btn:hover { box-shadow: 0 4px 16px rgba(99,102,241,0.45); transform: translateY(-1px); }
        .badge-dept { background: #eef2ff; color: #4338ca; font-size: 11px; font-weight: 600;
                      padding: 3px 10px; border-radius: 99px; }
        .upload-zone { border: 2px dashed #c7d2fe; border-radius: 12px; padding: 20px;
                       text-align: center; cursor: pointer; transition: all .2s; }
        .upload-zone:hover { border-color: #6366f1; background: #eef2ff30; }
        .info-row { display: flex; justify-content: space-between; align-items: center;
                    padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .info-row:last-child { border-bottom: none; }
    </style>

    {{-- ═══ PROFILE HERO ═══ --}}
    <div class="profile-hero relative overflow-hidden">
        <div class="absolute inset-0 opacity-10"
             style="background: url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 80 80%22><circle cx=%2240%22 cy=%2240%22 r=%2240%22 fill=%22%23fff%22/></svg>') center/cover;"></div>
        <div class="max-w-5xl mx-auto px-6 pt-8 pb-20 relative">
            <div class="flex items-start gap-6">
                {{-- Avatar --}}
                <div class="relative">
                    <div class="w-24 h-24 rounded-2xl overflow-hidden shadow-xl flex-shrink-0"
                         style="background: linear-gradient(135deg,#fff3,#fff1); border: 3px solid rgba(255,255,255,0.35);">
                        @if($existingPhoto)
                            <img src="{{ asset('storage/'.$existingPhoto) }}"
                                 class="w-full h-full object-cover" alt="Profile Photo">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-white font-bold text-3xl">
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr(auth()->user()->name ?? ' ', ' '), 1, 1)) }}
                            </div>
                        @endif
                    </div>
                </div>
                {{-- Info --}}
                <div class="flex-1 pt-1">
                    <h1 class="text-2xl font-bold text-white">{{ auth()->user()->name }}</h1>
                    @if(auth()->user()->job_title || auth()->user()->department)
                    <p class="text-white/75 text-sm mt-0.5">
                        {{ auth()->user()->job_title }}
                        @if(auth()->user()->job_title && auth()->user()->department) · @endif
                        {{ auth()->user()->department }}
                    </p>
                    @endif
                    <div class="flex items-center flex-wrap gap-3 mt-3">
                        <span class="flex items-center gap-1.5 text-xs text-white/70">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            {{ auth()->user()->email }}
                        </span>
                        @if(auth()->user()->phone)
                        <span class="flex items-center gap-1.5 text-xs text-white/70">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ auth()->user()->phone }}
                        </span>
                        @endif
                        @if(auth()->user()->location)
                        <span class="flex items-center gap-1.5 text-xs text-white/70">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ auth()->user()->location }}
                        </span>
                        @endif
                        <span class="badge-dept" style="background:rgba(255,255,255,0.2); color:#fff;">
                            {{ auth()->user()->getRoleNames()->first() ?? 'User' }}
                        </span>
                        @if(auth()->user()->employee_id)
                        <span class="text-xs font-mono text-white/60 bg-white/10 px-2 py-0.5 rounded">
                            {{ auth()->user()->employee_id }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TABS BAR ═══ --}}
    <div class="max-w-5xl mx-auto px-6 -mt-10 relative z-10">
        <div class="flex items-center gap-1 p-1 rounded-xl shadow-sm" style="background: #f1f5f9;">
            @foreach([
                ['overview',  '👤', 'My Profile'],
                ['edit',      '✏️',  'Edit Info'],
                ['media',     '🖼️',  'Photo & Signature'],
                ['security',  '🔒', 'Security'],
                ['activity',  '📋', 'Activity'],
            ] as [$tab, $icon, $label])
            <button wire:click="$set('activeTab','{{ $tab }}')"
                    class="tab-btn {{ $activeTab === $tab ? 'active' : '' }}">
                {{ $icon }} {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ═══ TAB CONTENT ═══ --}}
    <div class="max-w-5xl mx-auto px-6 py-6 space-y-5">

        {{-- ── OVERVIEW TAB ── --}}
        @if($activeTab === 'overview')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Left card --}}
            <div class="card p-5 flex flex-col items-center text-center">
                <div class="w-20 h-20 rounded-2xl overflow-hidden mb-3 shadow-md"
                     style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                    @if($existingPhoto)
                        <img src="{{ asset('storage/'.$existingPhoto) }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-white font-bold text-2xl">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif
                </div>
                <h3 class="font-bold text-gray-800 text-base">{{ auth()->user()->name }}</h3>
                <p class="text-sm text-indigo-600 mt-0.5">{{ auth()->user()->job_title ?: 'No Title Set' }}</p>
                <span class="mt-2 badge-dept">{{ auth()->user()->getRoleNames()->first() ?? 'User' }}</span>
                @if($existingSignature)
                <div class="mt-4 w-full border-t pt-4">
                    <p class="text-xs text-gray-400 mb-2">Digital Signature</p>
                    <img src="{{ asset('storage/'.$existingSignature) }}" class="max-h-12 mx-auto object-contain" alt="Signature">
                </div>
                @endif
                <div class="mt-4 w-full space-y-1 text-sm">
                    <div class="flex items-center gap-2 text-gray-500">
                        <svg class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-xs">Joined {{ auth()->user()->date_of_joining ? auth()->user()->date_of_joining->format('M Y') : auth()->user()->created_at->format('M Y') }}</span>
                    </div>
                    @if(auth()->user()->last_login_at)
                    <div class="flex items-center gap-2 text-gray-500">
                        <svg class="w-3.5 h-3.5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        <span class="text-xs">Last login {{ auth()->user()->last_login_at->diffForHumans() }}</span>
                    </div>
                    @endif
                </div>
                <button wire:click="$set('activeTab','edit')"
                        class="mt-4 w-full save-btn text-sm py-2">
                    Edit Profile
                </button>
            </div>

            {{-- Right: Details --}}
            <div class="md:col-span-2 space-y-4">
                <div class="card p-5">
                    <h4 class="text-sm font-bold text-gray-700 mb-3">Personal Information</h4>
                    <div class="grid grid-cols-2 gap-0">
                        @foreach([
                            ['Full Name',    auth()->user()->name],
                            ['Email',        auth()->user()->email],
                            ['Phone',        auth()->user()->phone ?: '—'],
                            ['Location',     auth()->user()->location ?: '—'],
                            ['Language',     auth()->user()->language ? strtoupper(auth()->user()->language) : '—'],
                            ['Timezone',     auth()->user()->timezone ?: '—'],
                        ] as [$label, $val])
                        <div class="info-row pr-4">
                            <span class="text-xs text-gray-400 font-medium">{{ $label }}</span>
                            <span class="text-sm text-gray-700 font-medium text-right max-w-[60%] truncate">{{ $val }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="card p-5">
                    <h4 class="text-sm font-bold text-gray-700 mb-3">Work Information</h4>
                    <div class="grid grid-cols-2 gap-0">
                        @foreach([
                            ['Job Title',   auth()->user()->job_title ?: '—'],
                            ['Department',  auth()->user()->department ?: '—'],
                            ['Employee ID', auth()->user()->employee_id ?: '—'],
                            ['Date Joined', auth()->user()->date_of_joining ? auth()->user()->date_of_joining->format('d M Y') : '—'],
                        ] as [$label, $val])
                        <div class="info-row pr-4">
                            <span class="text-xs text-gray-400 font-medium">{{ $label }}</span>
                            <span class="text-sm text-gray-700 font-medium text-right">{{ $val }}</span>
                        </div>
                        @endforeach
                    </div>
                    @if(auth()->user()->bio)
                    <div class="mt-3 pt-3 border-t border-gray-50">
                        <span class="text-xs text-gray-400 font-medium">Bio</span>
                        <p class="text-sm text-gray-700 mt-1 leading-relaxed">{{ auth()->user()->bio }}</p>
                    </div>
                    @endif
                    @if(auth()->user()->linkedin_url)
                    <div class="mt-3 pt-3 border-t border-gray-50 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        <a href="{{ auth()->user()->linkedin_url }}" target="_blank"
                           class="text-sm text-blue-600 hover:underline truncate">LinkedIn Profile</a>
                    </div>
                    @endif
                </div>

                @if(auth()->user()->emergency_contact_name)
                <div class="card p-5">
                    <h4 class="text-sm font-bold text-gray-700 mb-3">Emergency Contact</h4>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->emergency_contact_name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ auth()->user()->emergency_contact_phone ?: '—' }}</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── EDIT INFO TAB ── --}}
        @if($activeTab === 'edit')
        <form wire:submit="saveProfile" class="space-y-5">
            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Personal Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="form-label">Full Name <span class="text-red-500">*</span></label>
                        <input wire:model="name" type="text" class="form-input" placeholder="John Doe">
                        @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="form-label">Email Address <span class="text-red-500">*</span></label>
                        <input wire:model="email" type="email" class="form-input">
                        @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="form-label">Phone Number</label>
                        <input wire:model="phone" type="tel" class="form-input" placeholder="+971 50 000 0000"></div>
                    <div><label class="form-label">Location / City</label>
                        <input wire:model="location" type="text" class="form-input" placeholder="Dubai, UAE"></div>
                    <div><label class="form-label">LinkedIn URL</label>
                        <input wire:model="linkedin_url" type="url" class="form-input" placeholder="https://linkedin.com/in/...">
                        @error('linkedin_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="form-label">Timezone</label>
                        <select wire:model="timezone" class="form-select">
                            <option value="UTC">UTC</option>
                            <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                            <option value="Asia/Riyadh">Asia/Riyadh (UTC+3)</option>
                            <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                            <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                            <option value="Europe/London">Europe/London</option>
                            <option value="America/New_York">America/New York</option>
                        </select></div>
                    <div><label class="form-label">Language</label>
                        <select wire:model="language" class="form-select">
                            @foreach(['en'=>'English','ar'=>'Arabic','fr'=>'French','de'=>'German','es'=>'Spanish','hi'=>'Hindi','ur'=>'Urdu'] as $code => $lang)
                                <option value="{{ $code }}">{{ $lang }}</option>
                            @endforeach
                        </select></div>
                </div>
                <div class="mt-4"><label class="form-label">Bio / About</label>
                    <textarea wire:model="bio" rows="3" class="form-input" placeholder="A brief professional summary about yourself…"></textarea></div>
            </div>

            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Work Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="form-label">Job Title</label>
                        <input wire:model="job_title" type="text" class="form-input" placeholder="Senior Manager"></div>
                    <div><label class="form-label">Department</label>
                        <select wire:model="department" class="form-select">
                            <option value="">-- Select Department --</option>
                            @foreach(['Operations','Finance','Procurement','Sales','Logistics & Fleet','IT / Technology','Human Resources','Legal & Compliance','Marketing','Executive Management'] as $dept)
                                <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="form-label">Employee ID</label>
                        <input wire:model="employee_id" type="text" class="form-input" placeholder="EMP-0001"></div>
                    <div><label class="form-label">Date of Joining</label>
                        <input wire:model="date_of_joining" type="date" class="form-input">
                        @error('date_of_joining') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                </div>
            </div>

            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    Emergency Contact
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="form-label">Contact Name</label>
                        <input wire:model="emergency_contact_name" type="text" class="form-input" placeholder="Jane Doe"></div>
                    <div><label class="form-label">Contact Phone</label>
                        <input wire:model="emergency_contact_phone" type="tel" class="form-input" placeholder="+971 55 000 0000"></div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="save-btn" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveProfile">💾 Save Profile</span>
                    <span wire:loading wire:target="saveProfile">Saving…</span>
                </button>
            </div>
        </form>
        @endif

        {{-- ── MEDIA TAB ── --}}
        @if($activeTab === 'media')
        <div class="space-y-5">
            {{-- Profile Photo --}}
            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4">Profile Photo</h4>
                <div class="flex items-start gap-6">
                    <div class="w-28 h-28 rounded-2xl overflow-hidden flex-shrink-0 shadow-lg"
                         style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        @if($profilePhoto)
                            <img src="{{ $profilePhoto->temporaryUrl() }}" class="w-full h-full object-cover">
                        @elseif($existingPhoto)
                            <img src="{{ asset('storage/'.$existingPhoto) }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-white text-3xl font-bold">
                                {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <label for="prof-photo" class="cursor-pointer">
                            <div class="upload-zone">
                                <svg class="w-8 h-8 mx-auto mb-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-sm font-semibold text-indigo-600">Click to upload new photo</p>
                                <p class="text-xs text-gray-400 mt-1">PNG, JPG up to 2MB</p>
                            </div>
                        </label>
                        <input id="prof-photo" wire:model="profilePhoto" type="file" accept="image/*" class="hidden">
                        @error('profilePhoto') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
                        @if($existingPhoto)
                        <button wire:click="removePhoto" wire:confirm="Remove your profile photo?"
                                class="mt-3 text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Remove current photo
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Digital Signature --}}
            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4">Digital Signature</h4>
                <div class="flex items-start gap-6">
                    <div class="w-44 h-24 rounded-xl overflow-hidden flex-shrink-0 border border-gray-100 flex items-center justify-center bg-gray-50">
                        @if($signatureImage)
                            <img src="{{ $signatureImage->temporaryUrl() }}" class="max-w-full max-h-full object-contain">
                        @elseif($existingSignature)
                            <img src="{{ asset('storage/'.$existingSignature) }}" class="max-w-full max-h-full object-contain">
                        @else
                            <p class="text-xs text-gray-400 text-center px-4">No signature uploaded</p>
                        @endif
                    </div>
                    <div class="flex-1">
                        <label for="sig-image" class="cursor-pointer">
                            <div class="upload-zone" style="border-color: #d8b4fe;">
                                <svg class="w-8 h-8 mx-auto mb-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <p class="text-sm font-semibold text-purple-600">Upload signature image</p>
                                <p class="text-xs text-gray-400 mt-1">PNG with transparent background recommended</p>
                            </div>
                        </label>
                        <input id="sig-image" wire:model="signatureImage" type="file" accept="image/*" class="hidden">
                        @error('signatureImage') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
                        @if($existingSignature)
                        <button wire:click="removeSignature" wire:confirm="Remove your digital signature?"
                                class="mt-3 text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Remove signature
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button wire:click="saveMedia" class="save-btn" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveMedia">🖼️ Save Media</span>
                    <span wire:loading wire:target="saveMedia">Uploading…</span>
                </button>
            </div>
        </div>
        @endif

        {{-- ── SECURITY TAB ── --}}
        @if($activeTab === 'security')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Change Password
                </h4>
                <form wire:submit="changePassword" class="space-y-3">
                    <div><label class="form-label">Current Password</label>
                        <input wire:model="current_password" type="password" class="form-input" placeholder="Enter current password">
                        @error('current_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="form-label">New Password</label>
                        <input wire:model="new_password" type="password" class="form-input" placeholder="Min 8 characters">
                        @error('new_password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror</div>
                    <div><label class="form-label">Confirm New Password</label>
                        <input wire:model="new_password_confirmation" type="password" class="form-input" placeholder="Repeat new password"></div>
                    <button type="submit" class="save-btn w-full" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="changePassword">🔐 Change Password</span>
                        <span wire:loading wire:target="changePassword">Updating…</span>
                    </button>
                </form>
            </div>

            <div class="card p-6">
                <h4 class="text-sm font-bold text-gray-700 mb-4">Account Security Info</h4>
                <div class="space-y-3">
                    @foreach([
                        ['Account Status', ucfirst(auth()->user()->status ?? 'active'), auth()->user()->status === 'active' ? '#dcfce7' : '#fee2e2', auth()->user()->status === 'active' ? '#16a34a' : '#dc2626'],
                        ['Email Verified', auth()->user()->email_verified_at ? 'Verified ✅' : 'Not Verified ❌', auth()->user()->email_verified_at ? '#dcfce7' : '#fee2e2', auth()->user()->email_verified_at ? '#16a34a' : '#dc2626'],
                        ['2FA Status', auth()->user()->two_factor_enabled ? 'Enabled' : 'Disabled', auth()->user()->two_factor_enabled ? '#dcfce7' : '#f3f4f6', auth()->user()->two_factor_enabled ? '#16a34a' : '#6b7280'],
                    ] as [$label, $val, $bg, $color])
                    <div class="flex items-center justify-between p-3 rounded-xl" style="background: {{ $bg }}15;">
                        <span class="text-sm text-gray-600">{{ $label }}</span>
                        <span class="text-sm font-semibold" style="color: {{ $color }};">{{ $val }}</span>
                    </div>
                    @endforeach
                    @if(auth()->user()->last_login_at)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50">
                        <span class="text-sm text-gray-600">Last Login</span>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-700">{{ auth()->user()->last_login_at->format('d M Y, H:i') }}</p>
                            @if(auth()->user()->last_login_ip)
                            <p class="text-xs text-gray-400 font-mono">{{ auth()->user()->last_login_ip }}</p>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── ACTIVITY TAB ── --}}
        @if($activeTab === 'activity')
        <div class="card p-6">
            <h4 class="text-sm font-bold text-gray-700 mb-4">Account Activity</h4>
            <div class="space-y-3">
                @foreach([
                    ['Account Created', auth()->user()->created_at->format('d M Y, H:i'), 'bg-blue-50', 'text-blue-600', '🏁'],
                    ['Profile Last Updated', auth()->user()->updated_at->format('d M Y, H:i'), 'bg-indigo-50', 'text-indigo-600', '✏️'],
                    auth()->user()->last_login_at ? ['Last Login', auth()->user()->last_login_at->format('d M Y, H:i') . (auth()->user()->last_login_ip ? ' from ' . auth()->user()->last_login_ip : ''), 'bg-green-50', 'text-green-600', '🔐'] : null,
                    auth()->user()->date_of_joining ? ['Date of Joining', auth()->user()->date_of_joining->format('d M Y'), 'bg-amber-50', 'text-amber-600', '📅'] : null,
                ] as $item)
                @if($item)
                <div class="flex items-center gap-4 p-3 rounded-xl {{ $item[2] }}">
                    <span class="text-xl">{{ $item[4] }}</span>
                    <div>
                        <p class="text-sm font-semibold {{ $item[3] }}">{{ $item[0] }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $item[1] }}</p>
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            <div class="mt-6 pt-5 border-t">
                <h5 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">My Assigned Role & Permissions</h5>
                @php $role = auth()->user()->roles->first(); @endphp
                @if($role)
                <div class="flex items-center gap-3 mb-4 p-3 rounded-xl bg-indigo-50">
                    <div class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center">
                        <svg class="w-4.5 h-4.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-indigo-700">{{ $role->name }}</p>
                        <p class="text-xs text-indigo-500">{{ $role->permissions->count() }} permissions</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach(auth()->user()->getAllPermissions()->take(15) as $perm)
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-lg">{{ $perm->name }}</span>
                    @endforeach
                    @if(auth()->user()->getAllPermissions()->count() > 15)
                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded-lg">+{{ auth()->user()->getAllPermissions()->count() - 15 }} more</span>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>
