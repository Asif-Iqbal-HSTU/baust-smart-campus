<?php

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * A report laid out like the registrar's "Admission Cancel List" export.
 *
 * @param  list<list<string|null>>  $rows
 */
function cancelReport(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'cancel').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Bangladesh Army University of Science and Technology (BAUST), Saidpur']));
    $writer->addRow(Row::fromValues(['Report: Admission Cancel List']));
    $writer->addRow(Row::fromValues(['Download Time: Wednesday, 30/09/2026 11:02 am']));
    $writer->addRow(Row::fromValues(['SL', 'Roll', 'Reg. No.', 'Student Name', 'Department', 'Batch', 'Cancellation Reason', 'Remarks', 'Cancelled By', 'Cancellation Time']));

    foreach ($rows as $i => $row) {
        $writer->addRow(Row::fromValues([(string) ($i + 1), ...$row]));
    }

    $writer->close();

    return $path;
}

test('listed students get the new status with reason, remarks, who and when', function () {
    $student = Student::factory()->create(['student_id' => '0802620205101026']);
    $other = Student::factory()->create();

    $file = cancelReport([
        ['0802620205101026', '0802620205101026', 'MD. TUHIN', 'CSE', '23rd', 'Ref: Admission office', 'Date: 08 August 2026', '- system admin', '2026-08-08 20:34:59'],
    ]);

    $this->artisan('students:apply-status', ['file' => $file, 'status' => 'admission_cancelled'])->assertSuccessful();

    $student->refresh();
    $change = $student->statusChanges()->sole();

    expect($student->status)->toBe(StudentStatus::AdmissionCancelled)
        ->and($other->refresh()->status)->toBe(StudentStatus::Active)
        ->and($change->from_status)->toBe(StudentStatus::Active)
        ->and($change->reason)->toBe('Ref: Admission office')
        ->and($change->remarks)->toBe('Date: 08 August 2026')
        ->and($change->changed_by)->toBe('system admin')
        ->and($change->changed_at->format('Y-m-d H:i:s'))->toBe('2026-08-08 20:34:59')
        ->and($change->source)->toEndWith('.xlsx');
});

test('re-running does not duplicate history, and unknown rolls are reported', function () {
    Student::factory()->create(['student_id' => '0802620205101026']);
    $file = cancelReport([
        ['0802620205101026', null, 'A', 'CSE', '23rd', 'Reason', null, 'Someone', '2026-08-08 20:34:59'],
        ['0000000000000000', null, 'B', 'CSE', '23rd', 'Reason', null, 'Someone', '2026-08-08 20:34:59'],
    ]);

    $this->artisan('students:apply-status', ['file' => $file, 'status' => 'admission_cancelled'])
        ->expectsOutputToContain('Not found: 0000000000000000')
        ->assertSuccessful();
    $this->artisan('students:apply-status', ['file' => $file, 'status' => 'admission_cancelled'])->assertSuccessful();

    expect(Student::sole()->statusChanges()->count())->toBe(1);
});

test('a dry run changes nothing', function () {
    Student::factory()->create(['student_id' => '0802620205101026']);

    $this->artisan('students:apply-status', [
        'file' => cancelReport([['0802620205101026', null, 'A', 'CSE', '23rd', 'Reason', null, 'X', '2026-08-08 20:34:59']]),
        'status' => 'admission_cancelled',
        '--dry-run' => true,
    ])->expectsOutputToContain('Dry run')->assertSuccessful();

    expect(Student::sole()->status)->toBe(StudentStatus::Active);
});

test('an unknown status is rejected', function () {
    $this->artisan('students:apply-status', ['file' => cancelReport([]), 'status' => 'expelled'])->assertFailed();
});

test('the student list counts students per status and filters by admission cancelled', function () {
    Student::factory()->count(2)->create();
    Student::factory()->create(['status' => StudentStatus::AdmissionCancelled, 'student_id' => 'X1']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.students.index', ['status' => 'admission_cancelled']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('statusCounts.active', 2)
            ->where('statusCounts.admission_cancelled', 1)
            ->where('students.total', 1)
            ->where('students.data.0.status_label', 'Admission Cancelled'));
});

test('the profile shows the status history', function () {
    $student = Student::factory()->create(['student_id' => '0802620205101026']);
    $student->changeStatus(StudentStatus::AdmissionCancelled, reason: 'Wrong ID from the admission office', changedBy: 'Registrar');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.students.show', '0802620205101026'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('student.status_label', 'Admission Cancelled')
            ->where('student.status_history.0.reason', 'Wrong ID from the admission office')
            ->where('student.status_history.0.by', 'Registrar'));
});

test('without a status argument each row takes the status from the Status column', function () {
    $path = tempnam(sys_get_temp_dir(), 'list').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Bangladesh Army University of Science and Technology (BAUST), Saidpur']));
    $writer->addRow(Row::fromValues(['Report: Student List']));
    $writer->addRow(Row::fromValues(['Download Time: Wednesday, 30/09/2026 11:21 am']));
    $writer->addRow(Row::fromValues(['SL', 'Roll', 'Regi', 'Gender', 'Name', 'Contact', 'Dept.', 'Batch', 'Level-Term', 'Section', 'Syllabus', 'Earned Cr.', 'Credit Requirements', 'Quota', 'Stipend', 'Scholarship', 'Status']));
    foreach ([['A1', 'Active'], ['G1', 'Degree completed'], ['I1', 'Inactive'], ['C1', 'Cancelled'], ['X1', 'On leave']] as $i => [$id, $status]) {
        $writer->addRow(Row::fromValues([(string) ($i + 1), $id, $id, 'Male', 'NAME', '', 'CSE', '23rd', '', 'A', '2021', '', '161.00', 'General', '', '', $status]));
    }
    $writer->close();

    foreach (['A1', 'G1', 'I1', 'C1', 'X1'] as $id) {
        Student::factory()->create(['student_id' => $id]);
    }

    $this->artisan('students:apply-status', ['file' => $path])
        ->expectsOutputToContain('Unknown status "On leave" (skipped): X1')
        ->assertSuccessful();

    expect(Student::pluck('status', 'student_id')->map->value->all())->toBe([
        'A1' => 'active',
        'G1' => 'graduated',
        'I1' => 'inactive',
        'C1' => 'admission_cancelled',
        'X1' => 'active',
    ])->and(Student::firstWhere('student_id', 'G1')->statusChanges()->sole()->source)->toEndWith('.xlsx')
        ->and(Student::firstWhere('student_id', 'A1')->statusChanges()->count())->toBe(0);
});
