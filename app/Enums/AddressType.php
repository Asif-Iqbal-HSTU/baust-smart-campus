<?php

namespace App\Enums;

enum AddressType: string
{
    case Present = 'present';
    case Permanent = 'permanent';
    case Guardian = 'guardian';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
