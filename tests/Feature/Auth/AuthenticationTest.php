<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

test('login screen can be rendered', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('dashboard can be rendered for an authorized user', function () {
    $user = User::factory()->withRole('Executive')->create();

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertOk();
});

test('users without dashboard permission are forbidden from the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/dashboard')->assertForbidden();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
