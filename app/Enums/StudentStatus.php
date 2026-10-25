<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case AdmissionCancelled = 'admission_cancelled';
    case Graduated = 'graduated';
    case Dropped = 'dropped';
    case Suspended = 'suspended';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::AdmissionCancelled => 'Admission Cancelled',
            default => ucfirst($this->value),
        };
    }
}
