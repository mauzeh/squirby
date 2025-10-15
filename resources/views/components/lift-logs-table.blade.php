@props(['liftLogs', 'hideExerciseColumn' => false])

<table class="log-entries-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="select-all-lift-logs"></th>
            @if($hideExerciseColumn)
                <th>Date</th>
            @else
                <th class="hide-on-mobile">Date</th>
            @endif
            @unless($hideExerciseColumn)
                <th>Exercise</th>
            @endunless
            <th class="hide-on-mobile">Weight (reps x rounds)</th>
            @php
                $hasNonBandedLiftLogs = $liftLogs->contains(function ($liftLog) { return !$liftLog->exercise->band_type; });
            @endphp
            @if($hasNonBandedLiftLogs)
                <th class="hide-on-mobile">1RM (est.)</th>
            @endif
            <th class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Comments</th>
            <th class="actions-column">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($liftLogs as $liftLog)
            <tr>
                <td><input type="checkbox" name="lift_log_ids[]" value="{{ $liftLog->id }}" class="lift-log-checkbox"></td>
                @if($hideExerciseColumn)
                    <td>
                        {{ $liftLog->logged_at->format('m/d') }}
                        <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                            <x-lift-weight-display :liftLog="$liftLog" /> (<x-lift-reps-sets-display :reps="$liftLog->display_reps" :sets="$liftLog->display_rounds" />)
                            @if (!$liftLog->exercise->band_type)
                                <br><i>1RM: {{ round($liftLog->one_rep_max) }} lbs</i>
                            @endif
                        </div>
                    </td>
                @endunless
                <td class="hide-on-mobile">
                    <x-lift-weight-display :liftLog="$liftLog" /><br>
                    <x-lift-reps-sets-display :reps="$liftLog->display_reps" :sets="$liftLog->display_rounds" />
                </td>
            @if($hasNonBandedLiftLogs)
                <td class="hide-on-mobile">
                    @if (!$liftLog->exercise->band_type)
                        @if ($liftLog->exercise->is_bodyweight)
                            {{ round($liftLog->one_rep_max) }} lbs (est. incl. BW)
                        @else
                            {{ round($liftLog->one_rep_max) }} lbs
                        @endif
                    @endif
                </td>
            @endif
                <td class="hide-on-mobile" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $liftLog->comments }}">{{ $liftLog->comments }}</td>
                <td class="actions-column">
                    <div style="display: flex; gap: 5px;">
                        <a href="{{ route('lift-logs.edit', $liftLog->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
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
            <th colspan="{{ $hideExerciseColumn ? 6 : 7 }}" style="text-align:left; font-weight:normal;">
                <form action="{{ route('lift-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected lift logs?');" style="display:inline;">
                    @csrf
                    <button type="submit" class="button delete">Delete Selected</button>
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