<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Enums\BloodGroup;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\Religion;
use Carbon\CarbonImmutable;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * An application for admission, made online by the applicant or entered by
 * admission staff from a paper form. It becomes a Student only when admitted.
 *
 * @property int $id
 * @property string|null $application_no e.g. ADM2400012 (batch 24, application 12)
 * @property AdmissionSource $source
 * @property AdmissionStatus $status
 * @property int $batch_id
 * @property int $program_id
 * @property int|null $quota_id
 * @property int|null $hall_id
 * @property string $name_en
 * @property string|null $name_bn
 * @property Gender $gender
 * @property CarbonImmutable $date_of_birth
 * @property BloodGroup|null $blood_group
 * @property Religion|null $religion
 * @property string $nationality
 * @property string|null $marital_status
 * @property string|null $nid
 * @property string|null $birth_registration_no
 * @property string $mobile
 * @property string|null $email
 * @property string|null $photo_path
 * @property string $father_name
 * @property string|null $father_mobile
 * @property string|null $father_occupation
 * @property string $mother_name
 * @property string|null $mother_mobile
 * @property string|null $mother_occupation
 * @property string|null $guardian_name
 * @property string|null $guardian_relation
 * @property string|null $guardian_mobile
 * @property string|null $gpa_total
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $remarks
 * @property CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 * @property string|null $test_score
 * @property bool|null $test_passed
 * @property CarbonImmutable|null $test_recorded_at
 * @property int|null $test_recorded_by
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $rejected_at
 * @property int|null $rejected_by
 * @property CarbonImmutable|null $admitted_at
 * @property int|null $admitted_by
 * @property int|null $student_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'source', 'status', 'batch_id', 'program_id', 'quota_id', 'hall_id',
    'name_en', 'name_bn', 'gender', 'date_of_birth', 'blood_group', 'religion', 'nationality',
    'marital_status', 'nid', 'birth_registration_no', 'mobile', 'email', 'photo_path',
    'father_name', 'father_mobile', 'father_occupation', 'mother_name', 'mother_mobile', 'mother_occupation',
    'guardian_name', 'guardian_relation', 'guardian_mobile', 'gpa_total', 'payment_method', 'payment_reference', 'remarks',
])]
class AdmissionApplication extends Model
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AdmissionSource::class,
            'status' => AdmissionStatus::class,
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'religion' => Religion::class,
            'date_of_birth' => 'date',
            'gpa_total' => 'decimal:2',
            'test_score' => 'decimal:2',
            'test_passed' => 'boolean',
            'verified_at' => 'datetime',
            'test_recorded_at' => 'datetime',
            'rejected_at' => 'datetime',
            'admitted_at' => 'datetime',
        ];
    }

    /**
     * Whether the SSC + HSC GPA is below the admission test threshold.
     */
    public function needsAdmissionTest(): bool
    {
        return $this->gpa_total === null
            || (float) $this->gpa_total < (float) config('admission.test_gpa_threshold');
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<Quota, $this>
     */
    public function quota(): BelongsTo
    {
        return $this->belongsTo(Quota::class);
    }

    /**
     * @return BelongsTo<Hall, $this>
     */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    /**
     * The student record created on admission.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function testRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'test_recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    /**
     * @return HasMany<AdmissionApplicationEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(AdmissionApplicationEducation::class);
    }

    /**
     * @return HasOne<AdmissionApplicationEducation, $this>
     */
    public function higherSecondary(): HasOne
    {
        return $this->educations()->one()->where('level', EducationLevel::HigherSecondary);
    }

    /**
     * @return HasMany<AdmissionApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionApplicationDocument::class);
    }

    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function presentAddress(): MorphOne
    {
        return $this->addresses()->one()->where('type', AddressType::Present);
    }

    /**
     * @return MorphOne<Address, $this>
     */
    public function permanentAddress(): MorphOne
    {
        return $this->addresses()->one()->where('type', AddressType::Permanent);
    }
}
