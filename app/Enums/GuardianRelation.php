<?php

namespace App\Enums;

enum GuardianRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
