<?php

namespace App\Http\Controllers\Admin;

use App\Admissions\AdmissionWorkflow;
use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\Hall;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Decisions on an application: verify, record the admission test, admit, reject.
 */
class AdmissionActionController extends Controller
{
    public function __construct(private AdmissionWorkflow $workflow) {}

    public function verify(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $this->workflow->verify($application, $request->user());

        $this->toast($application->status === AdmissionStatus::TestRequired ? 'info' : 'success', $application->status === AdmissionStatus::TestRequired
            ? 'Verified. GPA total is below the threshold, so the applicant must sit the admission test.'
            : 'Verified. The applicant is ready to admit.');

        return back();
    }

    public function testResult(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'passed' => ['required', 'boolean'],
        ]);

        $this->workflow->recordTestResult(
            $application,
            isset($validated['score']) ? (float) $validated['score'] : null,
            (bool) $validated['passed'],
            $request->user(),
        );

        $this->toast('success', $validated['passed'] ? 'Test passed. The applicant is ready to admit.' : 'Test failed. The application was rejected.');

        return back();
    }

    public function admit(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $request->validate([
            'hall_id' => ['required', 'integer', Rule::exists('halls', 'id')],
        ]);

        $student = $this->workflow->admit($application, Hall::findOrFail($request->integer('hall_id')), $request->user());

        $this->toast('success', "{$student->name_en} admitted with student ID {$student->student_id}.");

        return back();
    }

    public function reject(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->reject($application, $validated['reason'], $request->user());

        $this->toast('success', 'Application rejected.');

        return back();
    }

    private function toast(string $type, string $message): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
