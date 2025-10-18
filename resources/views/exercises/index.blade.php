@extends('app')

@section('content')
    <div class="container">
        <h1>Exercises</h1>
        <a href="{{ route('exercises.create') }}" class="button create">Add Exercise</a>
        
        @if (session('success'))
            <div class="container success-message-box">
                {!! session('success') !!}
            </div>
        @endif
        @if (session('error'))
            <div class="container error-message-box">
                {{ session('error') }}
            </div>
        @endif

        @if ($exercises->isEmpty())
            <p>No exercises found. Add one to get started!</p>
        @else
            <table class="log-entries-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-exercises"></th>
                        <th>Title</th>
                        <th class="hide-on-mobile">Description</th>
                        <th class="hide-on-mobile">Type</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($exercises as $exercise)
                        <tr>
                            <td><input type="checkbox" name="exercise_ids[]" value="{{ $exercise->id }}" class="exercise-checkbox" {{ !$exercise->canBeDeletedBy(auth()->user()) ? 'disabled' : '' }}></td>
                            <td>
                                <div style="display: flex; align-items: flex-start; gap: 8px;">
                                    @if($exercise->isGlobal())
                                        <span class="badge" style="background-color: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7em; white-space: nowrap; display: inline-block; flex-shrink: 0;">Everyone</span>
                                    @else
                                        <span class="badge" style="background-color: #FFC107; color: black; padding: 2px 6px; border-radius: 3px; font-size: 0.7em; white-space: nowrap; display: inline-block; flex-shrink: 0;">{{ $exercise->user_id === auth()->id() ? 'You' : $exercise->user->name }}</span>
                                    @endif
                                    <a href="{{ route('exercises.show-logs', $exercise) }}" class="text-white" style="flex: 1; word-wrap: break-word;">{{ $exercise->title }}</a>
                                </div>
                                <div class="show-on-mobile" style="font-size: 0.9em; color: #ccc;">
                                    {{ $exercise->is_bodyweight ? 'Bodyweight' : 'Weighted' }}
                                    • {{ $exercise->isGlobal() ? 'Global' : 'Personal' }}
                                    @if($exercise->description)
                                        <br><small style="font-size: 0.8em; color: #aaa;">{{ \Illuminate\Support\Str::limit($exercise->description, 50) }}</small>
                                    @endif
                                </div>
                            </td>
                            <td class="hide-on-mobile">{{ $exercise->description }}</td>
                            <td class="hide-on-mobile">{{ $exercise->is_bodyweight ? 'Bodyweight' : 'Weighted' }}</td>
                            <td class="actions-column">
                                <div style="display: flex; gap: 5px;">
                                    @if($exercise->canBeEditedBy(auth()->user()))
                                        <a href="{{ route('exercises.edit', $exercise->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
                                    @endif
                                    @if(auth()->user()->hasRole('Admin') && !$exercise->isGlobal())
                                        <form action="{{ route('exercises.promote', $exercise->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="button" style="background-color: #4CAF50;" onclick="return confirm('Are you sure you want to promote this exercise to global status?');" title="Promote to global exercise"><i class="fa-solid fa-globe"></i></button>
                                        </form>
                                    @endif
                                    @if($exercise->canBeDeletedBy(auth()->user()))
                                        <form action="{{ route('exercises.destroy', $exercise->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this exercise?');"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    @elseif($exercise->liftLogs()->exists())
                                        <span class="button delete" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete: has lift logs"><i class="fa-solid fa-trash"></i></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th><input type="checkbox" id="select-all-exercises-footer"></th>
                        <th colspan="4" style="text-align:left; font-weight:normal;">
                            <form action="{{ route('exercises.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected exercises?');" style="display:inline;">
                                @csrf
                                <button type="submit" class="button delete"><i class="fa-solid fa-trash"></i> Delete Selected</button>
                            </form>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <div class="form-container">
                <h3>TSV Export</h3>
                <textarea id="tsv-output" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555;">Title	Description	Is Bodyweight	Band Type
@foreach ($exercises as $exercise){{ $exercise->title }}	{{ $exercise->description ?? '' }}	{{ $exercise->is_bodyweight ? 'true' : 'false' }}	{{ $exercise->band_type ?? 'none' }}
@endforeach</textarea>
                <button id="copy-tsv-button" class="button">Copy to Clipboard</button>
            </div>

            <script>
                document.getElementById('copy-tsv-button').addEventListener('click', function() {
                    var tsvOutput = document.getElementById('tsv-output');
                    tsvOutput.select();
                    document.execCommand('copy');
                    alert('TSV data copied to clipboard!');
                });

                // Select all functionality for exercises
                document.getElementById('select-all-exercises').addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('.exercise-checkbox:not([disabled])');
                    var footerCheckbox = document.getElementById('select-all-exercises-footer');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = this.checked;
                    }, this);
                    footerCheckbox.checked = this.checked;
                });

                document.getElementById('select-all-exercises-footer').addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('.exercise-checkbox:not([disabled])');
                    var headerCheckbox = document.getElementById('select-all-exercises');
                    checkboxes.forEach(function(checkbox) {
                        checkbox.checked = this.checked;
                    }, this);
                    headerCheckbox.checked = this.checked;
                });

                // Handle bulk delete form submission
                document.getElementById('delete-selected-form').addEventListener('submit', function(e) {
                    var checkedBoxes = document.querySelectorAll('.exercise-checkbox:checked:not([disabled])');
                    if (checkedBoxes.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one exercise to delete.');
                        return false;
                    }
                    
                    // Add selected IDs to the form
                    checkedBoxes.forEach(function(checkbox) {
                        var hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'exercise_ids[]';
                        hiddenInput.value = checkbox.value;
                        this.appendChild(hiddenInput);
                    }, this);
                });


            </script>
        @endif

        @if (!app()->environment(['production', 'staging']))
        <div class="form-container">
            <h3>TSV Import</h3>
            <form action="{{ route('exercises.import-tsv') }}" method="POST">
                @csrf
                <textarea name="tsv_data" id="tsv_data" rows="10" style="width: 100%; background-color: #3a3a3a; color: #f2f2f2; border: 1px solid #555; margin-bottom: 15px;" placeholder="Title&#9;Description&#9;Is Bodyweight (true/false)&#9;Band Type (resistance/assistance/none)"></textarea>
                
                @if(auth()->user()->hasRole('Admin'))
                <div style="margin-bottom: 15px;">
                    <input type="checkbox" name="import_as_global" id="import_as_global" value="1" style="margin-right: 8px;">
                    <label for="import_as_global" style="color: #f2f2f2; cursor: pointer;">Global</label>
                    <small style="color: #aaa; font-size: 0.85em; margin-left: 8px;">
                        Global exercises will be available to all users and can only be managed by administrators. Personal exercises are only visible to you and will be skipped if they conflict with existing global exercises.
                    </small>
                </div>
                @endif
                
                <button type="submit" class="button">Import Exercises</button>
            </form>
        </div>
        @endif
    </div>
@endsection
