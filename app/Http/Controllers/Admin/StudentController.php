<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Hall;
use App\Models\Quota;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Filterable, paginated list of students.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer'],
            'program' => ['nullable', 'integer'],
            'batch' => ['nullable', 'integer'],
            'hall' => ['nullable', 'string', 'max:10'], // hall id, or "none"
            'quota' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(StudentStatus::class)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);

        $students = $this->filtered($filters)
            ->with(['program:id,department_id,code,name', 'program.department:id,code', 'batch:id,number,name,admission_year,semester', 'hall:id,code,name', 'quota:id,code,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('batch_id')
            ->orderBy('student_id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return Inertia::render('admin/students/index', [
            'students' => $students->through(fn (Student $student) => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name_en,
                'department' => $student->program->department->code,
                'program' => $student->program->name,
                'batch' => $student->batch->label,
                'hall' => $student->hall?->name,
                'quota' => $student->quota?->code,
                'gender' => $student->gender?->label(),
                'mobile' => $student->mobile,
                'status' => $student->status->value,
                'status_label' => $student->status->label(),
            ]),
            'filters' => (object) array_filter($filters, fn ($value) => $value !== null),
            // Students per status under every other active filter (for the status tabs).
            'statusCounts' => $this->filtered($filters)
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'options' => [
                'departments' => Department::with('programs:id,department_id,name')->orderBy('code')->get(['id', 'code', 'name'])
                    ->map(fn (Department $department) => [
                        'id' => $department->id,
                        'label' => "{$department->code} — {$department->name}",
                        'programs' => $department->programs->map(fn ($program) => ['id' => $program->id, 'label' => $program->name])->values(),
                    ]),
                'batches' => Batch::orderByDesc('number')->get()->map(fn (Batch $batch) => ['id' => $batch->id, 'label' => $batch->label]),
                'halls' => Hall::orderBy('code')->get(['id', 'name'])->map(fn (Hall $hall) => ['id' => $hall->id, 'label' => $hall->name]),
                'quotas' => Quota::orderBy('code')->get(['id', 'code', 'name'])->map(fn (Quota $quota) => ['id' => $quota->id, 'label' => "{$quota->code} — {$quota->name}"]),
                'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => ['id' => $status->value, 'label' => $status->label()]),
                'genders' => collect(Gender::cases())->map(fn (Gender $gender) => ['id' => $gender->value, 'label' => $gender->label()]),
            ],
        ]);
    }

    /**
     * Students matching every filter except status.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Student>
     */
    private function filtered(array $filters): Builder
    {
        return Student::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('student_id', 'like', "{$search}%")
                ->orWhere('registration_no', 'like', "{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")))
            ->when($filters['department'] ?? null, fn (Builder $query, int $department) => $query->whereRelation('program', 'department_id', $department))
            ->when($filters['program'] ?? null, fn (Builder $query, int $program) => $query->where('program_id', $program))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $batch) => $query->where('batch_id', $batch))
            ->when($filters['hall'] ?? null, fn (Builder $query, string $hall) => $hall === 'none'
                ? $query->whereNull('hall_id')
                : $query->where('hall_id', (int) $hall))
            ->when($filters['quota'] ?? null, fn (Builder $query, int $quota) => $query->where('quota_id', $quota))
            ->when($filters['gender'] ?? null, fn (Builder $query, string $gender) => $query->where('gender', $gender));
    }

    /**
     * Full profile of one student.
     */
    public function show(Student $student): Response
    {
        $student->load(['program.department.faculty', 'batch', 'hall', 'quota', 'user:id,email_verified_at,password', 'guardians', 'addresses', 'educations.board', 'statusChanges']);

        return Inertia::render('admin/students/show', [
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'registration_no' => $student->registration_no,
                'name_en' => $student->name_en,
                'name_bn' => $student->name_bn,
                'status' => $student->status->value,
                'status_label' => $student->status->label(),
                'status_history' => $student->statusChanges->map(fn ($change) => [
                    'id' => $change->id,
                    'from' => $change->from_status?->label(),
                    'to' => $change->to_status->label(),
                    'to_value' => $change->to_status->value,
                    'reason' => $change->reason,
                    'remarks' => $change->remarks !== $change->reason ? $change->remarks : null,
                    'by' => $change->changed_by,
                    'at' => $change->changed_at->format('d M Y, h:i A'),
                    'source' => $change->source,
                ]),
                'faculty' => $student->program->department->faculty->name,
                'department' => $student->program->department->name,
                'program' => $student->program->name,
                'batch' => $student->batch->label,
                'hall' => $student->hall?->name,
                'quota' => $student->quota ? "{$student->quota->code} — {$student->quota->name}" : null,
                'gender' => $student->gender?->label(),
                'date_of_birth' => $student->date_of_birth?->format('d M Y'),
                'blood_group' => $student->blood_group?->value,
                'religion' => $student->religion?->label(),
                'nationality' => $student->nationality,
                'nid' => $student->nid,
                'mobile' => $student->mobile,
                'email' => $student->email,
                'has_password' => $student->user?->password !== null,
                'guardians' => $student->guardians->map(fn ($guardian) => [
                    'relation' => $guardian->relation->label(),
                    'name' => $guardian->name,
                    'mobile' => $guardian->mobile,
                    'occupation' => $guardian->occupation,
                    'is_primary_contact' => $guardian->is_primary_contact,
                ]),
                'addresses' => $student->addresses->map(fn ($address) => [
                    'type' => $address->type->label(),
                    'text' => collect([$address->address_line, $address->post_office ? "{$address->post_office}-{$address->post_code}" : null, $address->upazila, $address->district, $address->division])
                        ->filter()->implode(', ') ?: $address->raw_text,
                ]),
                'educations' => $student->educations->map(fn ($education) => [
                    'exam' => $education->exam->label(),
                    'year' => $education->passing_year,
                    'board' => $education->board?->name,
                    'institute' => $education->institute,
                    'roll' => $education->roll,
                    'gpa' => $education->gpa,
                ]),
            ],
        ]);
    }
}
