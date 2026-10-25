<?php

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\UserSeeder;

test('the seeder creates the admin and teacher accounts and is idempotent', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::count())->toBe(2)
        ->and(User::firstWhere('mobile', '01725215111')->role)->toBe(Role::Admin)
        ->and(User::firstWhere('mobile', '01725215112')->role)->toBe(Role::Teacher);
});

test('the seeder gives the imported demo student a known password', function () {
    $student = Student::factory()->create(['student_id' => UserSeeder::DEMO_STUDENT_ID]);

    $this->seed(UserSeeder::class);

    expect($student->refresh()->user)->not->toBeNull()
        ->and($student->user->role)->toBe(Role::Student);

    $this->post(route('login.store'), ['login' => UserSeeder::DEMO_STUDENT_ID, 'password' => 'password123']);

    $this->assertAuthenticatedAs($student->user);
});

test('seeded staff can sign in with their mobile number', function (string $mobile) {
    $this->seed(UserSeeder::class);

    $this->post(route('login.store'), ['login' => $mobile, 'password' => 'password123']);

    $this->assertAuthenticated();
})->with(['admin' => '01725215111', 'teacher' => '01725215112']);
