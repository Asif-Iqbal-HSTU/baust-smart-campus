<?php

namespace App\Admissions;

use App\Models\Batch;
use App\Models\Hall;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

/**
 * Issues the next student ID (class roll):
 * 080 | 26 | 1 | 02 | 05101 | 001 = university | year | semester | hall | subject | serial.
 *
 * The serial counts per year + semester + subject across every hall (the
 * existing IDs follow that rule), so the hall digits never decide the number.
 */
final class StudentIdGenerator
{
    /**
     * Must run inside a database transaction: the programs sharing the subject
     * code are locked so two admissions can never draw the same serial.
     */
    public function next(Batch $batch, Program $program, Hall $hall): string
    {
        if ($batch->semester === null) {
            throw ValidationException::withMessages(['application' => "Batch {$batch->name} has no semester, so a student ID cannot be issued."]);
        }

        if ($program->subject_code === null) {
            throw ValidationException::withMessages(['application' => "{$program->name} has no subject code, so a student ID cannot be issued."]);
        }

        Program::where('subject_code', $program->subject_code)->lockForUpdate()->get(['id']);

        $prefix = config('admission.university_code')
            .str_pad((string) ($batch->admission_year % 100), 2, '0', STR_PAD_LEFT)
            .$batch->semester->value;

        // "_" matches exactly one character, so this only sees 16-digit IDs of this intake + subject.
        $last = Student::withTrashed()
            ->where('student_id', 'like', $prefix.'__'.$program->subject_code.'___')
            ->pluck('student_id')
            ->map(fn (string $id) => (int) substr($id, -3))
            ->max() ?? 0;

        if ($last >= 999) {
            throw ValidationException::withMessages(['application' => "No serials left for {$program->name} in this intake."]);
        }

        return $prefix.$hall->code.$program->subject_code.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
