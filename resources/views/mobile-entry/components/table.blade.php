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
        @php
            $hasSubitems = isset($row['subItems']) && !empty($row['subItems']);
            $isCollapsible = $hasSubitems && ($row['collapsible'] ?? true);
            $initialState = $row['initialState'] ?? 'collapsed';
            $expandedClass = $initialState === 'expanded' ? ' expanded' : '';
        @endphp
        <div class="component-table-row {{ $hasSubitems ? 'has-subitems' : '' }} {{ $isCollapsible ? 'is-collapsible' : '' }}{{ $expandedClass }}" data-row-id="{{ $row['id'] }}" @if($isCollapsible) data-toggle-subitems="{{ $row['id'] }}" @endif>
            @if($isCollapsible)
            <i class="fas fa-chevron-right table-expand-icon"></i>
            @endif
            <div class="component-table-cell">
                @if(isset($row['line1']) && !empty($row['line1']))
                <div class="{{ $row['titleClass'] ?? 'cell-title' }}">{{ $row['line1'] }}</div>
                @endif
                @if(isset($row['line2']) && !empty($row['line2']))
                <div class="cell-content">{{ $row['line2'] }}</div>
                @endif
                @if(isset($row['line3']) && !empty($row['line3']))
                <div class="cell-detail">{{ $row['line3'] }}</div>
                @endif
            </div>
            <div class="component-table-actions">
                @if(isset($row['actions']) && !empty($row['actions']))
                    {{-- New format: custom actions --}}
                    @foreach($row['actions'] as $action)
                        @if($action['type'] === 'link')
                            <a href="{{ $action['url'] }}" 
                               class="btn-table-edit {{ $action['cssClass'] ?? '' }}" 
                               aria-label="{{ $action['ariaLabel'] ?? '' }}">
                                <i class="fas {{ $action['icon'] }}"></i>
                            </a>
                        @elseif($action['type'] === 'form')
                            <form class="{{ $action['requiresConfirm'] ? 'delete-form' : '' }}" 
                                  method="POST" 
                                  action="{{ $action['url'] }}">
                                @csrf
                                @if($action['method'] !== 'POST')
                                    @method($action['method'])
                                @endif
                                @if(isset($action['params']))
                                    @foreach($action['params'] as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                @endif
                                <button type="submit" 
                                        class="btn-table-delete {{ $action['cssClass'] ?? '' }}" 
                                        aria-label="{{ $action['ariaLabel'] ?? '' }}">
                                    <i class="fas {{ $action['icon'] }}"></i>
                                </button>
                            </form>
                        @endif
                    @endforeach
                @else
                    {{-- Legacy format: edit/delete only --}}
                    @if(!empty($row['editAction']))
                    <a href="{{ $row['editAction'] }}" class="btn-table-edit" aria-label="{{ $data['ariaLabels']['editItem'] ?? 'Edit item' }}">
                        <i class="fas fa-edit"></i>
                    </a>
                    @endif
                    @if(!empty($row['deleteAction']))
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
                    @endif
                @endif
            </div>
        </div>
        
        {{-- Sub-items --}}
        @if(isset($row['subItems']) && !empty($row['subItems']))
            @php
                $isCollapsible = $row['collapsible'] ?? true;
                $initialState = $row['initialState'] ?? 'collapsed';
                $shouldHide = $isCollapsible && $initialState === 'collapsed';
            @endphp
            <div class="component-table-subitems" data-subitems="{{ $row['id'] }}" style="display: {{ $shouldHide ? 'none' : '' }};">

                @foreach($row['subItems'] as $subItem)
                <div class="component-table-subitem">
                    <div class="component-table-cell">
                        @if(isset($subItem['line1']) && !empty($subItem['line1']))
                        <div class="cell-title">{{ $subItem['line1'] }}</div>
                        @endif
                        @if(isset($subItem['line2']) && !empty($subItem['line2']))
                        <div class="cell-content">{{ $subItem['line2'] }}</div>
                        @endif
                        @if(isset($subItem['line3']) && !empty($subItem['line3']))
                        <div class="cell-detail">{{ $subItem['line3'] }}</div>
                        @endif
                        @if(isset($subItem['messages']) && !empty($subItem['messages']))
                            @foreach($subItem['messages'] as $message)
                            <div class="component-message component-message--{{ $message['type'] }}">
                                @if(isset($message['prefix']) && !empty($message['prefix']))
                                <span class="message-prefix">{{ $message['prefix'] }}</span>
                                @endif
                                {!! $message['text'] !!}
                            </div>
                            @endforeach
                        @endif
                    </div>
                    <div class="component-table-actions">
                        @if(isset($subItem['actions']) && !empty($subItem['actions']))
                            {{-- Custom actions --}}
                            @foreach($subItem['actions'] as $action)
                                @if($action['type'] === 'link')
                                    <a href="{{ $action['url'] }}" 
                                       class="btn-table-edit {{ $action['cssClass'] ?? '' }}" 
                                       aria-label="{{ $action['ariaLabel'] ?? '' }}">
                                        <i class="fas {{ $action['icon'] }}"></i>
                                    </a>
                                @elseif($action['type'] === 'form')
                                    <form class="{{ $action['requiresConfirm'] ? 'delete-form' : '' }}" 
                                          method="POST" 
                                          action="{{ $action['url'] }}">
                                        @csrf
                                        @if($action['method'] !== 'POST')
                                            @method($action['method'])
                                        @endif
                                        @if(isset($action['params']))
                                            @foreach($action['params'] as $name => $value)
                                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                            @endforeach
                                        @endif
                                        <button type="submit" 
                                                class="btn-table-delete {{ $action['cssClass'] ?? '' }}" 
                                                aria-label="{{ $action['ariaLabel'] ?? '' }}">
                                            <i class="fas {{ $action['icon'] }}"></i>
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                        @else
                            {{-- Legacy format --}}
                            @if(!empty($subItem['editAction']))
                            <a href="{{ $subItem['editAction'] }}" class="btn-table-edit" aria-label="{{ $data['ariaLabels']['editItem'] ?? 'Edit item' }}">
                                <i class="fas fa-edit"></i>
                            </a>
                            @endif
                            @if(!empty($subItem['deleteAction']))
                            <form class="delete-form" method="POST" action="{{ $subItem['deleteAction'] }}">
                                @csrf
                                @method('DELETE')
                                @if(isset($subItem['deleteParams']))
                                    @foreach($subItem['deleteParams'] as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                @endif
                                <button type="submit" class="btn-table-delete" aria-label="{{ $data['ariaLabels']['deleteItem'] ?? 'Delete item' }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @endif
        @endforeach
    </div>
    @endif
</section>

{{-- Pass confirm messages to JavaScript --}}
@if(isset($data['confirmMessages']))
<script data-table-confirm-messages="{{ json_encode($data['confirmMessages']) }}"></script>
@endif
