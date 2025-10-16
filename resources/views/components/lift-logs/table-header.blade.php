@props(['config'])

<thead>
    <tr>
        <th><input type="checkbox" id="select-all-lift-logs"></th>
        <th class="{{ $config['dateColumnClass'] }}">Date</th>
        @unless($config['hideExerciseColumn'])
            <th>Exercise</th>
        @endunless
        <th class="hide-on-mobile">Weight (reps x rounds)</th>
        <th class="hide-on-mobile">1RM (est.)</th>
        <th class="hide-on-mobile comments-column">Comments</th>
        <th class="actions-column">Actions</th>
    </tr>
</thead>