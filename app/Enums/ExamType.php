<?php

namespace App\Enums;

enum ExamType: string
{
    case Ssc = 'ssc';
    case Hsc = 'hsc';
    case Dakhil = 'dakhil';
    case Alim = 'alim';
    case Diploma = 'diploma';
    case OLevel = 'o_level';
    case ALevel = 'a_level';
    case Bachelor = 'bachelor';
    case Master = 'master';

    public function label(): string
    {
        return match ($this) {
            self::Ssc => 'SSC',
            self::Hsc => 'HSC',
            self::OLevel => 'O Level',
            self::ALevel => 'A Level',
            default => ucfirst($this->value),
        };
    }
}
