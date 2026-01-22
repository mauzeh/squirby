{{-- PR Records Table Component - Displays PR records in a clean table format --}}
<div class="pr-records-table">
    @if(isset($data['title']))
    <div class="pr-records-header">
        @if(isset($data['icon']))
        <span class="pr-records-icon">{!! $data['icon'] !!}</span>
        @endif
        <span class="pr-records-title">{{ $data['title'] }}:</span>
    </div>
    @endif
    
    <table class="pr-records-grid">
        @foreach($data['records'] as $record)
        <tr class="pr-record-row">
            <td class="pr-record-label">{{ $record['label'] }}</td>
            <td class="pr-record-value">{{ $record['value'] }}</td>
        </tr>
        @endforeach
    </table>
</div>
