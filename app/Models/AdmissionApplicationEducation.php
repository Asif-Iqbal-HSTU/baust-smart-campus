<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\ExamType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admission_application_id
 * @property EducationLevel $level
 * @property ExamType $exam
 * @property int $passing_year
 * @property int|null $education_board_id
 * @property string|null $institute
 * @property string|null $group
 * @property string $roll
 * @property string|null $registration_no
 * @property string $gpa
 * @property string $gpa_scale
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'level', 'exam', 'passing_year', 'education_board_id', 'institute',
    'group', 'roll', 'registration_no', 'gpa', 'gpa_scale',
])]
class AdmissionApplicationEducation extends Model
{
    /**
     * Set explicitly: Laravel treats "education" as uncountable.
     *
     * @var string
     */
    protected $table = 'admission_application_educations';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => EducationLevel::class,
            'exam' => ExamType::class,
            'gpa' => 'decimal:2',
            'gpa_scale' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<AdmissionApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    /**
     * @return BelongsTo<EducationBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(EducationBoard::class, 'education_board_id');
    }
}
