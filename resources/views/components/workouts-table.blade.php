@props(['workouts'])

<table class="log-entries-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="select-all-workouts"></th>
            <th>Date</th>
            <th>Exercise</th>
            <th>Weight (reps x rounds)</th>
            <th>1RM (est.)</th>
            <th class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Comments</th>
            <th class="actions-column">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($workouts as $workout)
            <tr>
                <td><input type="checkbox" name="workout_ids[]" value="{{ $workout->id }}" class="workout-checkbox"></td>
                <td>{{ $workout->logged_at->format('m/d') }}</td>
                <td><a href="{{ route('exercises.show-logs', $workout->exercise) }}">{{ $workout->exercise->title }}</a></td>
                <td>
                    @if ($workout->exercise->is_bodyweight)
                        <span style="font-weight: bold; font-size: 1.2em;">Bodyweight</span><br>
                        {{ $workout->display_reps }} x {{ $workout->display_rounds }}
                        @if ($workout->display_weight > 0)
                            <br>+ {{ $workout->display_weight }} lbs
                        @endif
                    @else
                        <span style="font-weight: bold; font-size: 1.2em;">{{ $workout->display_weight }} lbs</span><br>
                        {{ $workout->display_reps }} x {{ $workout->display_rounds }}
                    @endif
                </td>
                <td>
                    @if ($workout->exercise->is_bodyweight)
                        {{ round($workout->one_rep_max) }} lbs (est. incl. BW)
                    @else
                        {{ round($workout->one_rep_max) }} lbs
                    @endif
                </td>
                <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $workout->comments }}">{{ $workout->comments }}</td>
                <td class="actions-column">
                    <div style="display: flex; gap: 5px;">
                        <a href="{{ route('workouts.edit', $workout->id) }}" class="button edit">Edit</a>
                        <form action="{{ route('workouts.destroy', $workout->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this workout?');">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="7" style="text-align:left; font-weight:normal;">
                <form action="{{ route('workouts.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected workouts?');" style="display:inline;">
                    @csrf
                    <button type="submit" class="button delete">Delete Selected</button>
                </form>
            </th>
        </tr>
    </tfoot>
</table>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllWorkouts = document.getElementById('select-all-workouts');
        if (selectAllWorkouts) {
            selectAllWorkouts.addEventListener('change', function(e) {
                document.querySelectorAll('.workout-checkbox').forEach(function(checkbox) {
                    checkbox.checked = e.target.checked;
                });
            });
        }

        const deleteSelectedForm = document.getElementById('delete-selected-form');
        if (deleteSelectedForm) {
            deleteSelectedForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var form = e.target;
                var checkedLogs = document.querySelectorAll('.workout-checkbox:checked');

                if (checkedLogs.length === 0) {
                    alert('Please select at least one workout to delete.');
                    return;
                }

                checkedLogs.forEach(function(checkbox) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'workout_ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });

                form.submit();
            });
        }
    });
</script>
