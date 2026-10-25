<?php

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\Hall;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('only admins can open the student list', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('admin.students.index'))->assertForbidden();
})->with(['teacher', 'student']);

test('guests are sent to the login page', function () {
    $this->get(route('admin.students.index'))->assertRedirect(route('login'));
});

test('admins see a paginated list with filter options', function () {
    Student::factory()->count(30)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/index')
            ->where('students.total', 30)
            ->has('students.data', 25)
            ->has('options.departments')
            ->has('options.batches')
            ->has('options.statuses', count(StudentStatus::cases())));
});

test('students can be filtered', function (Closure $filters, string $expected) {
    $cse = Program::factory()->create();
    $bba = Program::factory()->create();
    $batch = Batch::factory()->create();
    $hall = Hall::create(['code' => '02', 'name' => 'Abbas Uddin Ahmed Hall']);

    Student::factory()->create(['student_id' => 'A', 'name_en' => 'RAHIM UDDIN', 'program_id' => $cse->id, 'hall_id' => $hall->id, 'gender' => Gender::Male]);
    Student::factory()->create(['student_id' => 'B', 'name_en' => 'KARIM ALI', 'program_id' => $bba->id, 'batch_id' => $batch->id, 'gender' => Gender::Female, 'mobile' => '01711000000']);
    Student::factory()->create(['student_id' => 'C', 'name_en' => 'NUSRAT JAHAN', 'program_id' => $bba->id, 'status' => StudentStatus::Inactive, 'gender' => Gender::Female]);

    $query = $filters(compact('cse', 'bba', 'batch', 'hall'));

    $this->actingAs($this->admin)
        ->get(route('admin.students.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.data', fn ($rows) => collect($rows)->pluck('student_id')->sort()->implode(',') === $expected));
})->with([
    'program' => [fn ($m) => ['program' => $m['cse']->id], 'A'],
    'department' => [fn ($m) => ['department' => $m['bba']->department_id], 'B,C'],
    'batch' => [fn ($m) => ['batch' => $m['batch']->id], 'B'],
    'hall' => [fn ($m) => ['hall' => $m['hall']->id], 'A'],
    'no hall' => [fn ($m) => ['hall' => 'none'], 'B,C'],
    'status' => [fn ($m) => ['status' => 'inactive'], 'C'],
    'gender' => [fn ($m) => ['gender' => 'female'], 'B,C'],
    'search by name' => [fn ($m) => ['search' => 'karim'], 'B'],
    'search by mobile' => [fn ($m) => ['search' => '0171100'], 'B'],
    'combined' => [fn ($m) => ['program' => $m['bba']->id, 'status' => 'active'], 'B'],
]);

test('admins can open a student profile by student id', function () {
    $student = Student::factory()->create(['student_id' => '0802620205101001']);
    $student->guardians()->create(['relation' => GuardianRelation::Father, 'name' => 'MD. FATHER', 'is_primary_contact' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.students.show', '0802620205101001'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/show')
            ->where('student.student_id', '0802620205101001')
            ->where('student.guardians.0.name', 'MD. FATHER'));
});

test('invalid filter values are rejected', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.students.index', ['status' => 'nonsense']))
        ->assertSessionHasErrors('status');
});
