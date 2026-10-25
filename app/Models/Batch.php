<?php

namespace App\Models;

use App\Enums\Semester;
use Carbon\CarbonImmutable;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A university-wide admission intake, shared by every program
 * (e.g. the 23rd batch = Summer 2026).
 *
 * @property int $id
 * @property int $number
 * @property string $name
 * @property int $admission_year
 * @property Semester|null $semester
 * @property bool $admission_open Accepting online applications
 * @property-read string $label
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['number', 'name', 'admission_year', 'semester', 'admission_open'])]
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'admission_open' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * The intake currently accepting online applications, if any.
     */
    public static function openForAdmission(): ?self
    {
        return static::where('admission_open', true)->first();
    }

    /**
     * The intake after this one: Winter follows Summer of the previous year.
     *
     * @return array{number: int, name: string, admission_year: int, semester: Semester}
     */
    public function nextIntake(): array
    {
        $number = $this->number + 1;
        $summer = $this->semester === Semester::Summer;

        return [
            'number' => $number,
            'name' => static::ordinal($number),
            'admission_year' => $summer ? $this->admission_year + 1 : $this->admission_year,
            'semester' => $summer ? Semester::Winter : Semester::Summer,
        ];
    }

    /**
     * Ordinal batch name for a batch number: 1 → "1st", 23 → "23rd".
     */
    public static function ordinal(int $number): string
    {
        $suffix = match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }

    /**
     * Display label, e.g. "23rd (Summer 2026)".
     */
    public function getLabelAttribute(): string
    {
        $session = trim(($this->semester?->label() ?? '').' '.$this->admission_year);

        return "{$this->name} ({$session})";
    }
}
