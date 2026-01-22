{{-- PR Records Table Component - Displays PR records in a clean table format --}}
<div class="pr-records-table">
    <table class="pr-records-grid">
        @php
            $hasComparison = collect($data['records'])->contains(fn($r) => isset($r['comparison']) && $r['comparison']);
        @endphp
        
        @if($hasComparison)
        <thead>
            <tr class="pr-record-row">
                <th class="pr-record-label"></th>
                <th class="pr-record-value">Record</th>
                <th class="pr-record-comparison">Today</th>
            </tr>
        </thead>
        @endif
        
        <tbody>
        @foreach($data['records'] as $record)
        <tr class="pr-record-row">
            <td class="pr-record-label">{{ $record['label'] }}</td>
            <td class="pr-record-value">{{ $record['value'] }}</td>
            @if(isset($record['comparison']) && $record['comparison'])
            <td class="pr-record-comparison">{{ $record['comparison'] }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
