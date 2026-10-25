<?php

namespace Database\Factories;

use App\Enums\Semester;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'number' => $number,
            'name' => Batch::ordinal($number),
            'admission_year' => 2026,
            'semester' => Semester::Winter,
        ];
    }
}
