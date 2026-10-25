<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Initial accounts. Safe to run repeatedly (matched on the login
 * identifier). Change these passwords before going live.
 */
class UserSeeder extends Seeder
{
    /**
     * Demo student account; the student record itself comes from the student import.
     */
    public const DEMO_STUDENT_ID = '0802410104081005';

    public function run(): void
    {
        User::updateOrCreate(['mobile' => '01725215111'], [
            'name' => 'Administrator',
            'role' => Role::Admin,
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['mobile' => '01725215112'], [
            'name' => 'Teacher',
            'role' => Role::Teacher,
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);

        $this->seedDemoStudentAccount();
    }

    /**
     * Give the demo student a known password, once their record exists.
     */
    private function seedDemoStudentAccount(): void
    {
        $student = Student::firstWhere('student_id', self::DEMO_STUDENT_ID);

        if (! $student) {
            $this->command->warn('Demo student '.self::DEMO_STUDENT_ID.' not found; run students:import first.');

            return;
        }

        $user = $student->user ?? new User(['role' => Role::Student]);
        $user->fill([
            'name' => $student->name_en,
            'password' => 'password123',
        ]);
        $user->email_verified_at ??= now();
        $user->save();

        $student->user()->associate($user)->save();
    }
}
