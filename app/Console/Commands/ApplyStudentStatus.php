<?php

namespace App\Console\Commands;

use App\Enums\StudentStatus;
use App\Imports\Students\Clean;
use App\Models\Student;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Set the status of every student listed in a report file and record each
 * change in the student's status history. Either one status for the whole
 * file (e.g. the "Admission Cancel List"), or, when no status is given, each
 * row's own Status column (e.g. the full "Student List" report).
 */
class ApplyStudentStatus extends Command
{
    /**
     * @var string
     */
    protected $signature = 'students:apply-status
        {file : .xlsx report with a Roll / Student ID column}
        {status? : Status for every listed student, e.g. admission_cancelled; omit to use the file\'s Status column}
        {--dry-run : Show what would change without saving}';

    /**
     * @var string
     */
    protected $description = 'Set the status of the students listed in a report file, keeping reason, remarks, who and when';

    private const ID_HEADERS = ['roll', 'class roll', 'student id', 'student_id', 'id'];

    /** Status column values as the registrar's reports write them. */
    private const STATUS_LABELS = [
        'degree completed' => StudentStatus::Graduated,
        'cancelled' => StudentStatus::AdmissionCancelled,
        'canceled' => StudentStatus::AdmissionCancelled,
        'admission cancelled' => StudentStatus::AdmissionCancelled,
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $given = $this->argument('status');
        $status = $given === null ? null : StudentStatus::tryFrom((string) $given);
        $dryRun = (bool) $this->option('dry-run');

        if ($given !== null && $status === null) {
            $this->error('Unknown status. Use one of: '.implode(', ', array_column(StudentStatus::cases(), 'value')));

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path, needStatusColumn: $status === null);

        if ($rows === null) {
            $this->error($status === null
                ? 'No header row with a Roll / Student ID column and a Status column was found.'
                : 'No header row with a Roll / Student ID column was found.');

            return self::FAILURE;
        }

        $counts = ['listed' => count($rows), 'changed' => 0, 'already' => 0, 'not_found' => 0, 'unknown' => 0];
        $notFound = [];
        $unknown = [];
        $changes = [];

        DB::transaction(function () use ($rows, $status, $dryRun, $path, &$counts, &$notFound, &$unknown, &$changes) {
            foreach ($rows as $row) {
                $target = $status ?? $this->statusFromLabel($row['status']);

                if ($target === null) {
                    $counts['unknown']++;
                    $unknown[(string) $row['status']][] = $row['id'];

                    continue;
                }

                $student = Student::firstWhere('student_id', $row['id']);

                if ($student === null) {
                    $counts['not_found']++;
                    $notFound[] = $row['id'];

                    continue;
                }

                if ($student->status === $target) {
                    $counts['already']++;

                    continue;
                }

                $counts['changed']++;
                $change = $student->status->label().' → '.$target->label();
                $changes[$change] = ($changes[$change] ?? 0) + 1;

                if (! $dryRun) {
                    $student->changeStatus(
                        $target,
                        reason: $row['reason'],
                        remarks: $row['remarks'],
                        changedBy: $row['by'],
                        changedAt: $row['at'],
                        source: basename($path),
                    );
                }
            }
        });

        $this->table(
            ['Listed', $dryRun ? 'Would change' : 'Changed', $status ? 'Already '.$status->label() : 'Already right', 'Not found', 'Unknown status'],
            [[$counts['listed'], $counts['changed'], $counts['already'], $counts['not_found'], $counts['unknown']]],
        );

        if ($changes !== []) {
            ksort($changes);
            $this->table(['Change', 'Students'], array_map(fn (string $change, int $total) => [$change, $total], array_keys($changes), $changes));
        }

        if ($notFound !== []) {
            $this->warn('Not found: '.implode(', ', $notFound));
        }

        foreach ($unknown as $label => $ids) {
            $this->warn("Unknown status \"{$label}\" (skipped): ".implode(', ', $ids));
        }

        if ($dryRun) {
            $this->info('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }

    /**
     * Rows under the first header row that has an ID column (and a Status
     * column, when the status comes from the file).
     *
     * @return list<array{id: string, status: ?string, reason: ?string, remarks: ?string, by: ?string, at: ?CarbonImmutable}>|null
     */
    private function readRows(string $path, bool $needStatusColumn): ?array
    {
        $reader = new Reader;
        $reader->open($path);

        $columns = null;
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();

                    if ($columns === null) {
                        $columns = $this->detectColumns($cells);

                        if ($needStatusColumn && ! isset($columns['status'])) {
                            $columns = null;
                        }

                        continue;
                    }

                    $id = Clean::identifier($cells[$columns['id']] ?? null);

                    if ($id === null) {
                        continue;
                    }

                    $pick = fn (string $key) => isset($columns[$key]) ? Clean::text($cells[$columns[$key]] ?? null) : null;

                    $rows[] = [
                        'id' => $id,
                        'status' => $pick('status'),
                        'reason' => $pick('reason'),
                        'remarks' => $pick('remarks'),
                        'by' => isset($columns['by']) ? ltrim((string) $pick('by'), '- ') ?: null : null,
                        'at' => isset($columns['at']) ? $this->timestamp($cells[$columns['at']] ?? null) : null,
                    ];
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $columns === null ? null : $rows;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<string, int>|null
     */
    private function detectColumns(array $cells): ?array
    {
        $columns = [];

        foreach ($cells as $index => $cell) {
            $header = mb_strtolower((string) Clean::text($cell));

            if (! isset($columns['id']) && in_array($header, self::ID_HEADERS, true)) {
                $columns['id'] = $index;
            } elseif ($header === 'status') {
                $columns['status'] ??= $index;
            } elseif (str_contains($header, 'reason')) {
                $columns['reason'] ??= $index;
            } elseif (str_contains($header, 'remark')) {
                $columns['remarks'] ??= $index;
            } elseif (preg_match('/\bby$/', $header)) {
                $columns['by'] ??= $index;
            } elseif (str_contains($header, 'time') || str_contains($header, 'date')) {
                $columns['at'] ??= $index;
            }
        }

        return isset($columns['id']) ? $columns : null;
    }

    /**
     * "Degree completed" → graduated, "Cancelled" → admission cancelled,
     * otherwise the status name itself ("Active", "Inactive", …).
     */
    private function statusFromLabel(?string $label): ?StudentStatus
    {
        $label = mb_strtolower(trim((string) $label));

        return self::STATUS_LABELS[$label] ?? StudentStatus::tryFrom(str_replace(' ', '_', $label));
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $text = Clean::text($value);

        if ($text === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $text)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', $text);

        return $date ? CarbonImmutable::instance($date) : null;
    }
}
