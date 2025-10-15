@props(['config'])

<tfoot>
    <tr>
        <th colspan="{{ $config['colspan'] }}" style="text-align:left; font-weight:normal;">
            <form action="{{ route('lift-logs.destroy-selected') }}" method="POST" id="delete-selected-form" onsubmit="return confirm('Are you sure you want to delete the selected lift logs?');" style="display:inline;">
                @csrf
                <button type="submit" class="button delete">Delete Selected</button>
            </form>
        </th>
    </tr>
</tfoot>