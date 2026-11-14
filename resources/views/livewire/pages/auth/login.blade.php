<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="auth-layout">
    <section class="auth-intro" aria-labelledby="auth-brand">
        <a class="auth-brand" href="/" wire:navigate id="auth-brand">
            <span class="auth-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <path d="M5 10.5 16 5l11 5.5v11L16 27 5 21.5v-11Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                    <path d="m5.5 10.8 10.5 5.4 10.5-5.4M16 16.3V27M10.5 8l11 5.5v5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            </span>
            <span>{{ config('app.name', 'Inventory Management') }}</span>
        </a>

        <div class="auth-intro-copy">
            <p class="auth-eyebrow">{{ __('Procurement & inventory, in control') }}</p>
            <h1>{{ __('Make every move count.') }}</h1>
            <p class="auth-intro-description">{{ __('A clearer view of your inventory, from receiving to the moment it reaches the right hands.') }}</p>
        </div>

        <div class="auth-preview" aria-hidden="true">
            <div class="auth-preview-top">
                <span class="auth-preview-label">{{ __('Inventory overview') }}</span>
                <span class="auth-preview-indicator"><span></span>{{ __('Up to date') }}</span>
            </div>
            <div class="auth-preview-chart">
                <div class="auth-preview-chart-label">{{ __('Stock movement') }}</div>
                <div class="auth-chart-bars">
                    <span style="--bar-height: 36%"></span>
                    <span style="--bar-height: 52%"></span>
                    <span style="--bar-height: 43%"></span>
                    <span style="--bar-height: 72%"></span>
                    <span style="--bar-height: 58%"></span>
                    <span style="--bar-height: 86%"></span>
                    <span style="--bar-height: 68%"></span>
                    <span style="--bar-height: 100%"></span>
                    <span style="--bar-height: 77%"></span>
                    <span style="--bar-height: 91%"></span>
                    <span style="--bar-height: 73%"></span>
                    <span style="--bar-height: 96%"></span>
                </div>
                <div class="auth-chart-axis"><span>{{ __('Mon') }}</span><span>{{ __('Wed') }}</span><span>{{ __('Fri') }}</span><span>{{ __('Sun') }}</span></div>
            </div>
            <div class="auth-preview-footer">
                <span class="auth-preview-icon">
                    <svg viewBox="0 0 20 20" fill="none"><path d="M3 6.5 10 3l7 3.5v7L10 17l-7-3.5v-7Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m3.5 6.8 6.5 3.3 6.5-3.3M10 10v6.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                </span>
                <span><strong>{{ __('One connected view') }}</strong><small>{{ __('Stock · Orders · Operations') }}</small></span>
                <svg class="auth-preview-arrow" viewBox="0 0 20 20" fill="none"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>

        <p class="auth-intro-caption">{{ __('The right information, right where you need it.') }}</p>
    </section>

    <section class="auth-form-panel" aria-labelledby="auth-heading">
        <div class="auth-form-wrap">
            <div class="auth-form-heading">
                <span class="auth-mobile-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none"><path d="M5 10.5 16 5l11 5.5v11L16 27 5 21.5v-11Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m5.5 10.8 10.5 5.4 10.5-5.4M16 16.3V27" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                </span>
                <p class="auth-form-kicker">{{ __('Welcome back') }}</p>
                <h2 id="auth-heading">{{ __('Sign in to your account') }}</h2>
                <p class="auth-form-description">{{ __('Enter your details below to continue.') }}</p>
            </div>

            <x-auth-session-status class="auth-session-status" :status="session('status')" />

            <form class="auth-form" wire:submit="login">
                <div class="auth-field">
                    <label class="auth-label" for="email">{{ __('Email address') }}</label>
                    <x-text-input wire:model="form.email" id="email" class="auth-input" type="email" name="email" required autofocus autocomplete="username" placeholder="name@company.com" />
                    <x-input-error :messages="$errors->get('form.email')" class="auth-error" />
                </div>

                <div class="auth-field">
                    <div class="auth-password-label">
                        <label class="auth-label" for="password">{{ __('Password') }}</label>
                        @if (Route::has('password.request'))
                            <a class="auth-link" href="{{ route('password.request') }}" wire:navigate>{{ __('Forgot password?') }}</a>
                        @endif
                    </div>
                    <x-text-input wire:model="form.password" id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" placeholder="{{ __('Enter your password') }}" />
                    <x-input-error :messages="$errors->get('form.password')" class="auth-error" />
                </div>

                <label class="auth-remember" for="remember">
                    <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
                    <span>{{ __('Remember me') }}</span>
                </label>

                <button class="auth-submit" type="submit" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">{{ __('Sign in') }}</span>
                    <span wire:loading wire:target="login">{{ __('Signing in...') }}</span>
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </form>

            <p class="auth-security-note">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><path d="M10 2.8 4 5.2v4.4c0 3.7 2.5 6.3 6 7.6 3.5-1.3 6-3.9 6-7.6V5.2l-6-2.4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m7.5 9.8 1.7 1.7 3.5-3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('Your workspace is protected and secure.') }}
            </p>
        </div>

        <p class="auth-form-footer">{{ __('Need access? Contact your system administrator.') }}</p>
    </section>
</div>
