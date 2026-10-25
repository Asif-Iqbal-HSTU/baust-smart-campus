<?php

namespace App\Imports\Students;

use App\Enums\AddressType;
use App\Enums\BloodGroup;
use App\Enums\ExamType;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Religion;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\EducationBoard;
use App\Models\Hall;
use App\Models\Program;
use App\Models\Quota;

/**
 * Maps one row of the legacy "Student List" sheet to the attributes of a
 * student and its guardians, addresses and previous education.
 *
 * @phpstan-type MappedRow array{
 *     student: array<string, mixed>,
 *     guardians: list<array<string, mixed>>,
 *     addresses: list<array<string, mixed>>,
 *     educations: list<array<string, mixed>>,
 * }
 */
final class StudentRowMapper
{
    /** @var list<ImportIssue> */
    private array $issues = [];

    private int $line = 0;

    private ?string $studentId = null;

    /** @var array<string, int> program name => id */
    private array $programs;

    /** @var array<int, string|null> program id => subject code */
    private array $subjectCodes;

    /** @var array<int, array{id: int, year: int}> batch number => id / admission year */
    private array $batches;

    /** @var array<string, int> quota code => id */
    private array $quotas;

    /** @var array<string, int> hall code => id */
    private array $halls;

    /** @var array<string, int> board code => id */
    private array $boards;

    /**
     * Spellings of education boards found in the old data that are too far
     * from the real name for fuzzy matching.
     */
    private const BOARD_ALIASES = [
        'jashore' => 'jessore',
        'chattogram' => 'chittagong',
        'chattogarm' => 'chittagong',
        'cumilla' => 'comilla',
        'comillah' => 'comilla',
        'barishal' => 'barisal',
        'madrasha' => 'madrasah',
        'mymonshin' => 'mymensingh',
        'bteb' => 'technical',
        'tecnicle' => 'technical',
        'dhakatechnical' => 'technical',
        'dhakadiploma' => 'technical',
    ];

    private const DIVISIONS = ['dhaka', 'chittagong', 'chattogram', 'rajshahi', 'khulna', 'barisal', 'barishal', 'sylhet', 'rangpur', 'mymensingh'];

    public function __construct()
    {
        $this->programs = Program::pluck('id', 'name')->all();
        $this->subjectCodes = Program::pluck('subject_code', 'id')->all();
        $this->batches = Batch::all(['id', 'number', 'admission_year'])
            ->mapWithKeys(fn (Batch $batch) => [$batch->number => ['id' => $batch->id, 'year' => $batch->admission_year]])
            ->all();
        $this->quotas = Quota::pluck('id', 'code')->all();
        $this->halls = Hall::pluck('id', 'code')->all();
        $this->boards = EducationBoard::pluck('id', 'code')->all();
    }

    /**
     * @param  array<string, mixed>  $row  values keyed by source column name
     * @return MappedRow|null null when the row cannot be imported (see issues)
     */
    public function map(array $row, int $line): ?array
    {
        $this->line = $line;
        $this->studentId = Clean::identifier($row['Class Roll'] ?? null);

        if ($this->studentId === null) {
            return $this->skip('Class Roll', null, 'Missing class roll (student ID).');
        }

        $name = Clean::text($row['Student Name'] ?? null);
        if ($name === null) {
            return $this->skip('Student Name', null, 'Missing student name.');
        }

        $programName = Clean::text($row['Program'] ?? null);
        $programId = $programName !== null ? ($this->programs[$programName] ?? null) : null;
        if ($programId === null) {
            return $this->skip('Program', $programName, 'Unknown program.');
        }

        $batchNumber = Clean::batchNumber($row['Batch'] ?? null);
        $batch = $batchNumber !== null ? ($this->batches[$batchNumber] ?? null) : null;
        if ($batch === null) {
            return $this->skip('Batch', Clean::text($row['Batch'] ?? null), 'Unknown batch.');
        }

        $this->checkSubjectCode($programId);

        $year = Clean::year($row['Enrollment Year'] ?? null);
        if ($year !== null && $year !== $batch['year']) {
            $this->issue('Enrollment Year', (string) $year, "Does not match batch {$batchNumber} ({$batch['year']}); batch kept.");
        }

        return [
            'student' => [
                'student_id' => $this->studentId,
                'registration_no' => Clean::identifier($row['Registration Number'] ?? null),
                'program_id' => $programId,
                'batch_id' => $batch['id'],
                'quota_id' => $this->lookup($this->quotas, 'Quota', $row['Quota'] ?? null, strtoupper(...)),
                'hall_id' => $this->hallFromStudentId(),
                'enrollment_type' => 'general',
                'name_en' => $name,
                'name_bn' => Clean::bangla($row['Student Name (Bangla)'] ?? null),
                'gender' => $this->enum(Gender::class, 'Gender', $row['Gender'] ?? null, mb_strtolower(...)),
                'date_of_birth' => $this->checked('Date of Birth', $row, Clean::birthDate(...))?->toDateString(),
                'blood_group' => $this->enum(BloodGroup::class, 'Blood Group', $row['Blood Group'] ?? null, strtoupper(...)),
                'religion' => $this->enum(Religion::class, 'Religion', $row['Religion'] ?? null, mb_strtolower(...)),
                'nationality' => Clean::text($row['Nationality'] ?? null) ?? 'Bangladeshi',
                'marital_status' => Clean::text($row['Marital Status'] ?? null),
                'nid' => $this->checked('NID', $row, Clean::nid(...)),
                'mobile' => $this->checked('Mobile Number', $row, Clean::mobile(...)),
                'email' => $this->checked('Email', $row, Clean::email(...)),
                'status' => StudentStatus::Active,
            ],
            'guardians' => $this->guardians($row),
            'addresses' => array_values(array_filter([
                $this->address(AddressType::Present, $row['Present Address'] ?? null),
                $this->address(AddressType::Permanent, $row['Permanent Address'] ?? null),
                $this->address(AddressType::Guardian, $row['Guardian Address'] ?? null),
            ])),
            'educations' => array_values(array_filter([
                $this->education(ExamType::Ssc, 'SSC', $row),
                $this->education(ExamType::Hsc, 'HSC', $row),
            ])),
        ];
    }

    /**
     * Issues collected since the last call, then cleared.
     *
     * @return list<ImportIssue>
     */
    public function pullIssues(): array
    {
        $issues = $this->issues;
        $this->issues = [];

        return $issues;
    }

    /**
     * Hall code = digits 7–8 of a 16-digit ID: 080 | 23 | 1 | 02 | 05101 | 001.
     */
    private function hallFromStudentId(): ?int
    {
        if (! preg_match('/^080\d{13}$/', (string) $this->studentId)) {
            return null;
        }

        $code = substr((string) $this->studentId, 6, 2);

        if (! isset($this->halls[$code])) {
            $this->issue('Class Roll', $this->studentId, "Unknown hall code {$code}.");

            return null;
        }

        return $this->halls[$code];
    }

    /**
     * Subject code = digits 9–13 of a 16-digit ID; report IDs that disagree with the program.
     */
    private function checkSubjectCode(int $programId): void
    {
        $expected = $this->subjectCodes[$programId] ?? null;

        if ($expected === null || ! preg_match('/^080\d{13}$/', (string) $this->studentId)) {
            return;
        }

        $actual = substr((string) $this->studentId, 8, 5);

        if ($actual !== $expected) {
            $this->issue('Class Roll', $this->studentId, "Subject code {$actual} in the ID does not match the program ({$expected}); program kept.");
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function guardians(array $row): array
    {
        $guardians = [];

        if ($father = Clean::text($row['Father Name'] ?? null)) {
            $guardians[] = [
                'relation' => GuardianRelation::Father,
                'name' => $father,
                'mobile' => $this->checked("Father's Mobile", $row, Clean::mobile(...)),
                'occupation' => Clean::text($row['Parent Occupation'] ?? null),
                'designation' => Clean::text($row['Parent Designation'] ?? null),
                'yearly_income' => is_numeric($income = Clean::text($row['Parent Yearly Income'] ?? null)) ? $income : null,
                'military_rank' => Clean::text($row['Parent Military Rank'] ?? null),
            ];
        }

        if ($mother = Clean::text($row['Mother Name'] ?? null)) {
            $guardians[] = [
                'relation' => GuardianRelation::Mother,
                'name' => $mother,
                'mobile' => $this->checked("Mother's Mobile", $row, Clean::mobile(...)),
            ];
        }

        if ($guardian = Clean::text($row['Guardian Name'] ?? null)) {
            $guardians[] = [
                'relation' => GuardianRelation::tryFrom(mb_strtolower((string) Clean::text($row['Relation With Guardian'] ?? null))) ?? GuardianRelation::Guardian,
                'name' => $guardian,
                'mobile' => $this->checked('Guardian Phone Number', $row, Clean::mobile(...)),
            ];
        }

        if ($guardians !== []) {
            $guardians[0]['is_primary_contact'] = true;
        }

        return $guardians;
    }

    /**
     * Best-effort split of the old address formats, always keeping the original text:
     * "Village; Post Office-5200; Upazila; District; Bangladesh." or "Village, Upazila, District, Division".
     *
     * @return array<string, mixed>|null
     */
    private function address(AddressType $type, mixed $value): ?array
    {
        $raw = Clean::text($value);

        if ($raw === null) {
            return null;
        }

        $separator = str_contains($raw, ';') ? ';' : ',';
        $parts = array_values(array_filter(
            array_map(fn (string $part) => trim($part, " .\t"), explode($separator, $raw)),
            fn (string $part) => $part !== '' && mb_strtolower($part) !== 'bangladesh',
        ));

        if ($parts === []) {
            return null; // e.g. just "Bangladesh."
        }

        $address = ['type' => $type, 'raw_text' => $raw];

        foreach ($parts as $i => $part) {
            if (preg_match('/^(.+?)\s*-\s*(\d{4})$/', $part, $m)) {
                $address['post_office'] = $m[1];
                $address['post_code'] = $m[2];
                unset($parts[$i]);
            }
        }
        $parts = array_values($parts);

        if (count($parts) > 1 && in_array(mb_strtolower(end($parts)), self::DIVISIONS, true)) {
            $address['division'] = array_pop($parts);
        }
        if (count($parts) > 1) {
            $address['district'] = array_pop($parts);
        }
        if (count($parts) > 1) {
            $address['upazila'] = array_pop($parts);
        }
        $address['address_line'] = implode(', ', $parts) ?: null;

        return $address;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function education(ExamType $exam, string $prefix, array $row): ?array
    {
        $board = Clean::text($row["{$prefix} Board"] ?? null);
        $roll = Clean::identifier($row["{$prefix} Roll"] ?? null);
        $gpa = $this->checked("{$prefix} GPA", $row, Clean::gpa(...));
        $year = $this->checked("{$prefix} Year", $row, Clean::year(...));

        if ($board === null && $roll === null && $gpa === null && $year === null) {
            return null;
        }

        return [
            'exam' => $exam,
            'passing_year' => $year,
            'education_board_id' => $this->board($prefix, $board),
            'institute' => Clean::text($row["{$prefix} Institute"] ?? null),
            'roll' => $roll,
            'gpa' => $gpa,
            'gpa_scale' => '5.0',
        ];
    }

    private function board(string $prefix, ?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $key = (string) preg_replace('/[^a-z]/', '', mb_strtolower($name));
        $code = self::BOARD_ALIASES[$key] ?? (isset($this->boards[$key]) ? $key : null);

        if ($code === null) {
            foreach (array_keys($this->boards) as $candidate) {
                if (levenshtein($key, $candidate) <= 2) {
                    $code = $candidate;
                    break;
                }
            }
        }

        if ($code === null || ! isset($this->boards[$code])) {
            $this->issue("{$prefix} Board", $name, 'Unknown education board.');

            return null;
        }

        return $this->boards[$code];
    }

    /**
     * Run a cleaner on a column and record an issue when a present value is rejected.
     *
     * @template T
     *
     * @param  array<string, mixed>  $row
     * @param  callable(mixed): T  $cleaner
     * @return T
     */
    private function checked(string $column, array $row, callable $cleaner): mixed
    {
        $value = $row[$column] ?? null;
        $clean = $cleaner($value);

        if ($clean === null && ($original = Clean::text($value)) !== null) {
            $this->issue($column, $original, 'Invalid or placeholder value; stored as empty.');
        }

        return $clean;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  callable(string): string  $normalize
     * @return TEnum|null
     */
    private function enum(string $enum, string $column, mixed $value, callable $normalize): ?\BackedEnum
    {
        $text = Clean::text($value);

        if ($text === null) {
            return null;
        }

        $case = $enum::tryFrom($normalize($text));

        if ($case === null) {
            $this->issue($column, $text, 'Unrecognised value; stored as empty.');
        }

        return $case;
    }

    /**
     * @param  array<string, int>  $ids
     * @param  callable(string): string  $normalize
     */
    private function lookup(array $ids, string $column, mixed $value, callable $normalize): ?int
    {
        $text = Clean::text($value);

        if ($text === null) {
            return null;
        }

        $id = $ids[$normalize($text)] ?? null;

        if ($id === null) {
            $this->issue($column, $text, 'Unknown code; stored as empty.');
        }

        return $id;
    }

    private function issue(string $column, ?string $value, string $message): void
    {
        $this->issues[] = new ImportIssue($this->line, $this->studentId, $column, $value, $message);
    }

    private function skip(string $column, ?string $value, string $message): null
    {
        $this->issues[] = new ImportIssue($this->line, $this->studentId, $column, $value, $message, skipped: true);

        return null;
    }
}
