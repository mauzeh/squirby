@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
@endsection

@section('scripts')
    <script src="{{ asset('js/mobile-entry.js') }}"></script>
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
        <!-- Summary -->
        <section class="summary" aria-label="{{ $data['summary']['ariaLabels']['section'] }}">
            <div class="summary-item summary-item--total">
                <span class="summary-value">{{ number_format($data['summary']['values']['total']) }}</span>
                <span class="summary-label">{{ $data['summary']['labels']['total'] }}</span>
            </div>
            <div class="summary-item summary-item--completed">
                <span class="summary-value">{{ $data['summary']['values']['completed'] }}</span>
                <span class="summary-label">{{ $data['summary']['labels']['completed'] }}</span>
            </div>
            <div class="summary-item summary-item--average">
                <span class="summary-value">{{ $data['summary']['values']['average'] }}</span>
                <span class="summary-label">{{ $data['summary']['labels']['average'] }}</span>
            </div>
            <div class="summary-item summary-item--today">
                <span class="summary-value">{{ $data['summary']['values']['today'] }}</span>
                <span class="summary-label">{{ $data['summary']['labels']['today'] }}</span>
            </div>
        </section>

        <!-- Add Item Button -->
        <div class="add-item-section">
            <button type="button" class="btn-primary btn-success" aria-label="{{ $data['addItemButton']['ariaLabel'] }}">
                {{ $data['addItemButton']['text'] }}
            </button>
        </div>

        <!-- Item Selection List -->
        <section class="item-selection-section" aria-label="{{ $data['itemSelectionList']['ariaLabels']['section'] }}">
            <ul class="item-selection-list">
                <li>
                    <div class="item-filter-container">
                        <div class="item-filter-group">
                            <div class="item-filter-input-wrapper">
                                <input type="text" class="item-filter-input" placeholder="{{ $data['itemSelectionList']['filterPlaceholder'] }}">
                                <button type="button" class="btn-clear-filter" aria-label="Clear filter" style="display: none;">×</button>
                            </div>
                            <form method="{{ $data['itemSelectionList']['createForm']['method'] }}" action="{{ $data['itemSelectionList']['createForm']['action'] }}" class="create-item-form">
                                @csrf
                                <input type="hidden" name="{{ $data['itemSelectionList']['createForm']['inputName'] }}" class="create-item-input" value="">
                                <button type="submit" class="btn-secondary btn-create" aria-label="{{ $data['itemSelectionList']['createForm']['ariaLabel'] }}">
                                    <span class="plus-icon">{{ $data['itemSelectionList']['createForm']['submitText'] }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
                @foreach($data['itemSelectionList']['items'] as $item)
                <li>
                    <a href="#" class="item-selection-card item-selection-card--{{ str_replace(' ', '-', $item['type']) }}" aria-label="{{ $data['itemSelectionList']['ariaLabels']['selectItem'] }}: {{ $item['name'] }}">
                        <span class="item-name">{{ $item['name'] }}</span>
                        <span class="item-type">{{ ucfirst($item['type']) }}</span>
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
        
        <!-- Multiple Item Forms -->
        @foreach($data['itemForms'] as $form)
        <section class="item-logging-section" aria-label="{{ $form['ariaLabels']['section'] }}" data-form-type="{{ $form['type'] }}" data-form-id="{{ $form['id'] }}">
            <div class="item-header">
                <h2 class="item-title">{{ $form['title'] }}</h2>
                <form class="delete-form" method="POST" action="#">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete" aria-label="{{ $form['ariaLabels']['deleteForm'] }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            
            @if(isset($form['messages']) && count($form['messages']) > 0)
            <div class="item-messages">
                @foreach($form['messages'] as $message)
                <div class="item-message item-message--{{ $message['type'] }}">
                    <span class="message-prefix">{{ $message['prefix'] }}</span> {{ $message['text'] }}
                </div>
                @endforeach
            </div>
            @endif
            
            <form class="item-form" method="POST" action="#" data-form-type="{{ $form['type'] }}">
                @csrf
                <input type="hidden" name="form_type" value="{{ $form['type'] }}">
                @foreach($form['numericFields'] as $field)
                <div class="form-mobile-group">
                    <label for="{{ $field['id'] }}" class="form-mobile-label">{{ $field['label'] }}</label>
                    <div class="number-input-group" 
                         data-increment="{{ $field['increment'] }}" 
                         data-min="{{ $field['min'] }}" 
                         data-max="{{ $field['max'] ?? '' }}">
                        <button type="button" class="decrement-button" aria-label="{{ $field['ariaLabels']['decrease'] }}">{{ $form['buttons']['decrement'] }}</button>
                        <input type="number" id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="number-input" value="{{ $field['defaultValue'] }}" min="{{ $field['min'] }}" step="{{ $field['increment'] }}" @if($field['max']) max="{{ $field['max'] }}" @endif>
                        <button type="button" class="increment-button" aria-label="{{ $field['ariaLabels']['increase'] }}">{{ $form['buttons']['increment'] }}</button>
                    </div>
                </div>
                @endforeach
                <div class="form-mobile-group">
                    <label for="{{ $form['commentField']['id'] }}" class="form-mobile-label">{{ $form['commentField']['label'] }}</label>
                    <textarea id="{{ $form['commentField']['id'] }}" name="{{ $form['commentField']['name'] }}" class="comment-textarea" placeholder="{{ $form['commentField']['placeholder'] }}" rows="3">{{ $form['commentField']['defaultValue'] }}</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">{{ $form['buttons']['submit'] }}</button>
                </div>
            </form>
        </section>
        @endforeach 
       <!-- Logged Item Display -->
        <section class="logged-items-section" aria-label="{{ $data['loggedItems']['ariaLabels']['section'] }}">
            <div class="logged-item logged-items-empty">
                {{ $data['loggedItems']['emptyMessage'] }}
            </div>
            @foreach($data['loggedItems']['items'] as $index => $item)
            <div class="logged-item">
                <div class="item-header">
                    <h2 class="item-title">{{ $data['loggedItems']['title'] }} {{ $index + 1 }}</h2>
                    <span class="item-value">{{ $item['value'] }}</span>
                    <form class="delete-form" method="POST" action="#">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete" aria-label="{{ $data['loggedItems']['ariaLabels']['deleteItem'] }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <div class="item-message item-message--{{ $item['message']['type'] }}">
                    <span class="message-prefix">{{ $item['message']['prefix'] }}</span> {{ $item['message']['text'] }}
                </div>
            </div>
            @endforeach
        </section>
    </div>
@endsection