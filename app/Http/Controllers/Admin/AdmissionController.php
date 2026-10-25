<?php

namespace App\Http\Controllers\Admin;

use App\Admissions\AdmissionFormOptions;
use App\Admissions\AdmissionPresenter;
use App\Admissions\AdmissionWorkflow;
use App\Enums\AdmissionSource;
use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\AdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\AdmissionApplicationDocument;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    /**
     * Applications by status, newest first.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'batch' => ['nullable', 'integer'],
            'program' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::enum(AdmissionSource::class)],
            'status' => ['nullable', Rule::enum(AdmissionStatus::class)],
        ]);

        $applications = $this->filtered($filters)
            ->with(['program:id,name', 'batch:id,number,name,admission_year,semester', 'student:id,student_id'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $openBatch = Batch::openForAdmission();
        $latestBatch = Batch::orderByDesc('number')->first();

        return Inertia::render('admin/admissions/index', [
            'applications' => $applications->through(fn (AdmissionApplication $application) => [
                'id' => $application->id,
                'application_no' => $application->application_no,
                'name' => $application->name_en,
                'mobile' => $application->mobile,
                'program' => $application->program->name,
                'batch' => $application->batch->name,
                'gpa_total' => $application->gpa_total,
                'needs_test' => $application->needsAdmissionTest(),
                'source' => $application->source->value,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'student_id' => $application->student?->student_id,
                'submitted_at' => $application->created_at?->format('d M Y'),
            ]),
            'filters' => (object) array_filter($filters, fn ($value) => $value !== null),
            'statusCounts' => $this->filtered($filters)
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'intake' => [
                'open' => $openBatch ? ['id' => $openBatch->id, 'label' => $openBatch->label] : null,
                'batches' => Batch::orderByDesc('number')->limit(4)->get()->map(fn (Batch $batch) => ['id' => $batch->id, 'label' => $batch->label]),
                'next' => $latestBatch ? Batch::ordinal($latestBatch->nextIntake()['number']).' ('.$latestBatch->nextIntake()['semester']->label().' '.$latestBatch->nextIntake()['admission_year'].')' : null,
            ],
            'testThreshold' => (float) config('admission.test_gpa_threshold'),
            'options' => [
                'batches' => Batch::orderByDesc('number')->limit(8)->get()->map(fn (Batch $batch) => ['id' => $batch->id, 'label' => $batch->label]),
                'programs' => Department::with('programs:id,department_id,name')->orderBy('code')->get()
                    ->flatMap(fn (Department $department) => $department->programs->map(fn ($program) => ['id' => $program->id, 'label' => $program->name])),
                'sources' => collect(AdmissionSource::cases())->map(fn (AdmissionSource $source) => ['id' => $source->value, 'label' => $source->label()]),
                'statuses' => collect(AdmissionStatus::cases())->map(fn (AdmissionStatus $status) => ['id' => $status->value, 'label' => $status->label()]),
            ],
        ]);
    }

    /**
     * Staff entry of a paper admission form.
     */
    public function create(): Response
    {
        return Inertia::render('admin/admissions/create', [
            'options' => AdmissionFormOptions::for(staff: true),
            'defaultBatch' => (Batch::openForAdmission() ?? Batch::orderByDesc('number')->first())?->id,
            'testThreshold' => (float) config('admission.test_gpa_threshold'),
        ]);
    }

    /**
     * Office entries are verified on entry; when the GPA clears the threshold
     * and a hall is chosen the applicant is admitted at once.
     */
    public function store(AdmissionApplicationRequest $request, AdmissionWorkflow $workflow): RedirectResponse
    {
        $application = $workflow->submit($request->validated(), AdmissionSource::Office, $request->user());

        if ($application->status === AdmissionStatus::Eligible && $application->hall_id !== null) {
            $student = $workflow->admit($application, Hall::findOrFail($application->hall_id), $request->user());
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$student->name_en} admitted with student ID {$student->student_id}."]);
        } elseif ($application->status === AdmissionStatus::TestRequired) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "Saved as {$application->application_no}. GPA total is below the threshold, so the applicant must sit the admission test."]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Saved as {$application->application_no}. Choose a hall to admit."]);
        }

        return to_route('admin.admissions.show', $application);
    }

    public function show(AdmissionApplication $application): Response
    {
        return Inertia::render('admin/admissions/show', [
            'application' => AdmissionPresenter::detail($application),
            'matchingStudents' => AdmissionPresenter::matchingStudents($application),
            'halls' => AdmissionFormOptions::halls(),
            'testThreshold' => (float) config('admission.test_gpa_threshold'),
        ]);
    }

    public function edit(AdmissionApplication $application): Response|RedirectResponse
    {
        if (! $application->status->isOpen()) {
            return to_route('admin.admissions.show', $application);
        }

        return Inertia::render('admin/admissions/edit', [
            'application' => [
                'application_no' => $application->application_no,
                'status_label' => $application->status->label(),
                'values' => AdmissionPresenter::form($application),
            ],
            'options' => AdmissionFormOptions::for(staff: true),
            'testThreshold' => (float) config('admission.test_gpa_threshold'),
        ]);
    }

    public function update(AdmissionApplicationRequest $request, AdmissionApplication $application, AdmissionWorkflow $workflow): RedirectResponse
    {
        $workflow->update($application, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application updated.']);

        return to_route('admin.admissions.show', $application);
    }

    public function destroy(AdmissionApplication $application, AdmissionWorkflow $workflow): RedirectResponse
    {
        $studentId = $application->student?->student_id;

        $workflow->delete($application);

        Inertia::flash('toast', ['type' => 'success', 'message' => $studentId
            ? "Application {$application->application_no} and student {$studentId} deleted."
            : "Application {$application->application_no} deleted."]);

        return to_route('admin.admissions.index');
    }

    public function photo(AdmissionApplication $application): StreamedResponse
    {
        abort_if($application->photo_path === null, 404);

        return Storage::disk('local')->response($application->photo_path);
    }

    public function document(AdmissionApplication $application, AdmissionApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->admission_application_id === $application->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    /**
     * Applications matching every filter except status.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AdmissionApplication>
     */
    private function filtered(array $filters): Builder
    {
        return AdmissionApplication::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('application_no', 'like', "%{$search}%")
                ->orWhere('name_en', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhereHas('educations', fn (Builder $query) => $query->where('roll', $search))))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $batch) => $query->where('batch_id', $batch))
            ->when($filters['program'] ?? null, fn (Builder $query, int $program) => $query->where('program_id', $program))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source));
    }
}
