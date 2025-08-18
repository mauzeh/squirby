<input type="number" name="{{ $name }}" id="{{ $id }}" step="0.001" min="0.001" value="{{ $value ?? '' }}" {{ isset($required) && $required ? 'required' : '' }}>
