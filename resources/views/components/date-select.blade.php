<!-- resources/views/components/date-select.blade.php -->
<input type="date" name="{{ $name }}" id="{{ $id }}" value="{{ old($name, $selectedDate ?? now()->format('Y-m-d')) }}" {{ $attributes }}>