<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Which batch (intake) accepts online applications. At most one is open.
 */
class AdmissionIntakeController extends Controller
{
    public function open(Batch $batch): RedirectResponse
    {
        DB::transaction(function () use ($batch) {
            Batch::where('admission_open', true)->update(['admission_open' => false]);
            $batch->update(['admission_open' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Online admission is open for the {$batch->label} batch."]);

        return back();
    }

    public function close(): RedirectResponse
    {
        Batch::where('admission_open', true)->update(['admission_open' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online admission is closed.']);

        return back();
    }

    /**
     * Create the batch after the latest one and open it for online applications.
     */
    public function next(): RedirectResponse
    {
        $latest = Batch::orderByDesc('number')->firstOrFail();

        return $this->open(Batch::create($latest->nextIntake()));
    }
}
