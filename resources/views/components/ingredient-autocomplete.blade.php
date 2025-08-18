<select name="{{ $name }}" id="{{ $id }}" class="ingredient-autocomplete" style="width: 100%;">
    <option value="">Select an ingredient</option>
    @foreach ($ingredients as $ingredient)
        <option value="{{ $ingredient->id }}" {{ (isset($selected) && $selected == $ingredient->id) ? 'selected' : '' }}>
            {{ $ingredient->name }}
        </option>
    @endforeach
</select>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#{{ $id }}').select2({
            placeholder: 'Select an ingredient',
            allowClear: true
        });
    });
</script>
@endpush
