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
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts
        $this->validateExerciseName($validated['title'], $validated['is_global'] ?? false);

        $exercise = new Exercise([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_bodyweight' => $request->boolean('is_bodyweight'),
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
        ]);

        // Check admin permission for global exercises
        if ($validated['is_global'] ?? false) {
            $this->authorize('createGlobalExercise', Exercise::class);
        }

        // Check for name conflicts (excluding current exercise)
        $this->validateExerciseNameForUpdate($exercise, $validated['title'], $validated['is_global'] ?? false);

        $exercise->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_bodyweight' => $request->boolean('is_bodyweight'),
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

    public function showLogs(Request $request, Exercise $exercise)
    {
        // Only allow viewing logs for exercises available to the user
        $availableExercise = Exercise::availableToUser(auth()->id())->find($exercise->id);
        if (!$availableExercise) {
            abort(403, 'Unauthorized action.');
        }
        $liftLogs = $exercise->liftLogs()->with('liftSets')->where('user_id', auth()->id())->orderBy('logged_at', 'asc')->get();

        $displayExercises = $this->exerciseService->getDisplayExercises(5);

        $chartData = $this->chartService->generateBestPerDay($liftLogs);

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
        $parts = ["TSV data processed successfully!"];

        // Imported exercises
        if ($result['importedCount'] > 0) {
            $parts[] = "Imported {$result['importedCount']} new {$mode} exercises:";
            foreach ($result['importedExercises'] as $exercise) {
                $bodyweightText = $exercise['is_bodyweight'] ? ' (bodyweight)' : '';
                $parts[] = "• {$exercise['title']}{$bodyweightText}";
            }
        }

        // Updated exercises
        if ($result['updatedCount'] > 0) {
            $parts[] = "Updated {$result['updatedCount']} existing {$mode} exercises:";
            foreach ($result['updatedExercises'] as $exercise) {
                $changeDetails = [];
                foreach ($exercise['changes'] as $field => $change) {
                    if ($field === 'is_bodyweight') {
                        $changeDetails[] = "bodyweight: " . ($change['from'] ? 'yes' : 'no') . " → " . ($change['to'] ? 'yes' : 'no');
                    } else {
                        $changeDetails[] = "{$field}: '{$change['from']}' → '{$change['to']}'";
                    }
                }
                $parts[] = "• {$exercise['title']} (" . implode(', ', $changeDetails) . ")";
            }
        }

        // Skipped exercises
        if ($result['skippedCount'] > 0) {
            $parts[] = "Skipped {$result['skippedCount']} exercises:";
            foreach ($result['skippedExercises'] as $exercise) {
                $parts[] = "• {$exercise['title']} - {$exercise['reason']}";
            }
        }

        // Invalid rows
        if (count($result['invalidRows']) > 0) {
            $parts[] = "Found " . count($result['invalidRows']) . " invalid rows that were skipped.";
        }

        if ($result['importedCount'] === 0 && $result['updatedCount'] === 0) {
            $parts[] = "No new data was imported or updated - all entries already exist with the same data.";
        }

        return implode("\n", $parts);
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