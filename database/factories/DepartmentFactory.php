<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'code' => fake()->unique()->lexify('D???'),
            'name' => 'Department of '.fake()->unique()->word(),
        ];
    }
}
