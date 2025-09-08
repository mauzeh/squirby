<input type="number" name="{{ $name }}" id="{{ $id }}" step="any" min="0.001" value="{{ $value ?? '' }}" {{ isset($required) && $required ? 'required' : '' }} inputmode="decimal">
