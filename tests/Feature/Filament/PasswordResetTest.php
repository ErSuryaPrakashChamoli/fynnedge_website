<?php

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('sends a password reset link for an existing admin', function () {
    Notification::fake();
    $user = User::factory()->create(['is_admin' => true, 'email' => 'admin@fynnedge.test']);

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'admin@fynnedge.test'])
        ->call('request');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('does not reveal whether an email belongs to an account', function () {
    Notification::fake();
    User::factory()->create(['is_admin' => true, 'email' => 'real-admin@fynnedge.test']);

    $forReal = Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'real-admin@fynnedge.test'])
        ->call('request');

    $forFake = Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => 'no-such-admin@fynnedge.test'])
        ->call('request');

    $forReal->assertHasNoFormErrors();
    $forFake->assertHasNoFormErrors();
});

it('resets the password with a valid token, and the admin can log in with the new one', function () {
    $user = User::factory()->create(['is_admin' => true, 'password' => Hash::make('old-password-123')]);
    $token = Password::broker('users')->createToken($user);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->set('password', 'new-strong-password-123')
        ->set('passwordConfirmation', 'new-strong-password-123')
        ->call('resetPassword');

    expect(Hash::check('new-strong-password-123', $user->fresh()->password))->toBeTrue();
    expect(Auth::attempt(['email' => $user->email, 'password' => 'new-strong-password-123']))->toBeTrue();
});

it('rejects an invalid reset token and leaves the password unchanged', function () {
    $user = User::factory()->create(['is_admin' => true, 'password' => Hash::make('old-password-123')]);

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => 'not-a-real-token'])
        ->set('password', 'new-strong-password-123')
        ->set('passwordConfirmation', 'new-strong-password-123')
        ->call('resetPassword');

    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects an expired reset token and leaves the password unchanged', function () {
    $user = User::factory()->create(['is_admin' => true, 'password' => Hash::make('old-password-123')]);
    $token = Password::broker('users')->createToken($user);

    Carbon::setTestNow(now()->addMinutes(config('auth.passwords.users.expire') + 5));

    Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
        ->set('password', 'new-strong-password-123')
        ->set('passwordConfirmation', 'new-strong-password-123')
        ->call('resetPassword');

    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();
});
