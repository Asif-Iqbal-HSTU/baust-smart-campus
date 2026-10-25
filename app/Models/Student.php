<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $student_id Class roll, e.g. 0802310205101001; the student's login ID
 * @property string|null $registration_no
 * @property int $program_id
 * @property int $batch_id
 * @property int|null $quota_id
 * @property int|null $hall_id
 * @property string $enrollment_type
 * @property string $name_en
 * @property string|null $name_bn
 * @property Gender|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property BloodGroup|null $blood_group
 * @property Religion|null $religion
 * @property string $nationality
 * @property string|null $marital_status
 * @property string|null $nid
 * @property string|null $mobile
 * @property string|null $email
 * @property string|null $photo_path
 * @property StudentStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'user_id', 'student_id', 'registration_no', 'program_id', 'batch_id', 'quota_id', 'hall_id',
    'enrollment_type', 'name_en', 'name_bn', 'gender', 'date_of_birth', 'blood_group', 'religion',
    'nationality', 'marital_status', 'nid', 'mobile', 'email', 'photo_path', 'status',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'religion' => Religion::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return HasOneThrough<Department, Program, $this>
     */
    public function department(): HasOneThrough
    {
        return $this->hasOneThrough(
            Department::class,
            Program::class,
            'id',            // programs.id
            'id',            // departments.id
            'program_id',    // students.program_id
            'department_id', // programs.department_id
        );
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
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
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    /**
     * @return HasOne<StudentGuardian, $this>
     */
    public function father(): HasOne
    {
        return $this->guardians()->one()->where('relation', GuardianRelation::Father);
    }

    /**
     * @return HasOne<StudentGuardian, $this>
     */
    public function mother(): HasOne
    {
        return $this->guardians()->one()->where('relation', GuardianRelation::Mother);
    }

    /**
     * Status history, newest first.
     *
     * @return HasMany<StudentStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(StudentStatusChange::class)->latest('changed_at');
    }

    /**
     * Change the status and record why, by whom and when.
     */
    public function changeStatus(
        StudentStatus $status,
        ?string $reason = null,
        ?string $remarks = null,
        ?string $changedBy = null,
        ?User $changedByUser = null,
        ?\DateTimeInterface $changedAt = null,
        ?string $source = null,
    ): StudentStatusChange {
        $change = $this->statusChanges()->create([
            'from_status' => $this->status,
            'to_status' => $status,
            'reason' => $reason,
            'remarks' => $remarks,
            'changed_by' => $changedBy ?? $changedByUser?->name,
            'changed_by_user_id' => $changedByUser?->id,
            'changed_at' => $changedAt ?? now(),
            'source' => $source,
        ]);

        $this->update(['status' => $status]);

        return $change;
    }

    /**
     * @return HasMany<StudentEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(StudentEducation::class);
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

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StudentStatus::Active);
    }
}
