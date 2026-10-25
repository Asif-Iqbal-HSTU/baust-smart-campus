<?php

use App\Http\Controllers\Admin\AdmissionActionController;
use App\Http\Controllers\Admin\AdmissionController;
use App\Http\Controllers\Admin\AdmissionIntakeController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\ApplyController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Public online admission. Applications wait for staff verification.
Route::prefix('apply')->name('apply.')->group(function () {
    Route::get('/', [ApplyController::class, 'create'])->name('create');
    Route::post('/', [ApplyController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('submitted', [ApplyController::class, 'submitted'])->name('submitted');
    Route::get('status', [ApplyController::class, 'status'])->middleware('throttle:30,1')->name('status');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:admin,admission_officer'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student:student_id}', [StudentController::class, 'show'])->name('students.show');

    Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
    Route::get('admissions/create', [AdmissionController::class, 'create'])->name('admissions.create');
    Route::post('admissions', [AdmissionController::class, 'store'])->name('admissions.store');
    Route::get('admissions/{application:application_no}', [AdmissionController::class, 'show'])->name('admissions.show');
    Route::get('admissions/{application:application_no}/edit', [AdmissionController::class, 'edit'])->name('admissions.edit');
    Route::put('admissions/{application:application_no}', [AdmissionController::class, 'update'])->name('admissions.update');
    Route::get('admissions/{application:application_no}/photo', [AdmissionController::class, 'photo'])->name('admissions.photo');
    Route::get('admissions/{application:application_no}/documents/{document}', [AdmissionController::class, 'document'])->name('admissions.document');

    Route::post('admissions/{application:application_no}/verify', [AdmissionActionController::class, 'verify'])->name('admissions.verify');
    Route::post('admissions/{application:application_no}/test-result', [AdmissionActionController::class, 'testResult'])->name('admissions.test-result');
    Route::post('admissions/{application:application_no}/admit', [AdmissionActionController::class, 'admit'])->name('admissions.admit');
    Route::post('admissions/{application:application_no}/reject', [AdmissionActionController::class, 'reject'])->name('admissions.reject');

    // Deleting also removes the student an admission created, so only admins may do it.
    Route::delete('admissions/{application:application_no}', [AdmissionController::class, 'destroy'])->middleware('role:admin')->name('admissions.destroy');

    Route::post('admission-intake/next', [AdmissionIntakeController::class, 'next'])->name('admission-intake.next');
    Route::post('admission-intake/close', [AdmissionIntakeController::class, 'close'])->name('admission-intake.close');
    Route::post('admission-intake/{batch}/open', [AdmissionIntakeController::class, 'open'])->name('admission-intake.open');
});

require __DIR__.'/settings.php';
