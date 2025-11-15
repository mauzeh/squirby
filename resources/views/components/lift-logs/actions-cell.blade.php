@props(['liftLog'])

<td class="actions-column">
    <div class="actions-flex">
        <a href="{{ $liftLog['exercise_url'] }}" class="button" style="background-color: #007bff;" title="View Exercise Logs"><i class="fa-solid fa-chart-line"></i></a>
        <a href="{{ $liftLog['edit_url'] }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
    </div>
</td>