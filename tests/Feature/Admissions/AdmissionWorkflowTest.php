<?php

use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Enums\GuardianRelation;
use App\Enums\Role;
use App\Enums\Semester;
use App\Enums\StudentStatus;
use App\Models\Address;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\EducationBoard;
use App\Models\Hall;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');

    $this->admin = User::factory()->admin()->create();
    $this->batch = Batch::factory()->create(['number' => 24, 'name' => '24th', 'admission_year' => 2027, 'semester' => Semester::Winter]);
    $this->program = Program::factory()->create(['subject_code' => '05101']);
    $this->hall = Hall::create(['code' => '02', 'name' => 'Abbas Uddin Ahmed Hall']);
    $this->board = EducationBoard::create(['code' => 'dinajpur', 'name' => 'Dinajpur']);
});

/**
 * A complete, valid admission form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function admissionForm(array $overrides = []): array
{
    $address = ['address_line' => 'Saidpur Cantonment', 'post_office' => 'Saidpur', 'post_code' => '5310', 'upazila' => 'Saidpur', 'district' => 'Nilphamari', 'division' => 'Rangpur'];

    return array_replace_recursive([
        'program_id' => test()->program->id,
        'name_en' => 'NUSRAT JAHAN',
        'gender' => 'female',
        'date_of_birth' => '2006-03-15',
        'religion' => 'islam',
        'nationality' => 'Bangladeshi',
        'nid' => '1234567890',
        'mobile' => '+880 1712-345678',
        'father_name' => 'MD. ABDUL KARIM',
        'father_mobile' => '01812345678',
        'mother_name' => 'ROKEYA BEGUM',
        'present' => $address,
        'permanent' => $address,
        'secondary' => ['exam' => 'ssc', 'board_id' => test()->board->id, 'passing_year' => 2022, 'roll' => '112233', 'gpa' => '5.00'],
        'higher_secondary' => ['exam' => 'hsc', 'board_id' => test()->board->id, 'passing_year' => 2024, 'roll' => '445566', 'gpa' => '4.50'],
        'photo' => UploadedFile::fake()->image('photo.jpg'),
    ], $overrides);
}

test('the online form is closed when no batch accepts applications', function () {
    $this->get(route('apply.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('apply/create')->where('options', null));

    $this->post(route('apply.store'), admissionForm())->assertSessionHasErrors('application');
    expect(AdmissionApplication::count())->toBe(0);
});

test('an online application waits for verification and does not create a student', function () {
    $this->batch->update(['admission_open' => true]);

    $this->post(route('apply.store'), admissionForm([
        'documents' => [['name' => 'HSC mark sheet', 'file' => UploadedFile::fake()->create('hsc.pdf', 200, 'application/pdf')]],
    ]))->assertRedirect(route('apply.submitted'));

    $application = AdmissionApplication::sole();

    expect($application->status)->toBe(AdmissionStatus::Submitted)
        ->and($application->source)->toBe(AdmissionSource::Online)
        ->and($application->batch_id)->toBe($this->batch->id)
        ->and($application->application_no)->toBe(sprintf('ADM24%05d', $application->id))
        ->and($application->mobile)->toBe('01712345678')
        ->and($application->gpa_total)->toBe('9.50')
        ->and($application->educations)->toHaveCount(2)
        ->and($application->addresses)->toHaveCount(2)
        ->and($application->documents)->toHaveCount(1)
        ->and(Student::count())->toBe(0);

    Storage::disk('local')->assertExists($application->photo_path);
    Storage::disk('local')->assertExists($application->documents->first()->path);

    $this->get(route('apply.submitted'))
        ->assertInertia(fn (Assert $page) => $page->component('apply/submitted')->where('applicationNo', $application->application_no));
});

test('online applicants cannot choose a batch or hall', function () {
    $this->batch->update(['admission_open' => true]);

    $this->post(route('apply.store'), admissionForm(['hall_id' => $this->hall->id, 'batch_id' => $this->batch->id]))
        ->assertSessionHasErrors(['hall_id', 'batch_id']);
});

test('an HSC roll can only apply once per batch', function () {
    $this->batch->update(['admission_open' => true]);

    $this->post(route('apply.store'), admissionForm())->assertSessionHasNoErrors();
    $this->post(route('apply.store'), admissionForm(['mobile' => '01911111111']))->assertSessionHasErrors('higher_secondary.roll');

    expect(AdmissionApplication::count())->toBe(1);
});

test('verifying sends applicants below the GPA threshold to the admission test', function (string $hscGpa, AdmissionStatus $expected) {
    $this->batch->update(['admission_open' => true]);
    $this->post(route('apply.store'), admissionForm(['secondary' => ['gpa' => '4.00'], 'higher_secondary' => ['gpa' => $hscGpa]]));
    $application = AdmissionApplication::sole();

    $this->actingAs($this->admin)
        ->post(route('admin.admissions.verify', $application))
        ->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe($expected)
        ->verified_by->toBe($this->admin->id);
})->with([
    'total 8.00 needs no test' => ['4.00', AdmissionStatus::Eligible],
    'total 7.99 needs the test' => ['3.99', AdmissionStatus::TestRequired],
]);

test('passing the admission test makes the applicant ready to admit', function () {
    $application = AdmissionApplication::factory()->lowGpa()->verified()->create(['batch_id' => $this->batch->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.admissions.test-result', $application), ['score' => 62.5, 'passed' => true])
        ->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe(AdmissionStatus::Eligible)
        ->test_score->toBe('62.50')
        ->test_passed->toBeTrue();
});

test('failing the admission test rejects the application', function () {
    $application = AdmissionApplication::factory()->lowGpa()->verified()->create(['batch_id' => $this->batch->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.admissions.test-result', $application), ['score' => 20, 'passed' => false]);

    expect($application->fresh())
        ->status->toBe(AdmissionStatus::Rejected)
        ->rejection_reason->toContain('admission test');
});

test('applicants who still need verification or the test cannot be admitted', function (Closure $make) {
    $application = $make($this->batch);

    $this->actingAs($this->admin)
        ->post(route('admin.admissions.admit', $application), ['hall_id' => $this->hall->id])
        ->assertSessionHasErrors('application');

    expect(Student::count())->toBe(0);
})->with([
    'not verified' => [fn ($batch) => AdmissionApplication::factory()->create(['batch_id' => $batch->id])],
    'test pending' => [fn ($batch) => AdmissionApplication::factory()->lowGpa()->verified()->create(['batch_id' => $batch->id])],
]);

test('admitting creates the student with the next ID of the intake and subject', function () {
    // Serials count per year + semester + subject across every hall.
    Student::factory()->create(['student_id' => '0802710105101007', 'program_id' => $this->program->id]);
    Student::factory()->create(['student_id' => '0802710205101012', 'program_id' => $this->program->id]);
    Student::factory()->create(['student_id' => '0802620205101099', 'program_id' => $this->program->id]); // another intake

    $this->batch->update(['admission_open' => true]);
    $this->post(route('apply.store'), admissionForm());
    $application = AdmissionApplication::sole();

    $this->actingAs($this->admin)->post(route('admin.admissions.verify', $application));
    $this->actingAs($this->admin)
        ->post(route('admin.admissions.admit', $application), ['hall_id' => $this->hall->id])
        ->assertSessionHasNoErrors();

    $student = Student::where('name_en', 'NUSRAT JAHAN')->sole();

    expect($student->student_id)->toBe('0802710205101013')
        ->and($student->status)->toBe(StudentStatus::Active)
        ->and($student->batch_id)->toBe($this->batch->id)
        ->and($student->hall_id)->toBe($this->hall->id)
        ->and($student->photo_path)->toBe($application->photo_path)
        ->and($student->user->role)->toBe(Role::Student)
        ->and($student->user->password)->toBeNull()
        ->and($student->guardians->pluck('relation')->all())->toEqualCanonicalizing([GuardianRelation::Father, GuardianRelation::Mother])
        ->and($student->educations)->toHaveCount(2)
        ->and($student->addresses)->toHaveCount(2)
        ->and($student->statusChanges()->sole()->source)->toBe('admission');

    expect($application->fresh())
        ->status->toBe(AdmissionStatus::Admitted)
        ->student_id->toBe($student->id)
        ->admitted_by->toBe($this->admin->id);

    // Admitting twice is refused.
    $this->actingAs($this->admin)
        ->post(route('admin.admissions.admit', $application), ['hall_id' => $this->hall->id])
        ->assertSessionHasErrors('application');
    expect(Student::count())->toBe(4);
});

test('office entries above the threshold are admitted on saving', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.admissions.store'), admissionForm(['batch_id' => $this->batch->id, 'hall_id' => $this->hall->id, 'photo' => null]))
        ->assertSessionHasNoErrors();

    $application = AdmissionApplication::sole();

    expect($application->source)->toBe(AdmissionSource::Office)
        ->and($application->status)->toBe(AdmissionStatus::Admitted)
        ->and($application->verified_by)->toBe($this->admin->id)
        ->and($application->student->student_id)->toBe('0802710205101001');
});

test('office entries below the threshold wait for the admission test', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.admissions.store'), admissionForm([
            'batch_id' => $this->batch->id,
            'hall_id' => $this->hall->id,
            'secondary' => ['gpa' => '3.50'],
            'higher_secondary' => ['gpa' => '3.75'],
        ]))
        ->assertRedirect();

    expect(AdmissionApplication::sole()->status)->toBe(AdmissionStatus::TestRequired)
        ->and(Student::count())->toBe(0);
});

test('editing a verified application re-applies the GPA rule', function () {
    $this->batch->update(['admission_open' => true]);
    $this->post(route('apply.store'), admissionForm());
    $application = AdmissionApplication::sole();
    $this->actingAs($this->admin)->post(route('admin.admissions.verify', $application));
    expect($application->fresh()->status)->toBe(AdmissionStatus::Eligible);

    $this->actingAs($this->admin)
        ->put(route('admin.admissions.update', $application), admissionForm(['batch_id' => $this->batch->id, 'photo' => null, 'higher_secondary' => ['gpa' => '2.50']]))
        ->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe(AdmissionStatus::TestRequired)
        ->gpa_total->toBe('7.50');
});

test('staff can reject an open application with a reason', function () {
    $application = AdmissionApplication::factory()->create(['batch_id' => $this->batch->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.admissions.reject', $application), ['reason' => 'Documents do not match.'])
        ->assertSessionHasNoErrors();

    expect($application->fresh())
        ->status->toBe(AdmissionStatus::Rejected)
        ->rejection_reason->toBe('Documents do not match.');
});

test('applicants can check their status with application number and mobile', function () {
    $application = AdmissionApplication::factory()->lowGpa()->verified()->create(['batch_id' => $this->batch->id, 'mobile' => '01712345678']);

    $this->get(route('apply.status', ['application_no' => $application->application_no, 'mobile' => '01712345678']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('apply/status')
            ->where('result.status', 'test_required')
            ->where('result.message', fn (string $message) => str_contains($message, 'admission test')));

    $this->get(route('apply.status', ['application_no' => $application->application_no, 'mobile' => '01999999999']))
        ->assertInertia(fn (Assert $page) => $page->where('result', false));
});

test('admission pages are for admins and admission officers only', function () {
    $application = AdmissionApplication::factory()->create(['batch_id' => $this->batch->id]);

    $this->get(route('admin.admissions.index'))->assertRedirect(route('login'));

    foreach (['teacher', 'student'] as $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.admissions.index'))
            ->assertForbidden();
    }

    $this->actingAs(User::factory()->admissionOfficer()->create())
        ->get(route('admin.admissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/admissions/index')
            ->where('applications.total', 1)
            ->where('statusCounts.submitted', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.admissions.show', $application))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/admissions/show')
            ->where('application.application_no', $application->application_no)
            ->where('application.needs_test', false));
});

test('staff open online admission for one batch at a time, or for the next batch', function () {
    $older = Batch::factory()->create(['number' => 23, 'admission_year' => 2026, 'semester' => Semester::Summer, 'admission_open' => true]);

    $this->actingAs($this->admin)->post(route('admin.admission-intake.open', $this->batch));

    expect($this->batch->fresh()->admission_open)->toBeTrue()
        ->and($older->fresh()->admission_open)->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.admission-intake.next'));

    $next = Batch::where('number', 25)->sole();
    expect($next->semester)->toBe(Semester::Summer)
        ->and($next->admission_year)->toBe(2027)
        ->and($next->admission_open)->toBeTrue()
        ->and(Batch::openForAdmission()->id)->toBe($next->id);
});

test('admins can delete an admitted test application together with the student it created', function () {
    $this->batch->update(['admission_open' => true]);
    $this->post(route('apply.store'), admissionForm());
    $application = AdmissionApplication::sole();
    $this->actingAs($this->admin)->post(route('admin.admissions.verify', $application));
    $this->actingAs($this->admin)->post(route('admin.admissions.admit', $application), ['hall_id' => $this->hall->id]);
    $student = $application->fresh()->student;
    $userId = $student->user_id;

    $this->actingAs($this->admin)
        ->delete(route('admin.admissions.destroy', $application))
        ->assertRedirect(route('admin.admissions.index'));

    expect(AdmissionApplication::count())->toBe(0)
        ->and(Student::withTrashed()->count())->toBe(0)
        ->and(User::find($userId))->toBeNull()
        ->and(Address::count())->toBe(0)
        ->and(StudentGuardian::count())->toBe(0);
    Storage::disk('local')->assertMissing("admissions/{$application->id}");

    // The freed serial is issued again.
    $this->post(route('apply.store'), admissionForm());
    $again = AdmissionApplication::sole();
    $this->actingAs($this->admin)->post(route('admin.admissions.verify', $again));
    $this->actingAs($this->admin)->post(route('admin.admissions.admit', $again), ['hall_id' => $this->hall->id]);
    expect($again->fresh()->student->student_id)->toBe($student->student_id);
});

test('deleting an application that was not admitted leaves students alone', function () {
    $other = Student::factory()->create();
    $application = AdmissionApplication::factory()->create(['batch_id' => $this->batch->id]);

    $this->actingAs($this->admin)->delete(route('admin.admissions.destroy', $application));

    expect(AdmissionApplication::count())->toBe(0)
        ->and($other->fresh())->not->toBeNull();
});

test('admission officers cannot delete applications', function () {
    $application = AdmissionApplication::factory()->create(['batch_id' => $this->batch->id]);

    $this->actingAs(User::factory()->admissionOfficer()->create())
        ->delete(route('admin.admissions.destroy', $application))
        ->assertForbidden();

    expect(AdmissionApplication::count())->toBe(1);
});
