<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\ExerciseService;
use App\Services\ChartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExerciseController extends Controller
{
    use AuthorizesRequests;
    
    protected $exerciseService;
    protected $chartService;
    protected $tsvImporterService;

    public function __construct(ExerciseService $exerciseService, \App\Services\ChartService $chartService, \App\Services\TsvImporterService $tsvImporterService)
    {
        $this->exerciseService = $exerciseService;
        $this->chartService = $chartService;
        $this->tsvImporterService = $tsvImporterService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $exercises = Exercise::availableToUser(auth()->id())
            ->with('user') // Load user relationship for displaying user names
            ->orderBy('user_id') // Global exercises (null) first, then user exercises
            ->orderBy('title', 'asc')
            ->get();
        return view('exercises.index', compact('exercises'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $canCreateGlobal = auth()->user()->hasRole('Admin');
        return view('exercises.create', compact('canCreateGlobal'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_bodyweight' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'band_type' => 'nullable|in:resistance,assistance',
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts
        $this->validateExerciseName($validated['title'], $validated['is_global'] ?? false);

        $isBodyweight = $request->boolean('is_bodyweight');
        $bandType = $validated['band_type'] ?? null;

        // If a band type is selected, it cannot be a bodyweight exercise
        if ($bandType !== null) {
            $isBodyweight = false;
        }

        $exercise = new Exercise([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_bodyweight' => $isBodyweight,
            'band_type' => $bandType,
        ]);
        
        if ($validated['is_global'] ?? false) {
            $exercise->user_id = null;
        } else {
            $exercise->user_id = auth()->id();
        }

        $exercise->save();

        return redirect()->route('exercises.index')->with('success', 'Exercise created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exercise $exercise)
    {
        return view('exercises.show', compact('exercise'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        $canCreateGlobal = auth()->user()->hasRole('Admin');
        return view('exercises.edit', compact('exercise', 'canCreateGlobal'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exercise $exercise)
    {
        $this->authorize('update', $exercise);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_bodyweight' => 'nullable|boolean',
            'is_global' => 'nullable|boolean',
            'band_type' => 'nullable|in:resistance,assistance',
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts (excluding current exercise)
        $this->validateExerciseNameForUpdate($exercise, $validated['title'], $validated['is_global'] ?? false);

        $isBodyweight = $request->boolean('is_bodyweight');
        $bandType = $validated['band_type'] ?? null;

        // If a band type is selected, it cannot be a bodyweight exercise
        if ($bandType !== null) {
            $isBodyweight = false;
        }

        $exercise->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_bodyweight' => $isBodyweight,
            'band_type' => $bandType,
            'user_id' => ($validated['is_global'] ?? false) ? null : auth()->id()
        ]);

        return redirect()->route('exercises.index')->with('success', 'Exercise updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exercise $exercise)
    {
        $this->authorize('delete', $exercise);
        
        if ($exercise->liftLogs()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete exercise: it has associated lift logs.']);
        }
        
        $exercise->delete();
        
        return redirect()->route('exercises.index')->with('success', 'Exercise deleted successfully.');
    }

    /**
     * Remove the specified resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $validated = $request->validate([
            'exercise_ids' => 'required|array',
            'exercise_ids.*' => 'exists:exercises,id',
        ]);

        $exercises = Exercise::whereIn('id', $validated['exercise_ids'])->get();

        foreach ($exercises as $exercise) {
            $this->authorize('delete', $exercise);
            
            if ($exercise->liftLogs()->exists()) {
                return back()->withErrors(['error' => "Cannot delete exercise '{$exercise->title}': it has associated lift logs."]);
            }
        }

        Exercise::destroy($validated['exercise_ids']);

        return redirect()->route('exercises.index')->with('success', 'Selected exercises deleted successfully!');
    }

    /**
     * Promote selected user exercises to global exercises.
     */
    public function promoteSelected(Request $request)
    {
        $validated = $request->validate([
            'exercise_ids' => 'required|array',
            'exercise_ids.*' => 'exists:exercises,id',
        ]);

        $exercises = Exercise::whereIn('id', $validated['exercise_ids'])->get();

        // Verify admin permissions and that exercises are user-specific
        foreach ($exercises as $exercise) {
            $this->authorize('promoteToGlobal', $exercise);
            
            if ($exercise->isGlobal()) {
                return back()->withErrors(['error' => "Exercise '{$exercise->title}' is already global."]);
            }
        }

        // Promote all selected exercises
        Exercise::whereIn('id', $validated['exercise_ids'])
            ->update(['user_id' => null]);

        $count = count($validated['exercise_ids']);
        return redirect()->route('exercises.index')
            ->with('success', "Successfully promoted {$count} exercise(s) to global status.");
    }

    public function showLogs(Request $request, Exercise $exercise)
    {
        // Only allow viewing logs for exercises available to the user
        $availableExercise = Exercise::availableToUser(auth()->id())->find($exercise->id);
        if (!$availableExercise) {
            abort(403, 'Unauthorized action.');
        }
        $liftLogs = $exercise->liftLogs()->with('liftSets')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();

        $displayExercises = $this->exerciseService->getDisplayExercises(5);

        $chartData = [];
        if (!$exercise->band_type) {
            $chartData = $this->chartService->generateBestPerDay($liftLogs);
        }

        $liftLogs = $liftLogs->reverse();

        $exercises = Exercise::availableToUser(auth()->id())->orderBy('title', 'asc')->get();

        $sets = $request->input('sets');
        $reps = $request->input('reps');
        $weight = $request->input('weight');

        return view('exercises.logs', compact('exercise', 'liftLogs', 'chartData', 'displayExercises', 'exercises', 'sets', 'reps', 'weight'));
    }

    public function importTsv(Request $request)
    {
        $validated = $request->validate([
            'tsv_data' => 'required|string',
            'import_as_global' => 'boolean'
        ]);

        $tsvData = trim($validated['tsv_data']);
        $importAsGlobal = $request->boolean('import_as_global', false);

        if (empty($tsvData)) {
            return redirect()
                ->route('exercises.index')
                ->with('error', 'TSV data cannot be empty.');
        }

        // Validate admin permission for global imports
        if ($importAsGlobal && !auth()->user()->hasRole('Admin')) {
            return redirect()
                ->route('exercises.index')
                ->with('error', 'Only administrators can import global exercises.');
        }

        try {
            $result = $this->tsvImporterService->importExercises($tsvData, auth()->id(), $importAsGlobal);

            $message = $this->buildImportSuccessMessage($result);
            
            return redirect()
                ->route('exercises.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()
                ->route('exercises.index')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Build detailed import success message with lists of imported, updated, and skipped exercises.
     */
    private function buildImportSuccessMessage(array $result): string
    {
        $mode = $result['importMode'] === 'global' ? 'global' : 'personal';
        $html = "<p>TSV data processed successfully!</p>";

        // Imported exercises
        if ($result['importedCount'] > 0) {
            $html .= "<p>Imported {$result['importedCount']} new {$mode} exercises:</p><ul>";
            foreach ($result['importedExercises'] as $exercise) {
                $bodyweightText = $exercise['is_bodyweight'] ? ' (bodyweight)' : '';
                $html .= "<li>" . e($exercise['title']) . e($bodyweightText) . "</li>";
            }
            $html .= "</ul>";
        }

        // Updated exercises
        if ($result['updatedCount'] > 0) {
            $html .= "<p>Updated {$result['updatedCount']} existing {$mode} exercises:</p><ul>";
            foreach ($result['updatedExercises'] as $exercise) {
                $changeDetails = [];
                foreach ($exercise['changes'] as $field => $change) {
                    if ($field === 'is_bodyweight') {
                        $changeDetails[] = "bodyweight: " . ($change['from'] ? 'yes' : 'no') . " → " . ($change['to'] ? 'yes' : 'no');
                    } else {
                        $changeDetails[] = e($field) . ": '" . e($change['from']) . "' → '" . e($change['to']) . "'";
                    }
                }
                $html .= "<li>" . e($exercise['title']) . " (" . implode(', ', $changeDetails) . ")</li>";
            }
            $html .= "</ul>";
        }

        // Skipped exercises
        if ($result['skippedCount'] > 0) {
            $html .= "<p>Skipped {$result['skippedCount']} exercises:</p><ul>";
            foreach ($result['skippedExercises'] as $exercise) {
                $html .= "<li>" . e($exercise['title']) . " - " . e($exercise['reason']) . "</li>";
            }
            $html .= "</ul>";
        }

        // Invalid rows
        if (count($result['invalidRows']) > 0) {
            $html .= "<p>Found " . count($result['invalidRows']) . " invalid rows that were skipped.</p>";
        }

        if ($result['importedCount'] === 0 && $result['updatedCount'] === 0) {
            $html .= "<p>No new data was imported or updated - all entries already exist with the same data.</p>";
        }

        return $html;
    }

    /**
     * Validate exercise name for conflicts when creating new exercise.
     */
    private function validateExerciseName(string $title, bool $isGlobal): void
    {
        if ($isGlobal) {
            // Check if global exercise with same name exists
            if (Exercise::global()->where('title', $title)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has exercise with same name OR global exercise exists
            $userId = auth()->id();
            $conflicts = Exercise::where('title', $title)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }

    /**
     * Validate exercise name for conflicts when updating existing exercise.
     */
    private function validateExerciseNameForUpdate(Exercise $exercise, string $title, bool $isGlobal): void
    {
        if ($isGlobal) {
            // Check if another global exercise with same name exists
            if (Exercise::global()->where('title', $title)->where('id', '!=', $exercise->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'A global exercise with this name already exists.'
                ]);
            }
        } else {
            // Check if user has another exercise with same name OR global exercise exists
            $userId = auth()->id();
            $conflicts = Exercise::where('title', $title)
                ->where('id', '!=', $exercise->id)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->exists();

            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'title' => 'An exercise with this name already exists.'
                ]);
            }
        }
    }
}