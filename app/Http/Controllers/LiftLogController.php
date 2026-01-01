<?php

namespace App\Http\Controllers;

use App\Actions\LiftLogs\CreateLiftLogAction;
use App\Actions\LiftLogs\UpdateLiftLogAction;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Services\ExerciseTypes\Exceptions\InvalidExerciseDataException;
use App\Services\RedirectService;
use App\Services\ExerciseListService;
use App\Services\MobileEntry\LiftLogService;
use App\Services\ExerciseAliasService;
use App\Services\ComponentBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiftLogController extends Controller
{
    public function __construct(
        private RedirectService $redirectService,
        private ExerciseListService $exerciseListService,
        private CreateLiftLogAction $createLiftLogAction,
        private UpdateLiftLogAction $updateLiftLogAction,
        private LiftLogService $liftLogService,
        private ExerciseAliasService $exerciseAliasService
    ) {}
    
    /**
     * Show the form for creating a new lift log entry
     */
    public function create(Request $request)
    {
        $exerciseId = $request->input('exercise_id');
        $date = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date'))
            : \Carbon\Carbon::today();
        
        if (!$exerciseId) {
            return redirect()->route('mobile-entry.lifts')
                ->with('error', 'No exercise specified.');
        }
        
        // Capture redirect parameters
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        if ($request->has('workout_id')) {
            $redirectParams['workout_id'] = $request->input('workout_id');
        }
        
        // Determine back URL based on redirect parameters
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo === 'workouts') {
            $workoutId = $request->input('workout_id');
            $backUrl = route('workouts.index', $workoutId ? ['workout_id' => $workoutId] : []);
        } elseif ($redirectTo === 'mobile-entry-lifts') {
            $backUrl = route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        } elseif ($redirectTo === 'exercises-logs') {
            $backUrl = route('exercises.show-logs', ['exercise' => $exerciseId]);
        } else {
            // Default to mobile-entry lifts
            $backUrl = route('mobile-entry.lifts', ['date' => $date->toDateString()]);
        }
        
        // Generate the page using the service
        try {
            $formComponent = $this->liftLogService->generateFormComponent(
                $exerciseId,
                Auth::id(),
                $date,
                $redirectParams
            );
            
            // Get the exercise for the title (we know it exists now since form generation succeeded)
            $exercise = Exercise::where('id', $exerciseId)
                ->availableToUser(Auth::id())
                ->first();
            
            // Get user and apply alias to exercise title
            $user = Auth::user();
            $displayName = $this->exerciseAliasService->getDisplayName($exercise, $user);
            
            // Build components array with title and back button
            $components = [];
            
            // Add title with back button
            $components[] = ComponentBuilder::title($displayName)
                ->subtitle($date->format('l, F j, Y'))
                ->backButton('fa-arrow-left', $backUrl, 'Back')
                ->condensed()
                ->build();
            
            // Add the form
            $components[] = $formComponent;
            
            $data = [
                'components' => $components,
                'autoscroll' => true
            ];
            
            return view('mobile-entry.flexible', compact('data'));
        } catch (\Exception $e) {
            return redirect()->route('mobile-entry.lifts')
                ->with('error', 'Exercise not found or not accessible.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LiftLog $liftLog, Request $request)
    {
        if ($liftLog->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Load necessary relationships
        $liftLog->load(['exercise', 'liftSets']);
        
        // Capture redirect parameters from the request
        $redirectParams = [];
        if ($request->has('redirect_to')) {
            $redirectParams['redirect_to'] = $request->input('redirect_to');
        }
        
        // Generate edit form component using the service
        $formComponent = $this->liftLogService->generateFormComponent(
            $liftLog->exercise_id,
            Auth::id(),
            Carbon::parse($liftLog->logged_at),
            $redirectParams,
            $liftLog
        );
        
        $data = [
            'components' => [$formComponent],
            'autoscroll' => true
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = Auth::id();
        
        // Get all exercises that the user has logged
        $exercises = $this->exerciseListService->getLoggedExercises($userId);

        // Build components array
        $components = [
            ComponentBuilder::title(
                'Metrics',
                'Select an exercise to view your training history, personal records, and 1RM calculator.'
            )->build(),
        ];
        
        // Add success/error messages if present
        if ($sessionMessages = ComponentBuilder::messagesFromSession()) {
            $components[] = $sessionMessages;
        }
        
        // Build exercise list
        if ($exercises->isEmpty()) {
            $components[] = ComponentBuilder::messages()
                ->add('info', config('mobile_entry_messages.empty_states.metrics_getting_started'))
                ->build();
            $components[] = ComponentBuilder::button('Log Now')
                ->asLink(route('mobile-entry.lifts', ['expand_selection' => true]))
                ->build();
        } else {
            $components[] = $this->exerciseListService->generateMetricsExerciseList($userId);
        }

        $data = ['components' => $components];

        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $result = $this->createLiftLogAction->execute($request, Auth::user());
            
            return $this->redirectService->getRedirect(
                'lift_logs',
                'store',
                $request,
                [
                    'submitted_lift_log_id' => $result['liftLog']->id,
                    'exercise' => $result['liftLog']->exercise_id,
                ],
                $result['successMessage']
            )->with('is_pr', $result['isPR']);
            
        } catch (InvalidExerciseDataException $e) {
            return back()->withErrors(['exercise_data' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LiftLog $liftLog)
    {
        try {
            $updatedLiftLog = $this->updateLiftLogAction->execute($request, $liftLog, Auth::user());
            
            return $this->redirectService->getRedirect(
                'lift_logs',
                'update',
                $request,
                [
                    'submitted_lift_log_id' => $updatedLiftLog->id,
                    'exercise' => $updatedLiftLog->exercise_id,
                ],
                'Lift log updated successfully.'
            );
            
        } catch (InvalidExerciseDataException $e) {
            return back()->withErrors(['exercise_data' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LiftLog $liftLog)
    {
        if ($liftLog->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        // Generate a simple deletion message before deleting
        $exercise = $liftLog->exercise;
        $exerciseTitle = $this->exerciseAliasService->getDisplayName($exercise, Auth::user());
        
        $isMobileEntry = in_array(request()->input('redirect_to'), ['mobile-entry', 'mobile-entry-lifts', 'workouts']);
        
        if ($isMobileEntry) {
            $deletionMessage = str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted_mobile'));
        } else {
            $deletionMessage = str_replace(':exercise', $exerciseTitle, config('mobile_entry_messages.success.lift_deleted'));
        }
        
        $liftLog->delete();

        return $this->redirectService->getRedirect(
            'lift_logs',
            'destroy',
            request(),
            [],
            $deletionMessage
        );
    }
}
