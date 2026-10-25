<?php

namespace App\Models;

use App\Enums\ExamType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property ExamType $exam
 * @property int|null $passing_year
 * @property int|null $education_board_id
 * @property string|null $institute
 * @property string|null $group
 * @property string|null $roll
 * @property string|null $registration_no
 * @property string|null $gpa
 * @property string $gpa_scale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'student_id', 'exam', 'passing_year', 'education_board_id', 'institute',
    'group', 'roll', 'registration_no', 'gpa', 'gpa_scale',
])]
class StudentEducation extends Model
{
    /**
     * Set explicitly: Laravel treats "education" as uncountable.
     *
     * @var string
     */
    protected $table = 'student_educations';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam' => ExamType::class,
            'gpa' => 'decimal:2',
            'gpa_scale' => 'decimal:1',
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
     * @return BelongsTo<EducationBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(EducationBoard::class, 'education_board_id');
    }
}
