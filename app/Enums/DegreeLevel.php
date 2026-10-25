<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case Undergraduate = 'undergraduate';
    case Postgraduate = 'postgraduate';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
