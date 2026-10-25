<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a student's status, with who changed it, when and why.
 *
 * @property int $id
 * @property int $student_id
 * @property StudentStatus|null $from_status
 * @property StudentStatus $to_status
 * @property string|null $reason
 * @property string|null $remarks
 * @property string|null $changed_by
 * @property int|null $changed_by_user_id
 * @property CarbonImmutable $changed_at
 * @property string|null $source
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'student_id', 'from_status', 'to_status', 'reason', 'remarks',
    'changed_by', 'changed_by_user_id', 'changed_at', 'source',
])]
class StudentStatusChange extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => StudentStatus::class,
            'to_status' => StudentStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
