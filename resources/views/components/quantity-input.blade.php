<input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value ?? '' }}" {{ isset($required) && $required ? 'required' : '' }} inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*">
