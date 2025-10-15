@props(['liftLog'])

<td class="actions-column">
    <div class="actions-flex">
        <a href="{{ route('lift-logs.edit', $liftLog->id) }}" class="button edit"><i class="fa-solid fa-pencil"></i></a>
        <form action="{{ route('lift-logs.destroy', $liftLog->id) }}" method="POST" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="button delete" onclick="return confirm('Are you sure you want to delete this lift log?');"><i class="fa-solid fa-trash"></i></button>
        </form>
    </div>
</td>