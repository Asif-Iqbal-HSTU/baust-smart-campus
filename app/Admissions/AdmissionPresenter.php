<?php

namespace App\Admissions;

use App\Enums\AddressType;
use App\Enums\EducationLevel;
use App\Models\Address;
use App\Models\AdmissionApplication;
use App\Models\AdmissionApplicationEducation;
use App\Models\Student;

/**
 * Shapes an application for the Inertia pages.
 */
final class AdmissionPresenter
{
    /**
     * Values to pre-fill the edit form with.
     *
     * @return array<string, mixed>
     */
    public static function form(AdmissionApplication $application): array
    {
        $application->loadMissing(['educations', 'addresses']);

        $address = fn (AddressType $type) => $application->addresses->firstWhere('type', $type);
        $result = fn (EducationLevel $level) => $application->educations->firstWhere('level', $level);

        return [
            ...$application->only([
                'batch_id', 'program_id', 'quota_id', 'hall_id', 'name_en', 'name_bn', 'nationality', 'marital_status',
                'nid', 'birth_registration_no', 'mobile', 'email', 'father_name', 'father_mobile', 'father_occupation',
                'mother_name', 'mother_mobile', 'mother_occupation', 'guardian_name', 'guardian_relation', 'guardian_mobile',
                'payment_method', 'payment_reference', 'remarks',
            ]),
            'gender' => $application->gender->value,
            'date_of_birth' => $application->date_of_birth->toDateString(),
            'blood_group' => $application->blood_group?->value,
            'religion' => $application->religion?->value,
            'present' => self::addressForm($address(AddressType::Present)),
            'permanent' => self::addressForm($address(AddressType::Permanent)),
            'secondary' => self::resultForm($result(EducationLevel::Secondary)),
            'higher_secondary' => self::resultForm($result(EducationLevel::HigherSecondary)),
        ];
    }

    /**
     * Everything shown on the application page.
     *
     * @return array<string, mixed>
     */
    public static function detail(AdmissionApplication $application): array
    {
        $application->loadMissing([
            'batch', 'program.department', 'quota', 'hall', 'student', 'educations.board', 'addresses', 'documents',
            'verifier:id,name', 'testRecorder:id,name', 'rejecter:id,name', 'admitter:id,name',
        ]);

        return [
            'id' => $application->id,
            'application_no' => $application->application_no,
            'source' => $application->source->value,
            'source_label' => $application->source->label(),
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'submitted_at' => $application->created_at?->format('d M Y, h:i A'),
            'batch' => $application->batch->label,
            'department' => $application->program->department->name,
            'program' => $application->program->name,
            'quota' => $application->quota?->name,
            'hall_id' => $application->hall_id,
            'hall' => $application->hall?->name,
            'name_en' => $application->name_en,
            'name_bn' => $application->name_bn,
            'gender' => $application->gender->label(),
            'date_of_birth' => $application->date_of_birth->format('d M Y'),
            'blood_group' => $application->blood_group?->value,
            'religion' => $application->religion?->label(),
            'nationality' => $application->nationality,
            'marital_status' => $application->marital_status ? ucfirst($application->marital_status) : null,
            'nid' => $application->nid,
            'birth_registration_no' => $application->birth_registration_no,
            'mobile' => $application->mobile,
            'email' => $application->email,
            'has_photo' => $application->photo_path !== null,
            'guardians' => array_filter([
                ['relation' => 'Father', 'name' => $application->father_name, 'mobile' => $application->father_mobile, 'occupation' => $application->father_occupation],
                ['relation' => 'Mother', 'name' => $application->mother_name, 'mobile' => $application->mother_mobile, 'occupation' => $application->mother_occupation],
                $application->guardian_name
                    ? ['relation' => 'Local guardian ('.$application->guardian_relation.')', 'name' => $application->guardian_name, 'mobile' => $application->guardian_mobile, 'occupation' => null]
                    : null,
            ]),
            'addresses' => $application->addresses->map(fn (Address $address) => [
                'type' => $address->type->label(),
                'text' => collect([$address->address_line, $address->post_office ? trim("{$address->post_office} {$address->post_code}") : $address->post_code, $address->upazila, $address->district, $address->division])
                    ->filter()->implode(', '),
            ]),
            'educations' => $application->educations->sortBy(fn (AdmissionApplicationEducation $education) => $education->level === EducationLevel::Secondary ? 0 : 1)->values()
                ->map(fn (AdmissionApplicationEducation $education) => [
                    'level' => $education->level->label(),
                    'exam' => $education->exam->label(),
                    'year' => $education->passing_year,
                    'board' => $education->board?->name,
                    'institute' => $education->institute,
                    'group' => $education->group,
                    'roll' => $education->roll,
                    'registration_no' => $education->registration_no,
                    'gpa' => $education->gpa,
                    'gpa_scale' => $education->gpa_scale,
                ]),
            'gpa_total' => $application->gpa_total,
            'needs_test' => $application->needsAdmissionTest(),
            'documents' => $application->documents->map(fn ($document) => ['id' => $document->id, 'name' => $document->name, 'file' => $document->original_name]),
            'payment_method' => $application->payment_method ? (AdmissionFormOptions::PAYMENT_METHODS[$application->payment_method] ?? $application->payment_method) : null,
            'payment_reference' => $application->payment_reference,
            'remarks' => $application->remarks,
            'verified' => $application->verified_at ? ['at' => $application->verified_at->format('d M Y, h:i A'), 'by' => $application->verifier?->name] : null,
            'test' => $application->test_recorded_at ? [
                'score' => $application->test_score,
                'passed' => $application->test_passed,
                'at' => $application->test_recorded_at->format('d M Y, h:i A'),
                'by' => $application->testRecorder?->name,
            ] : null,
            'rejected' => $application->rejected_at ? [
                'reason' => $application->rejection_reason,
                'at' => $application->rejected_at->format('d M Y, h:i A'),
                'by' => $application->rejecter?->name,
            ] : null,
            'admitted' => $application->admitted_at ? [
                'student_id' => $application->student?->student_id,
                'at' => $application->admitted_at->format('d M Y, h:i A'),
                'by' => $application->admitter?->name,
            ] : null,
        ];
    }

    /**
     * Current students with the same HSC roll, year and board: a sign the
     * applicant is already enrolled (e.g. re-admission or a second application).
     *
     * @return array<int, array{student_id: string, name: string, status: string}>
     */
    public static function matchingStudents(AdmissionApplication $application): array
    {
        $hsc = $application->educations->firstWhere('level', EducationLevel::HigherSecondary);

        if ($hsc === null) {
            return [];
        }

        return Student::query()
            ->when($application->student_id, fn ($query, int $id) => $query->whereKeyNot($id))
            ->whereHas('educations', fn ($query) => $query
                ->whereIn('exam', array_map(fn ($exam) => $exam->value, EducationLevel::HigherSecondary->exams()))
                ->where('roll', $hsc->roll)
                ->where('passing_year', $hsc->passing_year)
                ->where('education_board_id', $hsc->education_board_id))
            ->limit(5)
            ->get()
            ->map(fn (Student $student) => ['student_id' => $student->student_id, 'name' => $student->name_en, 'status' => $student->status->label()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private static function addressForm(?Address $address): array
    {
        return [
            'address_line' => $address?->address_line,
            'post_office' => $address?->post_office,
            'post_code' => $address?->post_code,
            'upazila' => $address?->upazila,
            'district' => $address?->district,
            'division' => $address?->division,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resultForm(?AdmissionApplicationEducation $education): array
    {
        return [
            'exam' => $education?->exam->value,
            'board_id' => $education?->education_board_id,
            'passing_year' => $education?->passing_year,
            'roll' => $education?->roll,
            'registration_no' => $education?->registration_no,
            'gpa' => $education?->gpa,
            'institute' => $education?->institute,
            'group' => $education?->group,
        ];
    }
}
