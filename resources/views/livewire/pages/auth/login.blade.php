<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $showPassword = false;

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: true));
    }
}; ?>

<div>
    <form wire:submit="login" class="space-y-5">

        {{-- Email --}}
        <div>
            <label class="label-standard" for="email">Email Address</label>
            <input wire:model="form.email"
                   id="email" type="email" name="email"
                   placeholder="name@company.com"
                   required autofocus autocomplete="username"
                   class="input-standard">
            @error('form.email')
                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <div class="flex items-center justify-between mb-[0.375rem]">
                <label class="label-standard mb-0" for="password">Password</label>
            </div>
            <input wire:model="form.password"
                   id="password" name="password"
                   type="password"
                   placeholder="••••••••••••"
                   required autocomplete="current-password"
                   class="input-standard">
            @error('form.password')
                <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember --}}
        <div class="flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" class="checkbox-custom">
            <label for="remember" class="ml-2 block text-sm text-gray-700 font-medium cursor-pointer">
                Remember me for 30 days
            </label>
        </div>

        {{-- Submit Button --}}
        <div class="pt-4">
            <button type="submit" class="btn-primary group" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Authenticating...
                </span>
            </button>
        </div>

    </form>
</div>
