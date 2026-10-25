<?php

namespace App\Enums;

/**
 * Admission semester of a batch. The value is the semester digit used in
 * student IDs (e.g. 080 23 1 ... = Winter 2023).
 */
enum Semester: int
{
    case Winter = 1;
    case Summer = 2;

    public function label(): string
    {
        return match ($this) {
            self::Winter => 'Winter',
            self::Summer => 'Summer',
        };
    }
}
