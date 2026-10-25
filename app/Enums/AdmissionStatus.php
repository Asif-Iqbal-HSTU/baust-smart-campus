<?php

namespace App\Enums;

/**
 * Where an admission application stands.
 *
 * Online: Submitted → (verified) → TestRequired | Eligible → Admitted.
 * Office: entered by staff from the paper form, so it starts verified.
 * Any open application can be Rejected; failing the test rejects it.
 */
enum AdmissionStatus: string
{
    case Submitted = 'submitted';
    case TestRequired = 'test_required';
    case Eligible = 'eligible';
    case Admitted = 'admitted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Awaiting verification',
            self::TestRequired => 'Admission test',
            self::Eligible => 'Ready to admit',
            self::Admitted => 'Admitted',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Still being processed (can be edited or rejected).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::TestRequired, self::Eligible], true);
    }
}
