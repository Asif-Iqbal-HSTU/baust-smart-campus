<?php

namespace App\Models;

use App\Enums\GuardianRelation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property GuardianRelation $relation
 * @property string $name
 * @property string|null $mobile
 * @property string|null $occupation
 * @property string|null $designation
 * @property bool|null $is_govt_employee
 * @property string|null $yearly_income
 * @property bool|null $is_military
 * @property bool|null $is_serving
 * @property string|null $military_rank
 * @property bool $is_primary_contact
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'student_id', 'relation', 'name', 'mobile', 'occupation', 'designation', 'is_govt_employee',
    'yearly_income', 'is_military', 'is_serving', 'military_rank', 'is_primary_contact',
])]
class StudentGuardian extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'is_govt_employee' => 'boolean',
            'is_military' => 'boolean',
            'is_serving' => 'boolean',
            'is_primary_contact' => 'boolean',
            'yearly_income' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
