<?php

namespace Database\Factories;

use App\Enums\AddressType;
use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Enums\EducationLevel;
use App\Enums\ExamType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An online application awaiting verification, SSC 4.50 + HSC 4.50 = 9.00.
 *
 * @extends Factory<AdmissionApplication>
 */
class AdmissionApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => AdmissionSource::Online,
            'status' => AdmissionStatus::Submitted,
            'batch_id' => Batch::factory(),
            'program_id' => Program::factory(),
            'name_en' => mb_strtoupper(fake()->name()),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-22 years', '-18 years'),
            'religion' => Religion::Islam,
            'nationality' => 'Bangladeshi',
            'nid' => fake()->numerify('##########'),
            'mobile' => fake()->numerify('017########'),
            'father_name' => mb_strtoupper(fake()->name('male')),
            'mother_name' => mb_strtoupper(fake()->name('female')),
            'gpa_total' => 9.00,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AdmissionApplication $application) {
            $application->forceFill(['application_no' => sprintf('ADM%02d%05d', $application->batch->number % 100, $application->id)])->save();

            $gpa = number_format((float) $application->gpa_total / 2, 2);

            $application->educations()->createMany([
                ['level' => EducationLevel::Secondary, 'exam' => ExamType::Ssc, 'passing_year' => 2022, 'roll' => fake()->numerify('######'), 'gpa' => $gpa],
                ['level' => EducationLevel::HigherSecondary, 'exam' => ExamType::Hsc, 'passing_year' => 2024, 'roll' => fake()->numerify('######'), 'gpa' => $gpa],
            ]);

            $application->addresses()->createMany([
                ['type' => AddressType::Present, 'address_line' => 'Saidpur Cantonment', 'upazila' => 'Saidpur', 'district' => 'Nilphamari', 'division' => 'Rangpur'],
                ['type' => AddressType::Permanent, 'address_line' => 'Village Road', 'upazila' => 'Saidpur', 'district' => 'Nilphamari', 'division' => 'Rangpur'],
            ]);
        });
    }

    /**
     * SSC + HSC total below the admission test threshold.
     */
    public function lowGpa(float $total = 7.5): static
    {
        return $this->state(fn (array $attributes) => ['gpa_total' => $total]);
    }

    /**
     * Verified, with the status the GPA rule gives.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => (float) $attributes['gpa_total'] < (float) config('admission.test_gpa_threshold')
                ? AdmissionStatus::TestRequired
                : AdmissionStatus::Eligible,
        ])->afterCreating(fn (AdmissionApplication $application) => $application->forceFill(['verified_at' => now()])->save());
    }
}
