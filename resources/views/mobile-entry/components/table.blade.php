{{-- Table Component - Tabular CRUD list optimized for narrow screens --}}
<section class="component-table-section" aria-label="{{ $data['ariaLabels']['section'] ?? 'Data table' }}">
    @if($data['emptyMessage'] && empty($data['rows']))
    <div class="component-table-empty">
        {{ $data['emptyMessage'] }}
    </div>
    @endif
    
    @if(!empty($data['rows']))
    <div class="component-table">
        @foreach($data['rows'] as $row)
        <div class="component-table-row">
            <div class="component-table-cell">
                @if(isset($row['line1']) && !empty($row['line1']))
                <div class="cell-line cell-line-1">{{ $row['line1'] }}</div>
                @endif
                @if(isset($row['line2']) && !empty($row['line2']))
                <div class="cell-line cell-line-2">{{ $row['line2'] }}</div>
                @endif
                @if(isset($row['line3']) && !empty($row['line3']))
                <div class="cell-line cell-line-3">{{ $row['line3'] }}</div>
                @endif
            </div>
            <div class="component-table-actions">
                <a href="{{ $row['editAction'] }}" class="btn-table-edit" aria-label="{{ $data['ariaLabels']['editItem'] ?? 'Edit item' }}">
                    <i class="fas fa-edit"></i>
                </a>
                <form class="delete-form" method="POST" action="{{ $row['deleteAction'] }}">
                    @csrf
                    @method('DELETE')
                    @if(isset($row['deleteParams']))
                        @foreach($row['deleteParams'] as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                    @endif
                    <button type="submit" class="btn-table-delete" aria-label="{{ $data['ariaLabels']['deleteItem'] ?? 'Delete item' }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</section>

{{-- Pass confirm messages to JavaScript --}}
@if(isset($data['confirmMessages']))
<script data-table-confirm-messages="{{ json_encode($data['confirmMessages']) }}"></script>
@endif
