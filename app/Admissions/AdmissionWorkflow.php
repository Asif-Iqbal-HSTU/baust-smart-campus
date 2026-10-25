<?php

namespace App\Admissions;

use App\Enums\AddressType;
use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Enums\EducationLevel;
use App\Enums\ExamType;
use App\Enums\GuardianRelation;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AdmissionApplication;
use App\Models\Hall;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Every step an application goes through, from submission to a student record.
 *
 *  online:  submit → verify → (admission test if SSC + HSC GPA < threshold) → admit
 *  office:  submit + verify at once (staff saw the paper form) → test if needed → admit
 */
final class AdmissionWorkflow
{
    /** Application columns taken straight from the validated form. */
    private const FIELDS = [
        'batch_id', 'program_id', 'quota_id', 'hall_id',
        'name_en', 'name_bn', 'gender', 'date_of_birth', 'blood_group', 'religion', 'nationality',
        'marital_status', 'nid', 'birth_registration_no', 'mobile', 'email',
        'father_name', 'father_mobile', 'father_occupation', 'mother_name', 'mother_mobile', 'mother_occupation',
        'guardian_name', 'guardian_relation', 'guardian_mobile', 'payment_method', 'payment_reference', 'remarks',
    ];

    public function __construct(private StudentIdGenerator $ids) {}

    /**
     * Record a new application. Office entries are verified by the staff member
     * who keyed them in; online ones wait for verification.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data, AdmissionSource $source, ?User $staff = null): AdmissionApplication
    {
        return DB::transaction(function () use ($data, $source, $staff) {
            $application = new AdmissionApplication([
                'source' => $source,
                'status' => AdmissionStatus::Submitted,
            ]);

            $this->fill($application, $data);

            $application->application_no = sprintf('ADM%02d%05d', $application->batch->number, $application->id);
            $application->save();

            if ($source === AdmissionSource::Office && $staff !== null) {
                $this->verify($application, $staff);
            }

            return $application;
        });
    }

    /**
     * Correct an open application. A verified one is re-checked against the
     * GPA rule, since the results may have changed.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(AdmissionApplication $application, array $data): void
    {
        $this->ensureStatus($application, AdmissionStatus::Submitted, AdmissionStatus::TestRequired, AdmissionStatus::Eligible);

        DB::transaction(function () use ($application, $data) {
            $this->fill($application, $data);

            if ($application->verified_at !== null) {
                $application->update(['status' => $this->statusAfterVerification($application)]);
            }
        });
    }

    /**
     * Staff confirm the applicant's details and papers. The GPA decides whether
     * an admission test is needed.
     */
    public function verify(AdmissionApplication $application, User $staff): void
    {
        $this->ensureStatus($application, AdmissionStatus::Submitted);

        $application->forceFill([
            'verified_at' => now(),
            'verified_by' => $staff->id,
        ]);
        $application->status = $this->statusAfterVerification($application);
        $application->save();
    }

    /**
     * Passing the admission test makes the applicant ready to admit; failing rejects the application.
     */
    public function recordTestResult(AdmissionApplication $application, ?float $score, bool $passed, User $staff): void
    {
        $this->ensureStatus($application, AdmissionStatus::TestRequired);

        $application->forceFill([
            'test_score' => $score,
            'test_passed' => $passed,
            'test_recorded_at' => now(),
            'test_recorded_by' => $staff->id,
        ]);

        if ($passed) {
            $application->status = AdmissionStatus::Eligible;
            $application->save();

            return;
        }

        $application->save();
        $this->reject($application, 'Did not pass the admission test'.($score !== null ? " (score {$score})." : '.'), $staff);
    }

    /**
     * Remove an application (e.g. a test entry) with its uploads. If it was
     * admitted, the student it created goes too — record, login, guardians,
     * addresses, results and history — so the student ID serial is free again.
     */
    public function delete(AdmissionApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $student = $application->student()->withTrashed()->first();

            $application->addresses()->delete();
            $application->delete();

            if ($student !== null) {
                $user = $student->user;

                $student->addresses()->delete();
                $student->forceDelete(); // guardians, results and status history cascade
                $user?->delete();
            }
        });

        Storage::disk('local')->deleteDirectory("admissions/{$application->id}");
    }

    public function reject(AdmissionApplication $application, string $reason, User $staff): void
    {
        $this->ensureStatus($application, AdmissionStatus::Submitted, AdmissionStatus::TestRequired, AdmissionStatus::Eligible);

        $application->forceFill([
            'status' => AdmissionStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'rejected_by' => $staff->id,
        ])->save();
    }

    /**
     * Create the student (with login account, guardians, addresses and
     * results) and issue the student ID.
     */
    public function admit(AdmissionApplication $application, Hall $hall, User $staff): Student
    {
        return DB::transaction(function () use ($application, $hall, $staff) {
            // Re-read under lock so a double click cannot admit the same applicant twice.
            $application = AdmissionApplication::lockForUpdate()->findOrFail($application->id);
            $this->ensureStatus($application, AdmissionStatus::Eligible);
            $application->load(['batch', 'program', 'educations', 'addresses']);

            $student = Student::create([
                'student_id' => $this->ids->next($application->batch, $application->program, $hall),
                'program_id' => $application->program_id,
                'batch_id' => $application->batch_id,
                'quota_id' => $application->quota_id,
                'hall_id' => $hall->id,
                'enrollment_type' => 'general',
                'name_en' => $application->name_en,
                'name_bn' => $application->name_bn,
                'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth,
                'blood_group' => $application->blood_group,
                'religion' => $application->religion,
                'nationality' => $application->nationality,
                'marital_status' => $application->marital_status,
                'nid' => $application->nid,
                'mobile' => $application->mobile,
                'email' => $application->email,
                'photo_path' => $application->photo_path,
                'status' => StudentStatus::Active,
            ]);

            // Login account; like imported students it has no password until one is issued.
            $user = User::create(['name' => $student->name_en, 'role' => Role::Student]);
            $student->user()->associate($user)->save();

            $student->guardians()->createMany(array_filter([
                ['relation' => GuardianRelation::Father, 'name' => $application->father_name, 'mobile' => $application->father_mobile, 'occupation' => $application->father_occupation, 'is_primary_contact' => $application->guardian_name === null],
                ['relation' => GuardianRelation::Mother, 'name' => $application->mother_name, 'mobile' => $application->mother_mobile, 'occupation' => $application->mother_occupation, 'is_primary_contact' => false],
                $application->guardian_name !== null
                    ? ['relation' => GuardianRelation::Guardian, 'name' => $application->guardian_name, 'mobile' => $application->guardian_mobile, 'occupation' => $application->guardian_relation, 'is_primary_contact' => true]
                    : null,
            ]));

            foreach ($application->addresses as $address) {
                $student->addresses()->create($address->only($address->getFillable()));
            }

            foreach ($application->educations as $education) {
                $student->educations()->create([
                    'exam' => $education->exam,
                    'passing_year' => $education->passing_year,
                    'education_board_id' => $education->education_board_id,
                    'institute' => $education->institute,
                    'group' => $education->group,
                    'roll' => $education->roll,
                    'registration_no' => $education->registration_no,
                    'gpa' => $education->gpa,
                    'gpa_scale' => $education->gpa_scale,
                ]);
            }

            $student->statusChanges()->create([
                'from_status' => null,
                'to_status' => StudentStatus::Active,
                'reason' => "Admitted through application {$application->application_no} ({$application->source->label()}).",
                'changed_by' => $staff->name,
                'changed_by_user_id' => $staff->id,
                'changed_at' => now(),
                'source' => 'admission',
            ]);

            $application->forceFill([
                'status' => AdmissionStatus::Admitted,
                'hall_id' => $hall->id,
                'student_id' => $student->id,
                'admitted_at' => now(),
                'admitted_by' => $staff->id,
            ])->save();

            return $student;
        });
    }

    /**
     * Ready to admit when the GPA clears the threshold or the test was passed.
     */
    private function statusAfterVerification(AdmissionApplication $application): AdmissionStatus
    {
        return $application->test_passed === true || ! $application->needsAdmissionTest()
            ? AdmissionStatus::Eligible
            : AdmissionStatus::TestRequired;
    }

    private function ensureStatus(AdmissionApplication $application, AdmissionStatus ...$allowed): void
    {
        if (! in_array($application->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'application' => "This application is already \"{$application->status->label()}\".",
            ]);
        }
    }

    /**
     * Save the form data, results, addresses and uploads of an application.
     *
     * @param  array<string, mixed>  $data
     */
    private function fill(AdmissionApplication $application, array $data): void
    {
        $application->fill(Arr::only($data, array_filter(self::FIELDS, fn (string $field) => array_key_exists($field, $data))));

        foreach (['mobile', 'father_mobile', 'mother_mobile', 'guardian_mobile'] as $field) {
            if ($application->{$field} !== null) {
                $application->{$field} = User::normalizeMobile($application->{$field});
            }
        }

        $application->gpa_total = number_format((float) $data['secondary']['gpa'] + (float) $data['higher_secondary']['gpa'], 2, '.', '');
        $application->save();

        foreach (EducationLevel::cases() as $level) {
            $result = $data[$level->value];
            $exam = ExamType::from($result['exam']);

            $application->educations()->updateOrCreate(['level' => $level], [
                'exam' => $exam,
                'passing_year' => $result['passing_year'],
                'education_board_id' => $result['board_id'] ?? null,
                'institute' => $result['institute'] ?? null,
                'group' => $result['group'] ?? null,
                'roll' => $result['roll'],
                'registration_no' => $result['registration_no'] ?? null,
                'gpa' => $result['gpa'],
                'gpa_scale' => $exam === ExamType::Diploma ? 4.0 : 5.0,
            ]);
        }

        foreach ([AddressType::Present, AddressType::Permanent] as $type) {
            $address = $data[$type->value];

            $application->addresses()->updateOrCreate(['type' => $type], [
                'address_line' => $address['address_line'],
                'post_office' => $address['post_office'] ?? null,
                'post_code' => $address['post_code'] ?? null,
                'upazila' => $address['upazila'],
                'district' => $address['district'],
                'division' => $address['division'],
                'country' => 'Bangladesh',
            ]);
        }

        if (($data['photo'] ?? null) instanceof UploadedFile) {
            $old = $application->photo_path;
            $application->update(['photo_path' => $data['photo']->store("admissions/{$application->id}", 'local')]);

            // Keep the old file if an admitted student still points at it.
            if ($old !== null && ! Student::where('photo_path', $old)->exists()) {
                Storage::disk('local')->delete($old);
            }
        }

        foreach ($data['documents'] ?? [] as $document) {
            /** @var UploadedFile $file */
            $file = $document['file'];

            $application->documents()->create([
                'name' => $document['name'],
                'path' => $file->store("admissions/{$application->id}/documents", 'local'),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }
}
