<?php

namespace App\Http\Requests\Admissions;

use App\Admissions\AdmissionFormOptions;
use App\Enums\AdmissionStatus;
use App\Enums\BloodGroup;
use App\Enums\EducationLevel;
use App\Enums\ExamType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The admission form, used by applicants online (apply.store) and by
 * admission staff entering a paper form or correcting an application.
 */
class AdmissionApplicationRequest extends FormRequest
{
    private const MOBILE = '/^01[3-9]\d{8}$/';

    private const MOBILE_FIELDS = ['mobile', 'father_mobile', 'mother_mobile', 'guardian_mobile'];

    /**
     * Accept numbers as people write them (+880 1712-345678) by normalizing first.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::MOBILE_FIELDS)
            ->filter(fn (string $field) => is_string($this->input($field)) && $this->input($field) !== '')
            ->mapWithKeys(fn (string $field) => [$field => User::normalizeMobile($this->input($field))])
            ->all());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $online = $this->isOnline();
        $editing = $this->editedApplication() !== null;

        return [
            'batch_id' => $online ? ['prohibited'] : ['required', 'integer', Rule::exists('batches', 'id')],
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')->where('is_active', true)],
            'quota_id' => ['nullable', 'integer', Rule::exists('quotas', 'id')],
            'hall_id' => $online ? ['prohibited'] : ['nullable', 'integer', Rule::exists('halls', 'id')],

            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['nullable', 'string', 'max:150'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'religion' => ['required', Rule::enum(Religion::class)],
            'nationality' => ['required', 'string', 'max:50'],
            'marital_status' => ['nullable', Rule::in(AdmissionFormOptions::MARITAL_STATUSES)],
            'nid' => ['nullable', 'required_without:birth_registration_no', 'string', 'max:20'],
            'birth_registration_no' => ['nullable', 'required_without:nid', 'string', 'max:30'],
            'mobile' => ['required', 'string', 'regex:'.self::MOBILE],
            'email' => ['nullable', 'email', 'max:150'],

            'father_name' => ['required', 'string', 'max:150'],
            'father_mobile' => ['nullable', 'string', 'regex:'.self::MOBILE],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['required', 'string', 'max:150'],
            'mother_mobile' => ['nullable', 'string', 'regex:'.self::MOBILE],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_relation' => ['nullable', 'required_with:guardian_name', 'string', 'max:50'],
            'guardian_mobile' => ['nullable', 'required_with:guardian_name', 'string', 'regex:'.self::MOBILE],

            ...$this->addressRules('present'),
            ...$this->addressRules('permanent'),
            ...$this->resultRules(EducationLevel::Secondary),
            ...$this->resultRules(EducationLevel::HigherSecondary),

            'photo' => [$online && ! $editing ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'documents' => ['nullable', 'array', 'max:6'],
            'documents.*.name' => ['required', 'string', 'max:60'],
            'documents.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],

            'payment_method' => ['nullable', Rule::in(array_keys(AdmissionFormOptions::PAYMENT_METHODS))],
            'payment_reference' => ['nullable', 'required_with:payment_method', 'string', 'max:60'],
            'remarks' => $online ? ['prohibited'] : ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid Bangladeshi mobile number, e.g. 01712345678.',
            '*.regex' => 'Enter a valid Bangladeshi mobile number.',
            'nid.required_without' => 'Give the NID or the birth registration number.',
            'birth_registration_no.required_without' => 'Give the birth registration number or the NID.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_en' => 'name',
            'program_id' => 'program',
            'batch_id' => 'batch',
            'secondary.gpa' => 'SSC GPA',
            'secondary.roll' => 'SSC roll',
            'secondary.board_id' => 'SSC board',
            'secondary.passing_year' => 'SSC passing year',
            'higher_secondary.gpa' => 'HSC GPA',
            'higher_secondary.roll' => 'HSC roll',
            'higher_secondary.board_id' => 'HSC board',
            'higher_secondary.passing_year' => 'HSC passing year',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->isOnline() && Batch::openForAdmission() === null) {
                $validator->errors()->add('application', 'Online admission is closed at the moment.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $this->input('higher_secondary.passing_year') <= (int) $this->input('secondary.passing_year')) {
                $validator->errors()->add('higher_secondary.passing_year', 'HSC must be passed after SSC.');
            }

            foreach (EducationLevel::cases() as $level) {
                if ($this->input("{$level->value}.exam") === ExamType::Diploma->value && (float) $this->input("{$level->value}.gpa") > 4) {
                    $validator->errors()->add("{$level->value}.gpa", 'Diploma CGPA is out of 4.00.');
                }
            }

            $duplicate = $this->sameHscApplication();
            if ($duplicate !== null) {
                $validator->errors()->add('higher_secondary.roll', "An application with this HSC roll already exists for this batch ({$duplicate->application_no}).");
            }
        }];
    }

    /**
     * Applicants apply to the open intake; staff choose the batch.
     */
    public function batchId(): int
    {
        return $this->isOnline()
            ? (int) Batch::openForAdmission()?->id
            : (int) $this->integer('batch_id');
    }

    private function isOnline(): bool
    {
        return $this->routeIs('apply.store');
    }

    private function editedApplication(): ?AdmissionApplication
    {
        $application = $this->route('application');

        return $application instanceof AdmissionApplication ? $application : null;
    }

    /**
     * Another live application in the same batch with the same HSC roll, year and board.
     */
    private function sameHscApplication(): ?AdmissionApplication
    {
        $batchId = $this->editedApplication()->batch_id ?? $this->batchId();

        return AdmissionApplication::query()
            ->where('batch_id', $batchId)
            ->where('status', '!=', AdmissionStatus::Rejected)
            ->when($this->editedApplication(), fn ($query, AdmissionApplication $current) => $query->whereKeyNot($current->id))
            ->whereHas('higherSecondary', fn ($query) => $query
                ->where('roll', $this->input('higher_secondary.roll'))
                ->where('passing_year', $this->input('higher_secondary.passing_year'))
                ->where('education_board_id', $this->input('higher_secondary.board_id')))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function addressRules(string $type): array
    {
        return [
            "{$type}.address_line" => ['required', 'string', 'max:255'],
            "{$type}.post_office" => ['nullable', 'string', 'max:100'],
            "{$type}.post_code" => ['nullable', 'digits:4'],
            "{$type}.upazila" => ['required', 'string', 'max:100'],
            "{$type}.district" => ['required', 'string', 'max:100'],
            "{$type}.division" => ['required', Rule::in(AdmissionFormOptions::DIVISIONS)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultRules(EducationLevel $level): array
    {
        $exams = array_map(fn (ExamType $exam) => $exam->value, $level->exams());
        $key = $level->value;

        return [
            "{$key}.exam" => ['required', Rule::in($exams)],
            "{$key}.board_id" => ['nullable', "required_unless:{$key}.exam,o_level,a_level", 'integer', Rule::exists('education_boards', 'id')],
            "{$key}.passing_year" => ['required', 'integer', 'min:1990', 'max:'.now()->year],
            "{$key}.roll" => ['required', 'string', 'max:20'],
            "{$key}.registration_no" => ['nullable', 'string', 'max:30'],
            "{$key}.gpa" => ['required', 'numeric', 'min:1', 'max:5'],
            "{$key}.institute" => ['nullable', 'string', 'max:200'],
            "{$key}.group" => ['nullable', 'string', 'max:30'],
        ];
    }
}
