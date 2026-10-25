<?php

namespace App\Imports\Students;

/**
 * One problem found in one row of the source file. A "skipped" issue means
 * the whole row was not imported; otherwise only that value was dropped.
 */
final readonly class ImportIssue
{
    public function __construct(
        public int $line,
        public ?string $studentId,
        public string $column,
        public ?string $value,
        public string $message,
        public bool $skipped = false,
    ) {}

    /**
     * @return list<string|int|null>
     */
    public function toRow(): array
    {
        return [$this->line, $this->studentId, $this->column, $this->value, $this->message, $this->skipped ? 'row skipped' : 'value dropped'];
    }
}
