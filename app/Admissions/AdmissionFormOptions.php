<?php

namespace App\Admissions;

use App\Enums\BloodGroup;
use App\Enums\EducationLevel;
use App\Enums\ExamType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Batch;
use App\Models\Department;
use App\Models\EducationBoard;
use App\Models\Hall;
use App\Models\Program;
use App\Models\Quota;
use Illuminate\Support\Collection;

/**
 * Choices shown on the admission form (online and staff).
 */
final class AdmissionFormOptions
{
    public const DIVISIONS = ['Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Sylhet'];

    public const MARITAL_STATUSES = ['single', 'married'];

    /** @var array<string, string> */
    public const PAYMENT_METHODS = ['bkash' => 'bKash', 'bank' => 'Bank deposit / draft', 'cash' => 'Cash at office'];

    /**
     * @return array<string, mixed>
     */
    public static function for(bool $staff): array
    {
        $options = [
            'programs' => Department::with(['programs' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->orderBy('code')
                ->get()
                ->filter(fn (Department $department) => $department->programs->isNotEmpty())
                ->map(fn (Department $department) => [
                    'department' => $department->name,
                    'programs' => $department->programs->map(fn (Program $program) => ['id' => $program->id, 'label' => $program->name])->values(),
                ])
                ->values(),
            'quotas' => Quota::orderBy('code')->get()->map(fn (Quota $quota) => ['id' => $quota->id, 'label' => $quota->name]),
            'boards' => EducationBoard::orderBy('name')->get()->map(fn (EducationBoard $board) => ['id' => $board->id, 'label' => $board->name]),
            'exams' => collect(EducationLevel::cases())->mapWithKeys(fn (EducationLevel $level) => [
                $level->value => collect($level->exams())->map(fn (ExamType $exam) => ['id' => $exam->value, 'label' => $exam->label()]),
            ]),
            'genders' => self::enum(Gender::cases()),
            'religions' => self::enum(Religion::cases()),
            'blood_groups' => collect(BloodGroup::cases())->map(fn (BloodGroup $group) => ['id' => $group->value, 'label' => $group->value]),
            'marital_statuses' => collect(self::MARITAL_STATUSES)->map(fn (string $status) => ['id' => $status, 'label' => ucfirst($status)]),
            'divisions' => collect(self::DIVISIONS)->map(fn (string $division) => ['id' => $division, 'label' => $division]),
            'payment_methods' => collect(self::PAYMENT_METHODS)->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])->values(),
        ];

        if ($staff) {
            $options['batches'] = Batch::orderByDesc('number')->limit(6)->get()->map(fn (Batch $batch) => ['id' => $batch->id, 'label' => $batch->label]);
            $options['halls'] = self::halls();
        }

        return $options;
    }

    /**
     * @return Collection<int, array{id: int, label: non-falsy-string}>
     */
    public static function halls(): Collection
    {
        return Hall::orderBy('code')->get()->map(fn (Hall $hall) => ['id' => $hall->id, 'label' => "{$hall->code} — {$hall->name}"]);
    }

    /**
     * @param  list<Gender|Religion>  $cases
     * @return Collection<int, array{id: string, label: string}>
     */
    private static function enum(array $cases): Collection
    {
        return collect($cases)->map(fn ($case) => ['id' => $case->value, 'label' => $case->label()]);
    }
}
