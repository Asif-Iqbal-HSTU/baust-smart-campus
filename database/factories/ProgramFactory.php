<?php

namespace Database\Factories;

use App\Enums\DegreeLevel;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => fake()->unique()->lexify('P-????'),
            'name' => 'B.Sc. in '.fake()->unique()->bothify('Subject ####'),
            'subject_code' => fake()->unique()->numerify('9####'),
            'degree_level' => DegreeLevel::Undergraduate,
            'duration_years' => 4,
        ];
    }
}
