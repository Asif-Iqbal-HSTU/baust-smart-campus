<?php

use App\Enums\AddressType;
use App\Enums\ExamType;
use App\Enums\GuardianRelation;
use App\Enums\Role;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

const LEGACY_HEADERS = [
    'Enrollment Type', 'Department', 'Program', 'Enrollment Year', 'Batch', 'Registration Number', 'Class Roll',
    'Quota', 'Student Name', 'Student Name (Bangla)', 'Mobile Number', 'Email', 'Date of Birth', 'Gender',
    'Nationality', 'Marital Status', 'Blood Group', 'Religion', 'NID', 'Father Name', 'Mother Name',
    "Father's Mobile", "Mother's Mobile", 'Parent Occupation', 'Is Parent Job Govt', 'Parent Designation',
    'Parent Yearly Income', 'Is Parent Military', 'Is Parent Serving', 'Parent Military Rank', 'Present Address',
    'Permanent Address', 'Guardian Name', 'Relation With Guardian', 'Guardian Phone Number', 'Guardian Address',
    'SSC Exam Type', 'SSC Year', 'SSC Board', 'SSC Institute', 'SSC Roll', 'SSC GPA',
    'HSC Exam Type', 'HSC Year', 'HSC Board', 'HSC Institute', 'HSC Roll', 'HSC GPA',
];

/**
 * @param  list<array<string, mixed>>  $rows  values keyed by legacy header
 */
function legacyWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(LEGACY_HEADERS));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues(array_map(fn (string $header) => $row[$header] ?? null, LEGACY_HEADERS)));
    }

    $writer->close();

    return $path;
}

/**
 * @return array<string, mixed>
 */
function legacyRow(array $overrides = []): array
{
    return [
        'Enrollment Type' => 'General',
        'Department' => 'CSE',
        'Program' => 'B.Sc. Engg. in CSE',
        'Enrollment Year' => '2026',
        'Batch' => '23rd',
        'Registration Number' => '0802620205101001',
        'Class Roll' => '0802620205101001',
        'Quota' => 'AF',
        'Student Name' => 'AL-ARAFAT RASHID',
        'Student Name (Bangla)' => 'আল-আরাফাত রশিদ',
        'Mobile Number' => '1842825402',
        'Email' => 'arafat@example.com',
        'Date of Birth' => '08/10/2006',
        'Gender' => 'Male',
        'Nationality' => 'Bangladeshi',
        'Blood Group' => 'AB+',
        'Religion' => 'Islam',
        'NID' => '5102938475',
        'Father Name' => 'MD. MAMUNUR RASHID',
        'Mother Name' => 'MOST. AMINA KHATUN',
        "Father's Mobile" => '01712534372',
        "Mother's Mobile" => '17209',
        'Parent Occupation' => 'Police Officer',
        'Is Parent Job Govt' => 'No',
        'Is Parent Military' => 'No',
        'Is Parent Serving' => 'No',
        'Present Address' => 'Balubari; Dinajpur Sadar-5200; Dinajpur Sadar; Dinajpur; Bangladesh.',
        'Guardian Address' => 'Bangladesh.',
        'SSC Exam Type' => 'SSC',
        'SSC Year' => '2022',
        'SSC Board' => 'Dinjpur',
        'SSC Roll' => '230318',
        'SSC GPA' => '5',
        'HSC Exam Type' => 'HSC',
        'HSC Year' => '2017772688',
        'HSC Board' => 'DINAJPUR',
        'HSC Roll' => '153715',
        'HSC GPA' => '4.33',
        ...$overrides,
    ];
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed([AcademicStructureSeeder::class, LookupSeeder::class]);
});

test('a legacy row becomes a student with account, guardians, address and exam results', function () {
    $this->artisan('students:import', ['file' => legacyWorkbook([legacyRow()])])->assertSuccessful();

    $student = Student::with(['program', 'batch', 'quota', 'hall', 'user', 'guardians', 'addresses', 'educations.board'])->sole();

    expect($student->student_id)->toBe('0802620205101001')
        ->and($student->program->code)->toBe('BSC-CSE')
        ->and($student->batch->label)->toBe('23rd (Summer 2026)')
        ->and($student->quota->name)->toBe('Children of Armed Forces')
        ->and($student->hall->name)->toBe('Abbas Uddin Ahmed Hall')
        ->and($student->name_bn)->toBe('আল-আরাফাত রশিদ')
        ->and($student->mobile)->toBe('01842825402')
        ->and($student->date_of_birth->toDateString())->toBe('2006-08-10')
        ->and($student->status)->toBe(StudentStatus::Active)
        ->and($student->user->role)->toBe(Role::Student)
        ->and($student->user->password)->toBeNull();

    expect($student->guardians->pluck('relation')->all())->toBe([GuardianRelation::Father, GuardianRelation::Mother])
        ->and($student->guardians->firstWhere('relation', GuardianRelation::Father)->is_primary_contact)->toBeTrue()
        ->and($student->guardians->firstWhere('relation', GuardianRelation::Mother)->mobile)->toBeNull();

    // "Bangladesh." alone carries no address, so only the present address is kept.
    $address = $student->addresses->sole();
    expect($address->type)->toBe(AddressType::Present)
        ->and($address->post_office)->toBe('Dinajpur Sadar')
        ->and($address->post_code)->toBe('5200')
        ->and($address->district)->toBe('Dinajpur')
        ->and($address->address_line)->toBe('Balubari');

    $ssc = $student->educations->firstWhere('exam', ExamType::Ssc);
    $hsc = $student->educations->firstWhere('exam', ExamType::Hsc);
    expect($ssc->board->code)->toBe('dinajpur')
        ->and($ssc->gpa)->toBe('5.00')
        ->and($hsc->board->code)->toBe('dinajpur')
        ->and($hsc->passing_year)->toBeNull();
});

test('placeholder and corrupted values are stored as empty and reported', function () {
    $this->artisan('students:import', ['file' => legacyWorkbook([legacyRow([
        'Class Roll' => '150201001',
        'Student Name (Bangla)' => 'AL-ARAFAT RASHID',
        'Email' => 'baust@gmail.com',
        'Date of Birth' => '11/20/1998',
        'Gender' => 'Unknown',
        'NID' => '-1672744723',
        'Father Name' => '-',
    ])])])->assertSuccessful();

    $student = Student::sole();

    expect($student->name_bn)->toBeNull()
        ->and($student->email)->toBeNull()
        ->and($student->date_of_birth)->toBeNull()
        ->and($student->gender)->toBeNull()
        ->and($student->nid)->toBeNull()
        ->and($student->hall_id)->toBeNull() // legacy IDs carry no hall code
        ->and($student->guardians()->pluck('relation')->all())->toBe([GuardianRelation::Mother]);

    $report = Storage::disk('local')->get(Storage::disk('local')->files('imports')[0]);
    expect($report)->toContain('Date of Birth')->toContain('NID')->toContain('Gender');
});

test('an id whose subject code disagrees with the program is imported and reported', function () {
    $this->artisan('students:import', ['file' => legacyWorkbook([
        legacyRow(['Class Roll' => '0802620205111001']), // 05111 is not CSE (05101)
    ])])->assertSuccessful();

    expect(Student::sole()->program->code)->toBe('BSC-CSE');

    $report = Storage::disk('local')->get(Storage::disk('local')->files('imports')[0]);
    expect($report)->toContain('Subject code 05111');
});

test('rows with an unknown program or batch are skipped and reported', function () {
    $this->artisan('students:import', ['file' => legacyWorkbook([
        legacyRow(['Class Roll' => '1', 'Program' => 'Diploma in Cooking']),
        legacyRow(['Class Roll' => '2', 'Batch' => '99th']),
        legacyRow(['Class Roll' => '3']),
    ])])->assertSuccessful();

    expect(Student::pluck('student_id')->all())->toBe(['3']);

    $report = Storage::disk('local')->get(Storage::disk('local')->files('imports')[0]);
    expect($report)->toContain('Unknown program')->toContain('Unknown batch');
});

test('a dry run saves nothing', function () {
    $this->artisan('students:import', ['file' => legacyWorkbook([legacyRow()]), '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Student::count())->toBe(0)->and(User::count())->toBe(0);
});

test('re-running the import updates students instead of duplicating them', function () {
    $file = legacyWorkbook([legacyRow()]);

    $this->artisan('students:import', ['file' => $file])->assertSuccessful();
    $this->artisan('students:import', ['file' => legacyWorkbook([legacyRow(['Student Name' => 'AL ARAFAT RASHID'])])])->assertSuccessful();

    expect(Student::count())->toBe(1)
        ->and(User::count())->toBe(1)
        ->and(StudentGuardian::count())->toBe(2)
        ->and(Student::sole()->user->name)->toBe('AL ARAFAT RASHID');
});

test('the academic structure matches the current system', function () {
    expect(Batch::count())->toBe(23)
        ->and(Batch::firstWhere('number', 22)->label)->toBe('22nd (Winter 2026)')
        ->and(Batch::firstWhere('number', 5)->semester)->toBeNull()
        ->and(Batch::firstWhere('number', 16)->semester)->toBe(Semester::Winter);
});
