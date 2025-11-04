@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
@endsection

@section('scripts')
    <script data-confirm-messages="{{ json_encode($data['loggedItems']['confirmMessages']) }}" src="{{ asset('js/mobile-entry.js') }}"></script>
@endsection

@section('content')
    <div class="mobile-entry-container">
        <!-- Date Navigation -->
        <nav class="date-navigation" aria-label="{{ $data['navigation']['ariaLabels']['navigation'] }}">
            <a href="{{ $data['navigation']['prevButton']['href'] }}" class="nav-button nav-button--prev" aria-label="{{ $data['navigation']['ariaLabels']['previousDay'] }}">
                {{ $data['navigation']['prevButton']['text'] }}
            </a>
            <a href="{{ $data['navigation']['todayButton']['href'] }}" class="nav-button nav-button--today" aria-label="{{ $data['navigation']['ariaLabels']['goToToday'] }}">
                {{ $data['navigation']['todayButton']['text'] }}
            </a>
            <a href="{{ $data['navigation']['nextButton']['href'] }}" class="nav-button nav-button--next" aria-label="{{ $data['navigation']['ariaLabels']['nextDay'] }}">
                {{ $data['navigation']['nextButton']['text'] }}
            </a>
        </nav>

        <!-- Date Title -->
        <div class="date-title-container">
            <h1 class="date-title">{{ $data['navigation']['dateTitle']['main'] }}</h1>
            @if($data['navigation']['dateTitle']['subtitle'])
                <div class="date-subtitle">{{ $data['navigation']['dateTitle']['subtitle'] }}</div>
            @endif
        </div>

        <!-- Interface Messages -->
        @if(isset($data['interfaceMessages']) && $data['interfaceMessages']['hasMessages'])
        <section class="interface-messages" aria-label="Status messages">
            @foreach($data['interfaceMessages']['messages'] as $message)
            <div class="interface-message interface-message--{{ $message['type'] }}">
                @if(isset($message['prefix']))
                <span class="message-prefix">{{ $message['prefix'] }}</span>
                @endif
                {{ $message['text'] }}
            </div>
            @endforeach
        </section>
        @endif

        <!-- Summary -->
        @if($data['summary'])
        <section class="summary" aria-label="{{ $data['summary']['ariaLabels']['section'] }}">
            @foreach($data['summary']['values'] as $key => $value)
            <div class="summary-item summary-item--{{ $key }}">
                <span class="summary-value">
                    @if(is_numeric($value) && $value == (int)$value)
                        {{ number_format($value) }}
                    @elseif(is_numeric($value))
                        {{ number_format($value, 1) }}
                    @else
                        {{ $value }}
                    @endif
                </span>
                <span class="summary-label">{{ $data['summary']['labels'][$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</span>
            </div>
            @endforeach
        </section>
        @endif

        <!-- Add Item Button -->
        @if(isset($data['addItemButton']))
        <div class="add-item-section">
            <button type="button" class="btn-primary btn-success" aria-label="{{ $data['addItemButton']['ariaLabel'] }}">
                {{ $data['addItemButton']['text'] }}
            </button>
        </div>
        @endif

        <!-- Item Selection List -->
        <section class="item-selection-section" aria-label="{{ $data['itemSelectionList']['ariaLabels']['section'] }}">
            <ul class="item-selection-list">
                <li>
                    <div class="item-filter-container">
                        <div class="item-filter-group">
                            <div class="item-filter-input-wrapper">
                                <input type="text" class="item-filter-input" placeholder="{{ $data['itemSelectionList']['filterPlaceholder'] }}" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                                <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
                            </div>
                            @if(isset($data['itemSelectionList']['createForm']))
                            <form method="{{ $data['itemSelectionList']['createForm']['method'] }}" action="{{ $data['itemSelectionList']['createForm']['action'] }}" class="create-item-form">
                                @csrf
                                <input type="hidden" name="{{ $data['itemSelectionList']['createForm']['inputName'] }}" class="create-item-input" value="">
                                @if(isset($data['itemSelectionList']['createForm']['hiddenFields']))
                                    @foreach($data['itemSelectionList']['createForm']['hiddenFields'] as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                @endif
                                <button type="submit" class="btn-secondary btn-create" aria-label="{{ $data['itemSelectionList']['createForm']['ariaLabel'] }}">
                                    <span class="plus-icon">{{ $data['itemSelectionList']['createForm']['submitText'] }}</span>
                                </button>
                            </form>
                            @endif
                            <button type="button" class="btn-secondary btn-cancel" aria-label="Cancel and go back">
                                <span class="cancel-icon">×</span>
                            </button>
                        </div>
                    </div>
                </li>
                @foreach($data['itemSelectionList']['items'] as $item)
                <li>
                    <a href="{{ $item['href'] }}" class="item-selection-card item-selection-card--{{ $item['type']['cssClass'] }}" 
                       aria-label="{{ $data['itemSelectionList']['ariaLabels']['selectItem'] }}: {{ $item['name'] }}">
                        <span class="item-name">{{ $item['name'] }}</span>
                        <span class="item-type">{{ $item['type']['label'] }}</span>
                    </a>
                </li>
                @endforeach
                <li class="no-results-item" style="display: none;">
                    <div class="item-selection-card item-selection-card--no-results">
                        <span class="item-name">{{ $data['itemSelectionList']['noResultsMessage'] }}</span>
                        <span class="item-type">No matches</span>
                    </div>
                </li>
            </ul>
        </section>
        

        <!-- Forms -->
        @if(isset($data['forms']) && count($data['forms']) > 0)
        @foreach($data['forms'] as $form)
        <section class="item-logging-section form" aria-label="{{ $form['ariaLabels']['section'] }}" data-form-type="{{ $form['type'] }}" data-form-id="{{ $form['id'] }}">
            <div class="item-header">
                <h2 class="item-title">{{ $form['title'] }}</h2>
                @if($form['deleteAction'])
                <form class="delete-form" method="POST" action="{{ $form['deleteAction'] }}">
                    @csrf
                    @method('DELETE')
                    @if(isset($form['deleteParams']))
                        @foreach($form['deleteParams'] as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                    @endif
                    <button type="submit" class="btn-delete" aria-label="{{ $form['ariaLabels']['deleteForm'] ?? 'Delete form' }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
                @endif
            </div>
            
            @if(isset($form['messages']) && count($form['messages']) > 0)
            <div class="item-messages">
                @foreach($form['messages'] as $message)
                <div class="item-message item-message--{{ $message['type'] }}">
                    @if(isset($message['prefix']))
                    <span class="message-prefix">{{ $message['prefix'] }}</span>
                    @endif
                    {{ $message['text'] }}
                </div>
                @endforeach
            </div>
            @endif
            
            <form class="item-form" method="POST" action="{{ $form['formAction'] }}" data-form-type="{{ $form['type'] }}">
                @csrf
                <input type="hidden" name="form_type" value="{{ $form['type'] }}">
                <input type="hidden" name="item_name" value="{{ $form['itemName'] }}">
                @if(isset($form['hiddenFields']))
                    @foreach($form['hiddenFields'] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                @endif
                @foreach($form['numericFields'] as $field)
                <div class="form-mobile-group">
                    <label for="{{ $field['id'] }}" class="form-mobile-label">{{ $field['label'] }}</label>
                    @if(isset($field['type']) && $field['type'] === 'select')
                        <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="form-select" aria-label="{{ $field['ariaLabels']['field'] ?? $field['label'] }}">
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}" {{ old($field['name'], $field['defaultValue']) == $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="number-input-group" 
                             data-increment="{{ $field['increment'] }}" 
                             data-min="{{ $field['min'] }}" 
                             data-max="{{ $field['max'] ?? '' }}">
                            <button type="button" class="decrement-button" aria-label="{{ $field['ariaLabels']['decrease'] }}">{{ $form['buttons']['decrement'] }}</button>
                            <input type="number" id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="number-input" value="{{ old($field['name'], $field['defaultValue']) }}" min="{{ $field['min'] }}" step="{{ $field['step'] ?? $field['increment'] }}" @if(isset($field['max'])) max="{{ $field['max'] }}" @endif>
                            <button type="button" class="increment-button" aria-label="{{ $field['ariaLabels']['increase'] }}">{{ $form['buttons']['increment'] }}</button>
                        </div>
                    @endif
                </div>
                @endforeach
                <div class="form-mobile-group">
                    <label for="{{ $form['commentField']['id'] }}" class="form-mobile-label">{{ $form['commentField']['label'] }}</label>
                    <textarea id="{{ $form['commentField']['id'] }}" name="{{ $form['commentField']['name'] }}" class="comment-textarea" placeholder="{{ $form['commentField']['placeholder'] }}" rows="3">{{ old($form['commentField']['name'], $form['commentField']['defaultValue']) }}</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">{{ $form['buttons']['submit'] }}</button>
                </div>
            </form>
        </section>
        @endforeach
        @endif

       <!-- Logged Item Display -->
        <section class="logged-items-section" aria-label="{{ $data['loggedItems']['ariaLabels']['section'] }}">
            @if(isset($data['loggedItems']['emptyMessage']))
            <div class="logged-item logged-items-empty">
                {{ $data['loggedItems']['emptyMessage'] }}
            </div>
            @endif
            @foreach($data['loggedItems']['items'] as $index => $item)
            <div class="logged-item">
                <div class="item-header">
                    <h2 class="item-title">{{ $item['title'] }}</h2>
                    @if(isset($item['value']) && !empty($item['value']))
                    <span class="item-value">{{ $item['value'] }}</span>
                    @endif
                    <div class="item-actions">
                        <a href="{{ $item['editAction'] }}" class="btn-edit" aria-label="{{ $data['loggedItems']['ariaLabels']['editItem'] }}">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form class="delete-form" method="POST" action="{{ $item['deleteAction'] }}">
                            @csrf
                            @method('DELETE')
                            @if(isset($item['deleteParams']))
                                @foreach($item['deleteParams'] as $name => $value)
                                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                @endforeach
                            @endif
                            <button type="submit" class="btn-delete" aria-label="{{ $data['loggedItems']['ariaLabels']['deleteItem'] }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @if(isset($item['message']))
                <div class="item-message item-message--{{ $item['message']['type'] }}">
                    @if(isset($item['message']['prefix']))
                    <span class="message-prefix">{{ $item['message']['prefix'] }}</span>
                    @endif
                    {{ $item['message']['text'] }}
                </div>
                @endif
                @if(isset($item['freeformText']) && !empty($item['freeformText']))
                <div class="item-freeform-text">
                    {{ $item['freeformText'] }}
                </div>
                @endif
            </div>
            @endforeach
        </section>
    </div>
@endsection