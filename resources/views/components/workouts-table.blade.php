@props(['liftLogs'])

<table class="log-entries-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="select-all-lift-logs"></th>
            <th>Date</th>
            <th>Exercise</th>
            <th>Weight (reps x rounds)</th>
            <th>1RM (est.)</th>
            <th class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Comments</th>
            <th class="actions-column">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($liftLogs as $liftLog)
            <tr>
                <td><input type="checkbox" name="lift_log_ids[]" value="{{ $liftLog->id }}" class="lift-log-checkbox"></td>
                <td>{{ $liftLog->logged_at->format('m/d') }}</td>
                <td><a href="{{ route('exercises.show-logs', $liftLog->exercise) }}">{{ $liftLog->exercise->title }}</a></td>
                <td>
                    @if ($liftLog->exercise->exercise_type === 'cardio')
                        @php
                            try {
                                $strategy = $liftLog->exercise->getTypeStrategy();
                                if (method_exists($strategy, 'formatCompleteDisplay')) {
                                    $cardioDisplay = $strategy->formatCompleteDisplay($liftLog);
                                } else {
                                    $distance = $liftLog->display_reps;
                                    $rounds = $liftLog->display_rounds;
                                    $roundsText = $rounds == 1 ? 'round' : 'rounds';
                                    $cardioDisplay = "{$distance}m × {$rounds} {$roundsText}";
                                }
                            } catch (\Exception $e) {
                                $cardioDisplay = $liftLog->display_reps . 'm × ' . $liftLog->display_rounds . ' rounds';
                            }
                        @endphp
                        <span style="font-weight: bold; font-size: 1.2em;">{{ $cardioDisplay }}</span>
                    @elseif ($liftLog->exercise->exercise_type === 'bodyweight')
                        <span style="font-weight: bold; font-size: 1.2em;">Bodyweight</span><br>
                        {{ $liftLog->display_reps }} x {{ $liftLog->display_rounds }}
                        @if ($liftLog->display_weight > 0)
                            <br>+ {{ $liftLog->display_weight }} lbs
                        @endif
                    @else
                        <span style="font-weight: bold; font-size: 1.2em;">{{ $liftLog->display_weight }} lbs</span><br>
                        {{ $liftLog->display_reps }} x {{ $liftLog->display_rounds }}
                    @endif
                </td>
                <td>
                    @if ($liftLog->exercise->exercise_type === 'cardio')
                        N/A (Cardio)
                    @elseif ($liftLog->exercise->exercise_type === 'bodyweight')
                        {{ round($liftLog->one_rep_max) }} lbs (est. incl. BW)
                    @else
                        {{ round($liftLog->one_rep_max) }} lbs
                    @endif
                </td>
                <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $liftLog->comments }}">{{ $liftLog->comments }}</td>
                <td class="actions-column">
                    <div style="display: flex; gap: 5px;">
                        <a href="{{ route('lift-logs.edit', $liftLog->id) }}" class="button edit"><i class="fa-solid fa-pencil"></a>
                        <form action="{{ route('lift-logs.destroy', $liftLog->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this lift log?');"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="7" style="text-align:left; font-weight:normal;">
                <form action="{{ route('lift-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected lift logs?');" style="display:inline;">
                    @csrf
                    <button type="submit" class="button delete"><i class="fa-solid fa-trash"></i> Delete Selected</button>
                </form>
            </th>
        </tr>
    </tfoot>
</table>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllLiftLogs = document.getElementById('select-all-lift-logs');
        if (selectAllLiftLogs) {
            selectAllLiftLogs.addEventListener('change', function(e) {
                document.querySelectorAll('.lift-log-checkbox').forEach(function(checkbox) {
                    checkbox.checked = e.target.checked;
                });
            });
        }

        const deleteSelectedForm = document.getElementById('delete-selected-form');
        if (deleteSelectedForm) {
            deleteSelectedForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var form = e.target;
                var checkedLogs = document.querySelectorAll('.lift-log-checkbox:checked');

                if (checkedLogs.length === 0) {
                    alert('Please select at least one lift log to delete.');
                    return;
                }

                checkedLogs.forEach(function(checkbox) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'lift_log_ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });

                form.submit();
            });
        }
    });
</script>
