<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fake()->unique()->numerify('08026101####1###'),
            'program_id' => Program::factory(),
            'batch_id' => Batch::factory(),
            'name_en' => mb_strtoupper(fake()->name()),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-25 years', '-17 years'),
            'mobile' => fake()->numerify('017########'),
            'status' => StudentStatus::Active,
        ];
    }
}
