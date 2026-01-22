{{-- PR Info Component - Displays previous PR information in a user-friendly format --}}
<section class="component-pr-info" aria-label="{{ $data['ariaLabel'] ?? 'Personal Record Information' }}">
    <div class="pr-info-header">
        <h3 class="pr-info-title">
            <i class="fas fa-trophy"></i>
            {{ $data['title'] ?? 'Previous Records' }}
        </h3>
    </div>
    
    <div class="pr-info-content">
        @if(!empty($data['records']))
            <table class="pr-info-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Previous Best</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['records'] as $record)
                        <tr class="pr-info-row pr-info-row--{{ $record['type'] }}">
                            <td class="pr-info-type">
                                <span class="pr-info-badge pr-info-badge--{{ $record['type'] }}">
                                    {{ $record['label'] }}
                                </span>
                            </td>
                            <td class="pr-info-value">{{ $record['value'] }}</td>
                            <td class="pr-info-date">
                                @if(isset($record['lift_log_id']) && $record['lift_log_id'])
                                    <a href="{{ route('lift-logs.edit', $record['lift_log_id']) }}" class="pr-info-link">
                                        {{ $record['date'] }}
                                    </a>
                                @else
                                    {{ $record['date'] }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="pr-info-empty">{{ $data['emptyMessage'] ?? 'No previous records found. This will be your first!' }}</p>
        @endif
    </div>
</section>
