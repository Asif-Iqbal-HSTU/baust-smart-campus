<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('admins can authenticate with their mobile number', function () {
    $user = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->mobile,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('teachers can authenticate with their mobile number', function () {
    $user = User::factory()->teacher()->create();

    $this->post(route('login.store'), [
        'login' => $user->mobile,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('mobile numbers are accepted in international format', function () {
    $user = User::factory()->teacher()->create(['mobile' => '01725215111']);

    $this->post(route('login.store'), [
        'login' => '+880 1725-215111',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('students can authenticate with their student id', function () {
    $user = User::factory()->student()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->student->student_id,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('students without an issued password can not authenticate', function () {
    $user = User::factory()->student()->create(['password' => null]);

    $this->post(route('login.store'), [
        'login' => $user->student->student_id,
        'password' => '',
    ]);

    $this->assertGuest();
});

test('students can not authenticate with a mobile number', function () {
    $user = User::factory()->student()->create(['mobile' => '01811111111']);

    $this->post(route('login.store'), [
        'login' => '01811111111',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('admins and teachers can not authenticate with a student id', function () {
    User::factory()->admin()
        ->has(Student::factory()->state(['student_id' => '0802410104089999']), 'student')
        ->create();

    $this->post(route('login.store'), [
        'login' => '0802410104089999',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate with their email address', function () {
    $user = User::factory()->teacher()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'login' => $user->mobile,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->mobile,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->mobile, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'login' => $user->mobile,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
