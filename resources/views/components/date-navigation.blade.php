@props(['navigationData'])

<div class="date-navigation flex items-center">
    {{-- Last Record Button --}}
    @if($navigationData['showLastRecordButton'])
        <a href="{{ $navigationData['lastRecordUrl'] }}" class="date-link">
            Last Record
        </a>
    @endif
    
    {{-- Three-day navigation window --}}
    @foreach($navigationData['navigationDates'] as $navDate)
        <a href="{{ $navDate['url'] }}" class="date-link {{ $navDate['isSelected'] ? 'active' : '' }} {{ $navDate['isToday'] ? 'today-date' : '' }}">
            {{ $navDate['label'] }}
        </a>
    @endforeach
    
    {{-- Today button (if not in range) --}}
    @if($navigationData['showTodayButton'])
        <a href="{{ $navigationData['todayUrl'] }}" class="date-link {{ $navigationData['selectedDate']->isSameDay($navigationData['today']) ? 'active today-date' : 'today-date' }}">
            Today
        </a>
    @endif
    
    {{-- Date picker --}}
    <label for="date_picker" class="date-pick-label ml-4 mr-2">Or Pick a Date:</label>
    <input type="date" id="date_picker" onchange="window.location.href = '{{ route($navigationData['routeName']) }}?date=' + this.value;" value="{{ $navigationData['selectedDate']->format('Y-m-d') }}">
</div>