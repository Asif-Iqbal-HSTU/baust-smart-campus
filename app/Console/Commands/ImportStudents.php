<?php

namespace App\Console\Commands;

use App\Imports\Students\Clean;
use App\Imports\Students\ImportIssue;
use App\Imports\Students\StudentImporter;
use App\Imports\Students\StudentRowMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use SplTempFileObject;
use Throwable;

class ImportStudents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'students:import
        {file : Path to the legacy "Student List" .xlsx export}
        {--dry-run : Validate and report without saving anything}';

    /**
     * @var string
     */
    protected $description = 'Import students from the legacy system export, cleaning values and writing an issues report';

    public function handle(StudentRowMapper $mapper, StudentImporter $importer): int
    {
        $path = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        DB::disableQueryLog();

        $counts = ['rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        /** @var list<ImportIssue> $issues */
        $issues = [];

        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $bar = $this->output->createProgressBar();
                $bar->start();

                foreach ($sheet->getRowIterator() as $index => $row) {
                    /** @var Row $row */
                    $cells = $row->toArray();

                    if ($headers === null) {
                        $headers = array_map(fn ($header) => Clean::text($header) ?? '', $cells);

                        continue;
                    }

                    if (array_filter($cells, fn ($v) => $v !== null && $v !== '') === []) {
                        continue;
                    }

                    $counts['rows']++;
                    $values = array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), null));
                    $mapped = $mapper->map($values, $index);

                    if ($mapped === null) {
                        $counts['skipped']++;
                    } elseif (! $dryRun) {
                        try {
                            $importer->save($mapped) ? $counts['created']++ : $counts['updated']++;
                        } catch (Throwable $e) {
                            if ($counts['failed']++ < 3) {
                                $this->newLine();
                                $this->error("Line {$index}: ".$e->getMessage());
                            }

                            $issues[] = new ImportIssue($index, $mapped['student']['student_id'], '-', null, 'Save failed: '.$e->getMessage(), skipped: true);
                        }
                    }

                    array_push($issues, ...$mapper->pullIssues());
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine(2);

                break; // only the first sheet holds students
            }
        } finally {
            $reader->close();
        }

        $report = $this->writeReport($issues);

        $this->table(['Rows read', 'Created', 'Updated', 'Skipped', 'Failed', 'Issues'], [[
            $counts['rows'], $counts['created'], $counts['updated'], $counts['skipped'], $counts['failed'], count($issues),
        ]]);

        $this->table(['Column', 'Issue', 'Count'], collect($issues)
            ->countBy(fn (ImportIssue $issue) => $issue->column.'|'.$issue->message)
            ->sortDesc()
            ->map(fn (int $count, string $key) => [...explode('|', $key, 2), $count])
            ->values()
            ->all());

        $this->info(($dryRun ? 'Dry run: nothing was saved. ' : '')."Issues report: {$report}");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<ImportIssue>  $issues
     */
    private function writeReport(array $issues): string
    {
        $path = 'imports/students-'.now()->format('Ymd-His').'.csv';
        $csv = new SplTempFileObject;

        $csv->fputcsv(['line', 'student_id', 'column', 'value', 'issue', 'effect'], escape: '');
        foreach ($issues as $issue) {
            $csv->fputcsv($issue->toRow(), escape: '');
        }

        $content = '';
        foreach ($csv as $line) {
            $content .= is_string($line) ? $line : '';
        }
        Storage::disk('local')->put($path, $content);

        return Storage::disk('local')->path($path);
    }
}
