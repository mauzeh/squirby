<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ComponentBuilder as C;

/**
 * Flexible Workflow Controller
 * 
 * Example controller showing how to use the component-based mobile entry view.
 * This demonstrates building UIs without hardcoded sections - everything is optional.
 */
class FlexibleWorkflowController extends Controller
{
    /**
     * Example 1: Date-based navigation (like original mobile entry)
     */
    public function withDateNavigation(Request $request)
    {
        $selectedDate = $request->input('date') 
            ? \Carbon\Carbon::parse($request->input('date')) 
            : \Carbon\Carbon::today();
        
        $prevDay = $selectedDate->copy()->subDay();
        $nextDay = $selectedDate->copy()->addDay();
        $today = \Carbon\Carbon::today();
        
        $data = [
            'components' => [
                // Navigation
                C::navigation()
                    ->prev('← Prev', route('flexible.with-nav', ['date' => $prevDay->toDateString()]))
                    ->center('Today', route('flexible.with-nav', ['date' => $today->toDateString()]))
                    ->next('Next →', route('flexible.with-nav', ['date' => $nextDay->toDateString()]))
                    ->ariaLabel('Date navigation')
                    ->build(),
                
                // Title
                C::title(
                    $selectedDate->isToday() ? 'Today' : $selectedDate->format('M j, Y'),
                    $selectedDate->format('l, F j, Y')
                )->build(),
                
                // Messages (from session)
                C::messages()
                    ->success('Workout logged successfully!')
                    ->info('You have 2 exercises remaining today.')
                    ->build(),
                
                // Summary
                C::summary()
                    ->item('total', 1250, 'Total Calories')
                    ->item('completed', 3, 'Exercises Done')
                    ->item('average', 85, 'Avg Weight')
                    ->item('today', 12, 'Today\'s Sets')
                    ->build(),
                
                // Add button
                C::button('Add Exercise')
                    ->ariaLabel('Add new exercise')
                    ->build(),
                
                // Item list
                C::itemList()
                    ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
                    ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
                    ->item('ex-3', 'Deadlift', '#', 'Available', 'regular', 3)
                    ->filterPlaceholder('Search exercises...')
                    ->createForm('#', 'exercise_name', ['date' => $selectedDate->toDateString()])
                    ->build(),
                
                // Form (using 'primary' type for blue border)
                C::form('workout-1', 'Bench Press')
                    ->type('primary')  // Generic type: primary, success, warning, secondary, danger, info
                    ->formAction('#')
                    ->deleteAction('#')
                    ->message('info', '3 sets of 10 reps', 'Last time:')
                    ->message('tip', 'Try to increase by 5 lbs today', 'Goal:')
                    ->numericField('weight', 'Weight (lbs):', 135, 5, 45, 500)
                    ->numericField('reps', 'Reps:', 10, 1, 1, 50)
                    ->numericField('sets', 'Sets:', 3, 1, 1, 10)
                    ->commentField('Notes:', 'How did it feel?', 'Felt strong!')
                    ->hiddenField('date', $selectedDate->toDateString())
                    ->submitButton('Log Bench Press')
                    ->build(),
                
                // Items (previously logged items)
                C::items()
                    ->item(1, 'Morning Workout', 25, '#', '#')
                        ->message('neutral', 'Great session!', 'Comment:')
                        ->freeformText('Felt great today! Energy levels were high.')
                        ->add()
                    ->item(2, 'Afternoon Session', 15, '#', '#')
                        ->message('neutral', 'Quick session', 'Comment:')
                        ->add()
                    ->emptyMessage('No items yet.')
                    ->build(),
            ]
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 2: No navigation (standalone form)
     */
    public function withoutNavigation(Request $request)
    {
        $data = [
            'components' => [
                // Just a title
                C::title('Quick Workout Log')->build(),
                
                // Single form
                C::form('quick-log', 'Log Your Workout')
                    ->type('exercise')
                    ->formAction('#')
                    ->numericField('sets', 'Sets:', 3, 1, 1)
                    ->numericField('reps', 'Reps:', 10, 1, 1)
                    ->commentField('Notes:', 'Add notes...', '')
                    ->submitButton('Save Workout')
                    ->build(),
            ]
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 3: Multiple forms, no other sections
     */
    public function multipleForms(Request $request)
    {
        $data = [
            'components' => [
                C::title('Today\'s Workout')->build(),
                
                C::messages()
                    ->info('Complete all exercises below')
                    ->build(),
                
                C::form('ex-1', 'Bench Press')
                    ->type('primary')  // Blue border
                    ->formAction('#')
                    ->numericField('weight', 'Weight:', 135, 5, 45)
                    ->numericField('reps', 'Reps:', 10, 1, 1)
                    ->submitButton('Log')
                    ->build(),
                
                C::form('ex-2', 'Squats')
                    ->type('success')  // Green border
                    ->formAction('#')
                    ->numericField('weight', 'Weight:', 185, 5, 45)
                    ->numericField('reps', 'Reps:', 8, 1, 1)
                    ->submitButton('Log')
                    ->build(),
                
                C::form('ex-3', 'Deadlift')
                    ->type('warning')  // Yellow/orange border
                    ->formAction('#')
                    ->numericField('weight', 'Weight:', 225, 5, 45)
                    ->numericField('reps', 'Reps:', 5, 1, 1)
                    ->submitButton('Log')
                    ->build(),
            ]
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 4: Custom component order
     */
    public function customOrder(Request $request)
    {
        $data = [
            'components' => [
                // Messages first
                C::messages()
                    ->warning('Please complete your profile')
                    ->build(),
                
                // Then title
                C::title('Profile Setup')->build(),
                
                // Then form
                C::form('profile', 'Your Information')
                    ->formAction('#')
                    ->numericField('age', 'Age:', 25, 1, 13, 120)
                    ->numericField('weight', 'Weight (lbs):', 150, 0.1, 50, 500)
                    ->submitButton('Save Profile')
                    ->build(),
                
                // Summary at the end
                C::summary()
                    ->item('completion', '25%', 'Profile Complete')
                    ->build(),
            ]
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 5: Multiple buttons
     */
    public function multipleButtons(Request $request)
    {
        $data = [
            'components' => [
                C::title('Quick Actions')->build(),
                
                // Multiple buttons
                C::button('Add Exercise')
                    ->ariaLabel('Add new exercise')
                    ->build(),
                
                C::button('Add Food')
                    ->ariaLabel('Add new food item')
                    ->cssClass('btn-primary btn-success')
                    ->build(),
                
                C::button('Add Measurement')
                    ->ariaLabel('Add new measurement')
                    ->cssClass('btn-primary')
                    ->build(),
                
                C::messages()
                    ->info('Choose an action above to get started')
                    ->build(),
            ]
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
