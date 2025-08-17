<!-- resources/views/components/time-select.blade.php -->
<select name="{{ $name }}" id="{{ $id }}" {{ $attributes }}>
    @php
        $currentTime = now()->timezone(config('app.timezone'));
        $minutes = $currentTime->minute;
        $remainder = $minutes % 15;

        if ($remainder !== 0) {
            $currentTime->addMinutes(15 - $remainder);
        }
        $defaultSelectedTime = $currentTime->format('H:i');
        $actualSelectedTime = old($name, $selectedTime ?? $defaultSelectedTime);

        for ($h = 0; $h < 24; $h++) {
            for ($m = 0; $m < 60; $m += 15) {
                $time = sprintf('%02d:%02d', $h, $m);
                $isSelected = ($time === $actualSelectedTime) ? 'selected' : '';
                echo "<option value=\"" . $time . "\" " . $isSelected . ">" . $time . "</option>";
            }
        }
    @endphp
</select>
<span style="margin-left: 10px; color: #ccc;">({{ now()->timezone(config('app.timezone'))->format('T') }})</span>