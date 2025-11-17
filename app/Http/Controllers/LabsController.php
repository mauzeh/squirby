<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ComponentBuilder as C;

/**
 * Labs Controller
 * 
 * Example controller showing how to use the component-based mobile entry view.
 * This demonstrates building UIs without hardcoded sections - everything is optional.
 */
class LabsController extends Controller
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
                    ->prev('← Prev', route('labs.with-nav', ['date' => $prevDay->toDateString()]))
                    ->center('Today', route('labs.with-nav', ['date' => $today->toDateString()]))
                    ->next('Next →', route('labs.with-nav', ['date' => $nextDay->toDateString()]))
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
                    ->createForm('#', 'exercise_name', ['date' => $selectedDate->toDateString()], 'Create "{term}"')
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
                    ->tip('Row 7 shows compact buttons (75% size) using ->compact()', 'New:')
                    ->tip('Sub-items can have inline messages in different styles!', 'Note:')
                    ->tip('Sub-items with only ONE action are fully clickable!', 'NEW:')
                    ->build(),
                
                // Table with sample data and sub-items
                C::table()
                    ->row(
                        1,
                        'Morning Cardio',
                        '30 minutes • 3x per week',
                        'Last completed: 2 days ago'
                    )
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        11,
                        'Running',
                        '15 minutes',
                        'Warm-up pace'
                    )
                    ->message('success', '3.2 miles completed!', 'Completed:')
                    ->linkAction('fa-pencil', route('labs.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        12,
                        'Jump Rope',
                        '10 minutes',
                        '3 sets of 200 jumps'
                    )
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        2,
                        'Upper Body Strength',
                        'Bench Press, Rows, Shoulder Press',
                        '45 minutes • Mon, Wed, Fri'
                    )
                    ->linkAction('fa-info', route('labs.table-example'), 'View details', 'btn-info-circle')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        21,
                        'Bench Press',
                        'Felt strong today!',
                        null
                    )
                    ->message('success', '185 lbs × 8 reps × 4 sets', 'Completed:')
                    ->linkAction('fa-pencil', route('labs.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        22,
                        'Barbell Rows',
                        null,
                        null
                    )
                    ->message('info', '135 lbs × 10 reps × 4 sets', 'Last time:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        23,
                        'Shoulder Press',
                        null,
                        null
                    )
                    ->message('tip', 'Try 100 lbs × 12 reps × 3 sets', 'Suggestion:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        3,
                        'Leg Day',
                        'Squats, Deadlifts, Lunges',
                        null
                    )
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        31,
                        'Squats',
                        'Knees felt a bit sore',
                        null
                    )
                    ->message('warning', '225 lbs × 5 reps × 5 sets', 'Completed:')
                    ->message('neutral', 'Consider reducing weight next time', 'Note:')
                    ->linkAction('fa-pencil', route('labs.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        32,
                        'Deadlifts',
                        null,
                        null
                    )
                    ->message('error', 'Form breakdown on last set', 'Issue:')
                    ->message('tip', 'Focus on form before adding weight', 'Advice:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->add()
                    ->row(
                        4,
                        'Core & Flexibility',
                        '20 minutes daily',
                        'Planks, stretches, yoga poses'
                    )
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->add()
                    ->row(
                        5,
                        'Transparent Button + Large Row Title',
                        'Shows transparent edit button',
                        'White icon with subtle hover effect'
                    )
                    ->titleClass('cell-title-large')
                    ->linkAction('fa-pencil', route('labs.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->add()
                    ->row(
                        6,
                        'Quick Stretches',
                        'Always visible sub-items',
                        'No expand/collapse button'
                    )
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        61,
                        'Neck Rolls',
                        'Gentle circular motion',
                        null
                    )
                    ->message('success', '2 minutes completed', 'Done:')
                    ->linkAction('fa-pencil', route('labs.table-example'), 'Edit', 'btn-transparent')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->subItem(
                        62,
                        'Shoulder Shrugs',
                        null,
                        null
                    )
                    ->message('info', '10 reps recommended', 'Target:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                    ->add()
                    ->collapsible(false)
                    ->add()
                    ->row(
                        7,
                        'Compact Button Demo',
                        'Shows 75% sized buttons',
                        'Useful for less prominent actions'
                    )
                    ->linkAction('fa-info', route('labs.table-example'), 'Info', 'btn-info-circle')
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->compact()
                    ->add()
                    ->row(
                        8,
                        'Quick Workout Templates',
                        'Tap any exercise to start logging',
                        'Single-action sub-items are fully clickable'
                    )
                    ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                    ->formAction('fa-trash', route('labs.table-example'), 'DELETE', ['redirect' => 'table'], 'Delete', 'btn-danger', true)
                    ->subItem(
                        81,
                        'Push-ups',
                        '3 sets × 15 reps',
                        'Tap anywhere to log'
                    )
                    ->message('info', 'Last time: 45 total reps', 'History:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->add()
                    ->subItem(
                        82,
                        'Pull-ups',
                        '3 sets × 8 reps',
                        'Tap anywhere to log'
                    )
                    ->message('tip', 'Try to beat your record!', 'Goal:')
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->add()
                    ->subItem(
                        83,
                        'Plank Hold',
                        '3 sets × 60 seconds',
                        'Tap anywhere to log'
                    )
                    ->linkAction('fa-play', route('labs.table-example'), 'Log now', 'btn-log-now')
                    ->add()
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
                        ->linkAction('fa-arrow-up', route('labs.table-example'), 'Move up', 'btn-disabled')
                        ->linkAction('fa-arrow-down', route('labs.table-example'), 'Move down')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(2, 'Second Exercise', 'Squat')
                        ->linkAction('fa-arrow-up', route('labs.table-example'), 'Move up')
                        ->linkAction('fa-arrow-down', route('labs.table-example'), 'Move down')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(3, 'Third Exercise', 'Deadlift')
                        ->linkAction('fa-arrow-up', route('labs.table-example'), 'Move up')
                        ->linkAction('fa-arrow-down', route('labs.table-example'), 'Move down', 'btn-disabled')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
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
                    ->createForm('#', 'exercise_name', [], 'Create "{term}"')
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
                    ->createForm('#', 'meal_name', [], 'Create "{term}"')
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
                    ->backButton('fa-arrow-left', route('labs.with-nav'), 'Back to main page')
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
                    ->createForm('#', 'exercise_name', [], 'Create "{term}"')
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
                        ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(11, 'Squat', '3 sets × 5 reps', '225 lbs')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(12, 'Press', '3 sets × 5 reps', '135 lbs')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 1 ? 'expanded' : 'collapsed')
                        ->add()
                    ->row(2, 'Second Template', '3 exercises: Bench, Row, Deadlift')
                        ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(21, 'Bench Press', '4 sets × 8 reps', '185 lbs')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(22, 'Barbell Row', '4 sets × 10 reps', '135 lbs')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->subItem(23, 'Deadlift', '3 sets × 5 reps', '275 lbs')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 2 ? 'expanded' : ($expandId ? 'collapsed' : 'expanded'))
                        ->add()
                    ->row(3, 'Third Template', '1 exercise: Cardio')
                        ->linkAction('fa-edit', route('labs.table-example'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-example'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(31, 'Running', '30 minutes', 'Moderate pace')
                            ->linkAction('fa-play', route('labs.table-example'), 'Log', 'btn-log-now')
                            ->add()
                        ->initialState($expandId == 3 ? 'expanded' : 'collapsed')
                        ->add()
                    ->confirmMessage('deleteItem', 'Are you sure?')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
    
    /**
     * Example: Table with checkbox and bulk selection support
     * Demonstrates how to implement bulk selection for table rows
     */
    public function tableBulkSelection(Request $request)
    {
        // Handle bulk delete action
        if ($request->isMethod('post') && $request->has('selected_ids')) {
            $selectedIds = $request->input('selected_ids', []);
            $count = count($selectedIds);
            
            // In a real app, you'd delete the items here
            // WorkoutTemplate::whereIn('id', $selectedIds)->delete();
            
            $message = $count === 1 
                ? 'Successfully deleted 1 workout template.'
                : "Successfully deleted {$count} workout templates.";
            
            return redirect()->route('labs.table-bulk-selection')->with('success', $message);
        }
        
        $messagesBuilder = C::messages()
            ->info('Select multiple items using checkboxes, then use "Delete Selected" button.')
            ->tip('This demonstrates bulk selection with mobile-friendly badges', 'Demo:')
            ->tip('Badges show metadata like dates, frequency, and status', 'Note:')
            ->tip('Row 6 shows text wrapping - long content wraps instead of truncating', 'New:')
            ->tip('Rows have spacing between them for a card-like appearance', 'Layout:');
        
        if (session('success')) {
            $messagesBuilder->success(session('success'), 'Success:');
        }
        
        $data = [
            'components' => [
                C::title('Workout Templates', 'Manage your workout templates with bulk actions')->build(),
                
                $messagesBuilder->build(),
                
                // Select all control
                C::selectAllControl('select-all-templates', 'Select All')->build(),
                
                // Table with checkboxes and badges
                C::table()
                    ->row(1, '5x5 Strength Program', 'Last workout: Squat day', null)
                        ->checkbox(true)
                        ->badge('Today', 'success')
                        ->badge('5 x 5', 'neutral')
                        ->badge('225 lbs', 'dark', true)  // Emphasized badge for weight
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(11, 'Squat', '5 sets × 5 reps', 'Progressive overload')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->subItem(12, 'Bench Press', '5 sets × 5 reps', 'Progressive overload')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->subItem(13, 'Barbell Row', '5 sets × 5 reps', 'Progressive overload')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->add()
                    ->row(2, 'Upper Body Hypertrophy', 'Last workout: Bench press', null)
                        ->checkbox(true)
                        ->badge('Yesterday', 'warning')
                        ->badge('4 x 10', 'neutral')
                        ->badge('185 lbs', 'dark', true)  // Emphasized badge for weight
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(21, 'Bench Press', '4 sets × 8-12 reps', 'Hypertrophy range')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->subItem(22, 'Dumbbell Rows', '4 sets × 10-15 reps', 'Each arm')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->add()
                    ->row(3, 'Lower Body Power', 'Squat, Deadlift, Lunges', null)
                        ->checkbox(true)
                        ->badge('3 days ago', 'info')
                        ->badge('3 exercises', 'neutral')
                        ->badge('Heavy', 'danger')
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->subItem(31, 'Back Squat', '5 sets × 3 reps', 'Heavy weight')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->subItem(32, 'Deadlift', '5 sets × 3 reps', 'Heavy weight')
                            ->linkAction('fa-play', route('labs.table-bulk-selection'), 'Log now', 'btn-log-now')
                            ->add()
                        ->add()
                    ->row(4, 'Cardio & Conditioning', 'Running, Jump Rope, Burpees', null)
                        ->checkbox(true)
                        ->badge('11/10', 'neutral')
                        ->badge('Daily', 'success')
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(5, 'Core & Flexibility', 'Planks, Stretches, Yoga', null)
                        ->checkbox(true)
                        ->badge('11/8', 'neutral')
                        ->badge('Daily', 'success')
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->add()
                    ->row(6, 'Text Wrapping Demo', 'This row demonstrates the wrapText() feature which allows longer text content to wrap to multiple lines instead of being truncated with an ellipsis. This is useful for notes, comments, or descriptions that need to be fully visible.', 'Notice how this text wraps naturally')
                        ->checkbox(true)
                        ->badge('Demo', 'info')
                        ->linkAction('fa-edit', route('labs.table-bulk-selection'), 'Edit')
                        ->formAction('fa-trash', route('labs.table-bulk-selection'), 'DELETE', [], 'Delete', 'btn-danger', true)
                        ->wrapText()
                        ->add()
                    ->emptyMessage('No workout templates yet. Create your first one!')
                    ->confirmMessage('deleteItem', 'Are you sure you want to delete this template?')
                    ->ariaLabel('Workout templates')
                    ->spacedRows()
                    ->build(),
                
                // Bulk action form
                C::bulkActionForm('bulk-delete-form', route('labs.table-bulk-selection'), 'Delete Selected')
                    ->confirmMessage('Are you sure you want to delete :count template(s)?')
                    ->ariaLabel('Delete selected templates')
                    ->build(),
                
                C::button('Add New Template')
                    ->ariaLabel('Create a new workout template')
                    ->build(),
            ],
        ];
        
        return view('mobile-entry.flexible', compact('data'));
    }
}
