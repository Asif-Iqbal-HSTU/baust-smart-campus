<?php

namespace App\Imports\Students;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves mapped rows. Matching is on the student ID, so the import can be
 * re-run: imported fields, guardians, addresses and exam results are
 * replaced with the values from the file.
 */
final class StudentImporter
{
    /**
     * @param  array{student: array<string, mixed>, guardians: list<array<string, mixed>>, addresses: list<array<string, mixed>>, educations: list<array<string, mixed>>}  $mapped
     * @return bool true when the student was newly created
     */
    public function save(array $mapped): bool
    {
        return DB::transaction(function () use ($mapped): bool {
            $attributes = $mapped['student'];

            $student = Student::withTrashed()->firstOrNew(['student_id' => $attributes['student_id']]);
            $created = ! $student->exists;
            $student->fill($attributes)->save();

            $this->syncAccount($student);

            $student->guardians()->delete();
            $student->guardians()->createMany($mapped['guardians']);

            $student->addresses()->delete();
            $student->addresses()->createMany($mapped['addresses']);

            foreach ($mapped['educations'] as $education) {
                $student->educations()->updateOrCreate(['exam' => $education['exam']], $education);
            }

            return $created;
        });
    }

    /**
     * Every student gets a login account (role student). Imported accounts have
     * no password, so nobody can sign in until one is issued.
     */
    private function syncAccount(Student $student): void
    {
        $user = $student->user ?? new User(['role' => Role::Student]);
        $user->name = $student->name_en;
        $user->save();

        if ($student->user_id !== $user->id) {
            $student->user()->associate($user)->save();
        }
    }
}
