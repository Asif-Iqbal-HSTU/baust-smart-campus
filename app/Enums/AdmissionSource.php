<?php

namespace App\Enums;

enum AdmissionSource: string
{
    /** Filled on paper at the admission office and entered by staff. */
    case Office = 'office';

    /** Submitted by the applicant through the public online form. */
    case Online = 'online';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
