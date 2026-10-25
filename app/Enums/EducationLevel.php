<?php

namespace App\Enums;

/**
 * The two qualifications every admission application carries.
 */
enum EducationLevel: string
{
    case Secondary = 'secondary';
    case HigherSecondary = 'higher_secondary';

    public function label(): string
    {
        return match ($this) {
            self::Secondary => 'SSC / Equivalent',
            self::HigherSecondary => 'HSC / Equivalent',
        };
    }

    /**
     * Exams accepted for this level.
     *
     * @return list<ExamType>
     */
    public function exams(): array
    {
        return match ($this) {
            self::Secondary => [ExamType::Ssc, ExamType::Dakhil, ExamType::OLevel],
            self::HigherSecondary => [ExamType::Hsc, ExamType::Alim, ExamType::Diploma, ExamType::ALevel],
        };
    }
}
