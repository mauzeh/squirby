<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\TsvImporterService;
use App\Services\ExerciseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\TrainingProgressionService;

class LiftLogController extends Controller
{
    protected $tsvImporterService;
    protected $exerciseService;

    public function __construct(TsvImporterService $tsvImporterService, ExerciseService $exerciseService)
    {
        $this->tsvImporterService = $tsvImporterService;
        $this->exerciseService = $exerciseService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $liftLogs = LiftLog::with(['exercise', 'liftSets'])->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();

        $displayExercises = $this->exerciseService->getDisplayExercises(5);

        return view('lift-logs.index', compact('liftLogs', 'displayExercises', 'exercises'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'weight' => 'required|numeric',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
        ]);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));

        $liftLog = LiftLog::create([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
            'user_id' => auth()->id(),
        ]);

        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        if ($request->input('redirect_to') === 'mobile-entry') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'), // Assuming program_id is passed from the form
            ];
            return redirect()->route('lift-logs.mobile-entry', $redirectParams)->with('success', 'Lift log created successfully.');
        } else {
            return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', 'Lift log created successfully.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $exercises = Exercise::where('user_id', auth()->id())->orderBy('title', 'asc')->get();
        return view('lift-logs.edit', compact('liftLog', 'exercises'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
            'comments' => 'nullable|string',
            'date' => 'required|date',
            'logged_at' => 'required|date_format:H:i',
        ]);

        $loggedAtDate = Carbon::parse($request->input('date'));
        $loggedAt = $loggedAtDate->setTimeFromTimeString($request->input('logged_at'));

        $liftLog->update([
            'exercise_id' => $request->input('exercise_id'),
            'comments' => $request->input('comments'),
            'logged_at' => $loggedAt,
        ]);

        // Delete existing lift sets
        $liftLog->liftSets()->delete();

        // Create new lift sets
        $weight = $request->input('weight');
        $reps = $request->input('reps');
        $rounds = $request->input('rounds');

        for ($i = 0; $i < $rounds; $i++) {
            $liftLog->liftSets()->create([
                'weight' => $weight,
                'reps' => $reps,
                'notes' => $request->input('comments'),
            ]);
        }

        if ($request->input('redirect_to') === 'mobile-entry') {
            $redirectParams = [
                'date' => $request->input('date'),
                'submitted_lift_log_id' => $liftLog->id,
                'submitted_program_id' => $request->input('program_id'), // Assuming program_id is passed from the form
            ];
            return redirect()->route('lift-logs.mobile-entry', $redirectParams)->with('success', 'Lift log updated successfully.');
        } else {
            return redirect()->route('exercises.show-logs', ['exercise' => $liftLog->exercise_id])->with('success', 'Lift log updated successfully.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        $liftLog->delete();

        if (request()->input('redirect_to') === 'mobile-entry') {
            return redirect()->route('lift-logs.mobile-entry', ['date' => request()->input('date')])->with('success', 'Lift log deleted successfully.');
        }

        return redirect()->route('lift-logs.index')->with('success', 'Lift log deleted successfully.');
    }

    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'lift_log_ids' => 'required|array',
            'lift_log_ids.*' => 'exists:lift_logs,id',
        ]);

        $liftLogs = LiftLog::whereIn('id', $validated['lift_log_ids'])->get();

        foreach ($liftLogs as $liftLog) {
            if ($liftLog->user_id !== auth()->id()) {
                abort(403, 'Unauthorized action.');
            }
        }

        LiftLog::destroy($validated['lift_log_ids']);

        return redirect()->route('lift-logs.index')->with('success', 'Selected lift logs deleted successfully!');
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $tsvData = trim($validated['tsv_data']);
        if (empty($tsvData)) {
            return redirect()
                ->route('lift-logs.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        $result = $this->tsvImporterService->importLiftLogs($tsvData, $validated['date'], auth()->id());

        // Handle errors first
        if ($result['importedCount'] === 0 && !empty($result['notFound'])) {
            $uniqueNotFound = array_unique($result['notFound']);
            $notFoundCount = count($uniqueNotFound);
            
            if ($notFoundCount > 10) {
                $errorMessage = 'No exercises were found for ' . $notFoundCount . ' exercise names in the import data.';
            } else {
                $errorHtml = 'No exercises were found for the following names:<ul>';
                foreach ($uniqueNotFound as $notFoundExercise) {
                    $errorHtml .= '<li>' . htmlspecialchars($notFoundExercise) . '</li>';
                }
                $errorHtml .= '</ul>';
                $errorMessage = $errorHtml;
            }

            return redirect()
                ->route('lift-logs.index')
                ->with('error', $errorMessage);
        } elseif ($result['importedCount'] === 0 && !empty($result['invalidRows'])) {
            return redirect()
                ->route('lift-logs.index')
                ->with('error', 'No lift logs imported due to invalid data in rows: ' . implode(', ', array_map(function($row) { return '"' . $row . '"' ; }, $result['invalidRows'])));
        }

        // Build success message
        $successMessage = 'TSV data processed successfully! ';
        $totalProcessed = $result['importedCount'] + $result['updatedCount'];
        
        // Add counts
        $countParts = [];
        if ($result['importedCount'] > 0) {
            $countParts[] = $result['importedCount'] . ' lift log(s) imported';
        }
        if ($result['updatedCount'] > 0) {
            $countParts[] = $result['updatedCount'] . ' lift log(s) updated';
        }
        
        if (!empty($countParts)) {
            $successMessage .= implode(', ', $countParts) . '.';
        } else {
            // Handle case where nothing was imported or updated (all duplicates)
            $successMessage .= 'No new data was imported or updated - all entries already exist with the same data.';
        }

        // Add detailed list if total < 10
        if ($totalProcessed < 10) {
            if (!empty($result['importedEntries'])) {
                $successMessage .= '<br><br><strong>Imported:</strong><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $result['importedEntries'])) . '</li></ul>';
            }
            if (!empty($result['updatedEntries'])) {
                $successMessage .= '<br><br><strong>Updated:</strong><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $result['updatedEntries'])) . '</li></ul>';
            }
        }

        // Add invalid rows warning if any
        if (!empty($result['invalidRows'])) {
            $successMessage .= '<br><br><strong>Warning:</strong> Some rows were invalid: ' . implode(', ', array_map(function($row) { return '"' . htmlspecialchars($row) . '"'; }, $result['invalidRows']));
        }

        // Add missing exercises warning if any (for partial success cases)
        if (!empty($result['notFound'])) {
            $uniqueNotFound = array_unique($result['notFound']);
            $notFoundCount = count($uniqueNotFound);
            
            if ($notFoundCount > 10) {
                $successMessage .= '<br><br><strong>Warning:</strong> ' . $notFoundCount . ' exercise names were not found and their rows were skipped.';
            } else {
                $successMessage .= '<br><br><strong>Warning:</strong> The following exercises were not found and their rows were skipped:<ul>';
                foreach ($uniqueNotFound as $notFoundExercise) {
                    $successMessage .= '<li>' . htmlspecialchars($notFoundExercise) . '</li>';
                }
                $successMessage .= '</ul>';
            }
        }

        return redirect()
            ->route('lift-logs.index')
            ->with('success', $successMessage);
    }

    public function mobileEntry(Request $request, \App\Services\TrainingProgressionService $trainingProgressionService)
    {
        $selectedDate = $request->input('date') ? \Carbon\Carbon::parse($request->input('date')) : \Carbon\Carbon::today();

        $programs = \App\Models\Program::with('exercise')
            ->where('user_id', auth()->id())
            ->whereDate('date', $selectedDate->toDateString())
            ->orderBy('priority')
            ->get();

        if ($selectedDate->isToday() || $selectedDate->isTomorrow() || $selectedDate->copy()->addDay()->isTomorrow()) {
            foreach ($programs as $program) {
                if (!$program->exercise->is_bodyweight) {
                    $suggestionDetails = $trainingProgressionService->getSuggestionDetails(
                        auth()->id(),
                        $program->exercise_id,
                        $selectedDate
                    );

                    if ($suggestionDetails) {
                        $program->suggestedNextWeight = $suggestionDetails->suggestedWeight;
                        $program->lastWeight = $suggestionDetails->lastWeight;
                        $program->lastReps = $suggestionDetails->lastReps;
                        $program->lastSets = $suggestionDetails->lastSets;
                        $program->reps = $suggestionDetails->reps;
                        $program->sets = $suggestionDetails->sets;
                        
                    } else {
                        $program->suggestedNextWeight = null;
                        $program->lastWeight = null;
                        
                    }
                } else {
                    $program->suggestedNextWeight = null;
                    $program->lastWeight = null;
                    
                }
            }
        } else {
            foreach ($programs as $program) {
                $program->suggestedNextWeight = null;
                $program->lastWeight = null;
                
            }
        }

        //dd($programs);

        $submittedLiftLog = null;
        if ($request->has('submitted_lift_log_id')) {
            $submittedLiftLog = \App\Models\LiftLog::with('liftSets', 'exercise')->find($request->input('submitted_lift_log_id'));
        }

        // Fetch all lift logs for the selected date and user
        $dailyLiftLogs = \App\Models\LiftLog::with('liftSets', 'exercise')
            ->where('user_id', auth()->id())
            ->whereDate('logged_at', $selectedDate->toDateString())
            ->get()
            ->keyBy('exercise_id'); // Key by exercise_id for easy lookup

        $exercises = \App\Models\Exercise::where('user_id', auth()->id())->orderBy('title')->get();

        return view('lift-logs.mobile-entry', compact('programs', 'selectedDate', 'submittedLiftLog', 'dailyLiftLogs', 'exercises'));
    }
}