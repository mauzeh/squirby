{{-- PR Records Table Component - Displays PR records in a clean table format --}}
<div class="pr-records-table {{ $data['cssClass'] ?? '' }}">
    <table class="pr-records-grid">
        @php
            $hasComparison = collect($data['records'])->contains(fn($r) => isset($r['comparison']) && $r['comparison']);
            $hasRecords = !empty($data['records']);
            // Determine if this is showing beaten PRs (use "Previous") or current records (use "Record")
            $isPRTable = isset($data['isPRTable']) && $data['isPRTable'];
            $title = $data['title'] ?? '';
        @endphp
        
        @if($hasComparison && $hasRecords)
        <thead>
            <tr class="pr-record-row">
                <th class="pr-record-label pr-record-title">{{ $title }}</th>
                <th class="pr-record-value">{{ $isPRTable ? 'Previous' : 'Record' }}</th>
                <th class="pr-record-comparison">Today</th>
            </tr>
        </thead>
        @endif
        
        @if($hasRecords)
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
        @endif
        
        @if(isset($data['footerLink']) && $data['footerLink'])
        <tfoot>
            <tr class="pr-record-footer-row">
                <td colspan="3" class="pr-record-footer">
                    <a href="{{ $data['footerLink'] }}" class="pr-record-footer-link">
                        <i class="fas fa-chart-line"></i>
                        {{ $data['footerText'] ?? 'View history' }}
                    </a>
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
