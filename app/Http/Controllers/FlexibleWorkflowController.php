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
                
                // Add button (starts visible by default)
                C::button('Add Exercise')
                    ->ariaLabel('Add new exercise')
                    ->addClass('btn-add-item')
                    ->build(),
                
                // Item list (starts collapsed by default)
                // To start expanded, add: ->initialState('expanded')
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
                    ->textareaField('notes', 'Notes:', 'Felt strong!', 'How did it feel?')
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
            ],
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
                    ->textareaField('notes', 'Notes:', '', 'Add notes...')
                    ->submitButton('Save Workout')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 3: Multiple forms, no other sections
     * Demonstrates autoscroll enabled
     */
    public function multipleForms(Request $request)
    {
        $data = [
            'components' => [
                C::title('Today\'s Workout')->build(),
                
                C::messages()
                    ->info('Complete all exercises below')
                    ->success('Autoscroll is ON - page will scroll to first form', 'Demo:')
                    ->build(),
                
                C::form('ex-4', 'Exercise Notes')
                    ->type('info')  // Light blue border - demonstrates text field
                    ->formAction('#')
                    ->textField('exercise_name', 'Exercise Name:', 'Pull-ups', 'Enter exercise name')
                    ->numericField('sets', 'Sets:', 3, 1, 1)
                    ->textField('notes', 'Quick Notes:', '', 'Any observations?')
                    ->submitButton('Log')
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
            ],
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
            ],
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
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 6: Tabular CRUD list with two-level rows
     * Demonstrates the table component with sub-items
     */
    public function tableExample(Request $request)
    {
        $data = [
            'components' => [
                C::title('My Workouts', 'Manage your workout routines')->build(),
                
                C::messages()
                    ->info('Tap any row to edit, or use the delete button to remove.')
                    ->success('Expandable sub-items! Click chevron to expand/collapse.', 'Feature:')
                    ->tip('Last row shows always-visible sub-items (no collapse).', 'Note:')
                    ->build(),
                
                // Table with sample data and sub-items
                C::table()
                    ->row(
                        1,
                        'Morning Cardio',
                        '30 minutes • 3x per week',
                        'Last completed: 2 days ago'
                    )
                    ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        11,
                        'Running',
                        '15 minutes',
                        'Warm-up pace'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        12,
                        'Jump Rope',
                        '10 minutes',
                        '3 sets of 200 jumps'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        2,
                        'Upper Body Strength',
                        'Bench Press, Rows, Shoulder Press',
                        '45 minutes • Mon, Wed, Fri'
                    )
                    ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        21,
                        'Bench Press',
                        '4 sets × 8 reps',
                        '185 lbs'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        22,
                        'Barbell Rows',
                        '4 sets × 10 reps',
                        '135 lbs'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        23,
                        'Shoulder Press',
                        '3 sets × 12 reps',
                        '95 lbs'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        3,
                        'Leg Day',
                        'Squats, Deadlifts, Lunges',
                        null
                    )
                    ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        31,
                        'Squats',
                        '5 sets × 5 reps',
                        '225 lbs'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        32,
                        'Deadlifts',
                        '3 sets × 5 reps',
                        '275 lbs'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        4,
                        'Core & Flexibility',
                        '20 minutes daily',
                        'Planks, stretches, yoga poses'
                    )
                    ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->add()
                    ->row(
                        5,
                        'Transparent Button + Large Row Title',
                        'Shows transparent edit button',
                        'White icon with subtle hover effect'
                    )
                    ->titleClass('cell-title-large')
                    ->linkAction('fa-pencil', route('flexible.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->add()
                    ->row(
                        6,
                        'Quick Stretches',
                        'Always visible sub-items',
                        'No expand/collapse button'
                    )
                    ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        61,
                        'Neck Rolls',
                        '2 minutes',
                        'Gentle circular motion'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        62,
                        'Shoulder Shrugs',
                        '1 minute',
                        '10 reps'
                    )
                    ->linkAction('fa-play', route('flexible.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->collapsible(false)
                    ->add()
                    ->emptyMessage('No workouts yet. Create your first routine!')
                    ->confirmMessage('deleteItem', 'Are you sure you want to delete this workout?')
                    ->ariaLabel('Workout routines')
                    ->build(),
                
                C::button('Add New Workout')
                    ->ariaLabel('Create a new workout routine')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Table with custom actions (reorder buttons)
     */
    public function tableWithReorder()
    {
        $data = [
            'components' => [
                C::title('Table with Reorder Actions')
                    ->subtitle('Custom action buttons for reordering')
                    ->build(),
                
                C::messages()
                    ->info('Use up/down arrows to reorder items. This example shows custom actions.')
                    ->build(),
                
                // Table with custom actions
                C::table()
                    ->row(1, 'First Exercise', 'Bench Press')
                        ->linkAction('fa-arrow-up', route('flexible.table-example'), 'Move up', 'btn-disabled')
                        ->linkAction('fa-arrow-down', route('flexible.table-example'), 'Move down')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(2, 'Second Exercise', 'Squat')
                        ->linkAction('fa-arrow-up', route('flexible.table-example'), 'Move up')
                        ->linkAction('fa-arrow-down', route('flexible.table-example'), 'Move down')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(3, 'Third Exercise', 'Deadlift')
                        ->linkAction('fa-arrow-up', route('flexible.table-example'), 'Move up')
                        ->linkAction('fa-arrow-down', route('flexible.table-example'), 'Move down', 'btn-disabled')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->confirmMessage('deleteItem', 'Are you sure you want to delete this exercise?')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 7: Multiple item lists on one page
     * Demonstrates independent item selection lists for different categories
     */
    public function multipleItemLists(Request $request)
    {
        $data = [
            'components' => [
                C::title('Multiple Item Lists', 'Each list operates independently')->build(),
                
                C::messages()
                    ->info('This page has two separate item lists - one for exercises and one for meals')
                    ->tip('Exercise list starts collapsed, meal list starts expanded', 'Note:')
                    ->build(),
                
                // First list: Exercises (collapsed by default)
                C::button('Add Exercise')
                    ->ariaLabel('Add new exercise')
                    ->addClass('btn-add-item')
                    ->build(),
                
                C::itemList()
                    ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
                    ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
                    ->item('ex-3', 'Deadlift', '#', 'Available', 'regular', 3)
                    ->filterPlaceholder('Search exercises...')
                    ->createForm('#', 'exercise_name')
                    ->build(),
                
                // Separator
                C::messages()
                    ->info('Second list below - starts expanded')
                    ->build(),
                
                // Second list: Meals (expanded by default)
                C::button('Add Meal')
                    ->ariaLabel('Add new meal')
                    ->addClass('btn-add-item')
                    ->initialState('hidden')
                    ->build(),
                
                C::itemList()
                    ->item('meal-1', 'Chicken & Rice', '#', 'Favorite', 'in-program', 4)
                    ->item('meal-2', 'Protein Shake', '#', 'Recent', 'recent', 1)
                    ->item('meal-3', 'Oatmeal', '#', 'Available', 'regular', 3)
                    ->filterPlaceholder('Search meals...')
                    ->createForm('#', 'meal_name')
                    ->initialState('expanded')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 8: Title with back button
     * Demonstrates the back button feature in title component
     */
    public function titleWithBackButton(Request $request)
    {
        $data = [
            'components' => [
                C::title('Exercise Details', 'View and edit exercise information')
                    ->backButton('fa-arrow-left', route('flexible.with-nav'), 'Back to main page')
                    ->build(),
                
                C::messages()
                    ->info('Notice the back button on the left side of the title')
                    ->tip('Title and subtitle remain left-aligned', 'Layout:')
                    ->build(),
                
                C::form('exercise-details', 'Bench Press')
                    ->type('primary')
                    ->formAction('#')
                    ->numericField('weight', 'Weight (lbs):', 135, 5, 45, 500)
                    ->numericField('reps', 'Reps:', 10, 1, 1, 50)
                    ->numericField('sets', 'Sets:', 3, 1, 1, 10)
                    ->textareaField('notes', 'Notes:', '', 'How did it feel?')
                    ->submitButton('Save')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example 9: Expanded item list (initial state demo)
     * Shows the item list expanded by default with the button hidden
     */
    public function expandedList(Request $request)
    {
        $data = [
            'components' => [
                C::title('Add Exercise', 'Select from your exercises or create a new one')->build(),
                
                C::messages()
                    ->info('The item list starts expanded - perfect for quick-add workflows')
                    ->tip('The "Add Exercise" button is hidden initially', 'Note:')
                    ->build(),
                
                // Button starts hidden
                C::button('Add Exercise')
                    ->ariaLabel('Add new exercise')
                    ->addClass('btn-add-item')
                    ->initialState('hidden')
                    ->build(),
                
                // Item list starts expanded
                C::itemList()
                    ->item('ex-1', 'Bench Press', '#', 'In Program', 'in-program', 4)
                    ->item('ex-2', 'Squats', '#', 'Recent', 'recent', 1)
                    ->item('ex-3', 'Deadlift', '#', 'Available', 'regular', 3)
                    ->item('ex-4', 'Overhead Press', '#', 'Available', 'regular', 3)
                    ->item('ex-5', 'Barbell Row', '#', 'Recent', 'recent', 1)
                    ->filterPlaceholder('Search exercises...')
                    ->createForm('#', 'exercise_name')
                    ->initialState('expanded')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example: Table with initial expanded state
     * Demonstrates rows that start expanded but remain collapsible
     */
    public function tableInitialExpanded(Request $request)
    {
        $expandId = $request->query('id'); // Optional: expand specific row
        
        $data = [
            'components' => [
                C::title('Table Initial State Demo')
                    ->subtitle('Second row starts expanded')
                    ->build(),
                
                C::messages()
                    ->info('Row 2 starts expanded but can still be collapsed')
                    ->tip('Try ?id=1 or ?id=3 to expand different rows', 'URL:')
                    ->build(),
                
                C::table()
                    ->row(1, 'First Template', '2 exercises: Squat, Press')
                        ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(11, 'Squat', '3 sets × 5 reps', '225 lbs')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(12, 'Press', '3 sets × 5 reps', '135 lbs')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 1 ? 'expanded' : 'collapsed')
                        ->add()
                    ->row(2, 'Second Template', '3 exercises: Bench, Row, Deadlift')
                        ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(21, 'Bench Press', '4 sets × 8 reps', '185 lbs')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(22, 'Barbell Row', '4 sets × 10 reps', '135 lbs')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(23, 'Deadlift', '3 sets × 5 reps', '275 lbs')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 2 ? 'expanded' : ($expandId ? 'collapsed' : 'expanded'))
                        ->add()
                    ->row(3, 'Third Template', '1 exercise: Cardio')
                        ->linkAction('fa-edit', route('flexible.table-example'), 'Edit')
                        ->formAction('fa-trash', route('flexible.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(31, 'Running', '30 minutes', 'Moderate pace')
                            ->linkAction('fa-play', route('flexible.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 3 ? 'expanded' : 'collapsed')
                        ->add()
                    ->confirmMessage('deleteItem', 'Are you sure?')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
