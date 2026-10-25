<?php

namespace App\Imports\Students;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Value cleaning rules for the legacy student list (see the Student module
 * design brief, section 3). Each method returns null for values that are
 * empty, placeholders or invalid.
 */
final class Clean
{
    /**
     * Values the old system used to mean "nothing".
     */
    private const PLACEHOLDERS = ['', '-', '--', '.', '..', 'n/a', 'na', 'null', 'none', 'nil', '0'];

    /**
     * Birth dates that were bulk-filled by the old system, not real dates.
     */
    private const PLACEHOLDER_BIRTH_DATES = ['1998-11-20', '1753-01-01'];

    /**
     * Shared or institutional addresses used in place of a student's own email.
     */
    private const PLACEHOLDER_EMAILS = ['baust@gmail.com', 'info@baust.edu.bd', 'amirmd@baust.edu.bd'];

    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return in_array(mb_strtolower($text), self::PLACEHOLDERS, true) ? null : $text;
    }

    /**
     * Identifiers such as student IDs and rolls: text, digits kept exactly (no float formatting).
     */
    public static function identifier(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = number_format($value, 0, '', '');
        }

        return self::text($value);
    }

    /**
     * A valid Bangladeshi mobile number in 01XXXXXXXXX form. Restores a
     * leading 0 lost by numeric storage.
     */
    public static function mobile(mixed $value): ?string
    {
        $text = self::identifier($value);

        if ($text === null) {
            return null;
        }

        $digits = User::normalizeMobile($text);
        $digits = (string) preg_replace('/\D/', '', $digits);

        if (preg_match('/^1[3-9]\d{8}$/', $digits)) {
            $digits = '0'.$digits;
        }

        return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
    }

    public static function email(mixed $value): ?string
    {
        $email = self::text($value);

        if ($email === null) {
            return null;
        }

        $email = mb_strtolower($email);

        if (in_array($email, self::PLACEHOLDER_EMAILS, true) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    /**
     * A plausible date of birth, or null for placeholders and impossible dates.
     */
    public static function birthDate(mixed $value): ?CarbonImmutable
    {
        $date = self::date($value);

        if ($date === null || in_array($date->toDateString(), self::PLACEHOLDER_BIRTH_DATES, true)) {
            return null;
        }

        return $date->year >= 1950 && $date->lessThan(CarbonImmutable::now()->subYears(12)) ? $date : null;
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        foreach (['m/d/Y', 'Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);

            if ($date !== false && $date->format($format) === $text) {
                return CarbonImmutable::instance($date);
            }
        }

        return null;
    }

    /**
     * National ID (10 or 13 digits) or birth registration number (17 digits).
     */
    public static function nid(mixed $value): ?string
    {
        $nid = self::identifier($value);

        return $nid !== null && preg_match('/^(\d{10}|\d{13}|\d{17})$/', $nid) ? $nid : null;
    }

    /**
     * A 4-digit exam or admission year within a sensible range.
     */
    public static function year(mixed $value): ?int
    {
        $text = self::identifier($value);

        if ($text === null || ! preg_match('/^\d{4}$/', $text)) {
            return null;
        }

        $year = (int) $text;

        return $year >= 1980 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    public static function gpa(mixed $value, float $scale = 5.0): ?string
    {
        $text = self::text($value);

        if ($text === null || ! is_numeric($text)) {
            return null;
        }

        $gpa = (float) $text;

        return $gpa > 0 && $gpa <= $scale ? number_format($gpa, 2, '.', '') : null;
    }

    /**
     * Bangla text only: the old "Student Name (Bangla)" column mostly holds English names.
     */
    public static function bangla(mixed $value): ?string
    {
        $text = self::text($value);

        return $text !== null && preg_match('/\p{Bengali}/u', $text) ? $text : null;
    }

    /**
     * Batch number from an ordinal such as "23rd".
     */
    public static function batchNumber(mixed $value): ?int
    {
        $text = self::text($value);

        return $text !== null && preg_match('/^(\d{1,3})(st|nd|rd|th)?$/i', $text, $m) ? (int) $m[1] : null;
    }
}
