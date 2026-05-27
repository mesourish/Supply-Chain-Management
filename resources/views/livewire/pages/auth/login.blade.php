<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $showPassword = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: true));
    }

    public function togglePassword(): void
    {
        $this->showPassword = !$this->showPassword;
    }
}; ?>

<div>
    <form wire:submit="login" class="space-y-5">

        {{-- Email --}}
        <div>
            <label class="auth-label" for="email">Email address</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4" style="color: rgba(255,255,255,0.35);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <input wire:model="form.email"
                       id="email" type="email" name="email"
                       placeholder="you@company.com"
                       required autofocus autocomplete="username"
                       class="auth-input pl-11">
            </div>
            @error('form.email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label class="auth-label" for="password">Password</label>
            <div class="relative" x-data="{ show: false }">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4" style="color: rgba(255,255,255,0.35);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input wire:model="form.password"
                       id="password" name="password"
                       :type="show ? 'text' : 'password'"
                       placeholder="••••••••••"
                       required autocomplete="current-password"
                       class="auth-input pl-11 pr-11">
                {{-- Toggle visibility --}}
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 transition-colors"
                        style="color: rgba(255,255,255,0.3);"
                        x-bind:style="show ? 'color: rgba(255,255,255,0.7)' : ''">
                    <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                    </svg>
                </button>
            </div>
            @error('form.password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember + Forgot Row --}}
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input wire:model="form.remember"
                       id="remember" type="checkbox" name="remember"
                       class="auth-checkbox rounded">
                <span class="text-xs font-medium" style="color: rgba(255,255,255,0.55);">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-xs font-semibold transition-colors"
                   style="color: #818cf8;"
                   onmouseover="this.style.color='#a5b4fc'"
                   onmouseout="this.style.color='#818cf8'">
                    Forgot password?
                </a>
            @endif
        </div>

        {{-- Submit Button --}}
        <button type="submit" class="btn-login" wire:loading.attr="disabled">
            <span class="relative z-10 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="login">Sign In</span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Signing in…
                </span>
            </span>
        </button>

    </form>
</div>
