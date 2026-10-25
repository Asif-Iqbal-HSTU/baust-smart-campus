<?php

namespace App\Http\Controllers;

use App\Admissions\AdmissionFormOptions;
use App\Admissions\AdmissionWorkflow;
use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Http\Requests\Admissions\AdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public online admission form. Applications made here wait for
 * admission staff to verify them before anyone is admitted.
 */
class ApplyController extends Controller
{
    public function create(): Response
    {
        $batch = Batch::openForAdmission();

        return Inertia::render('apply/create', [
            'intake' => $batch?->label,
            'options' => $batch ? AdmissionFormOptions::for(staff: false) : null,
            'testThreshold' => (float) config('admission.test_gpa_threshold'),
        ]);
    }

    public function store(AdmissionApplicationRequest $request, AdmissionWorkflow $workflow): RedirectResponse
    {
        $application = $workflow->submit(
            [...$request->validated(), 'batch_id' => $request->batchId()],
            AdmissionSource::Online,
        );

        return to_route('apply.submitted')->with('submitted_application', $application->application_no);
    }

    /**
     * Confirmation shown once, right after submitting.
     */
    public function submitted(Request $request): Response|RedirectResponse
    {
        $number = $request->session()->get('submitted_application');

        if ($number === null) {
            return to_route('apply.create');
        }

        return Inertia::render('apply/submitted', ['applicationNo' => $number]);
    }

    /**
     * Applicants check their progress with the application number and mobile.
     */
    public function status(Request $request): Response
    {
        $lookup = $request->validate([
            'application_no' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $result = null;

        if (filled($lookup['application_no'] ?? null) && filled($lookup['mobile'] ?? null)) {
            $application = AdmissionApplication::with('student:id,student_id')
                ->where('application_no', strtoupper(trim($lookup['application_no'])))
                ->where('mobile', User::normalizeMobile($lookup['mobile']))
                ->first();

            $result = $application ? [
                'application_no' => $application->application_no,
                'name' => $application->name_en,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'message' => match ($application->status) {
                    AdmissionStatus::Submitted => 'Your application has been received and is waiting for verification by the admission office.',
                    AdmissionStatus::TestRequired => 'Your SSC + HSC GPA total is below '.number_format((float) config('admission.test_gpa_threshold'), 2).', so you need to sit the admission test. The admission office will contact you with the schedule.',
                    AdmissionStatus::Eligible => 'You are eligible for admission. Please contact the admission office to complete your admission.',
                    AdmissionStatus::Admitted => 'Congratulations! You have been admitted. Your student ID is '.$application->student?->student_id.'.',
                    AdmissionStatus::Rejected => 'Your application was not accepted. Please contact the admission office for details.',
                },
            ] : false;
        }

        return Inertia::render('apply/status', [
            'lookup' => (object) array_filter($lookup),
            'result' => $result,
        ]);
    }
}
