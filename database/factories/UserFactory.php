<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => Role::Teacher,
            'mobile' => fake()->unique()->numerify('017########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an admin (signs in with a mobile number).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
        ]);
    }

    /**
     * Indicate that the user is an admission officer (signs in with a mobile number).
     */
    public function admissionOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::AdmissionOfficer,
        ]);
    }

    /**
     * Indicate that the user is a teacher (signs in with a mobile number).
     */
    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Teacher,
        ]);
    }

    /**
     * Indicate that the user is a student, with a linked student record
     * (students sign in with students.student_id).
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Student,
            'mobile' => null,
        ])->has(Student::factory(), 'student');
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}
}
