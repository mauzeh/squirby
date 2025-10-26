@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
@endsection

@section('content')
    <div class="mobile-entry-container">
        <!-- Date Navigation -->
        <nav class="date-navigation" aria-label="{{ $data['navigation']['ariaLabels']['navigation'] }}">
            <button type="button" class="nav-button nav-button--prev" aria-label="{{ $data['navigation']['ariaLabels']['previousDay'] }}">
                {{ $data['navigation']['prevButton'] }}
            </button>
            <button type="button" class="nav-button nav-button--today" aria-label="{{ $data['navigation']['ariaLabels']['goToToday'] }}">
                {{ $data['navigation']['todayButton'] }}
            </button>
            <button type="button" class="nav-button nav-button--next" aria-label="{{ $data['navigation']['ariaLabels']['nextDay'] }}">
                {{ $data['navigation']['nextButton'] }}
            </button>
        </nav>

        <!-- Date Title -->
        <h1 class="date-title">{{ $data['navigation']['dateTitle'] }}</h1>       
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
        
        <!-- New Item Form -->
        <section class="item-logging-section" aria-label="{{ $data['itemForm']['ariaLabels']['section'] }}">
            <div class="item-header">
                <h2 class="item-title">{{ $data['itemForm']['title'] }}</h2>
                <form class="delete-form" method="POST" action="#">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete" aria-label="{{ $data['itemForm']['ariaLabels']['deleteForm'] }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            
            @if(isset($data['itemForm']['messages']) && count($data['itemForm']['messages']) > 0)
            <div class="item-messages">
                @foreach($data['itemForm']['messages'] as $message)
                <div class="item-message item-message--{{ $message['type'] }}">
                    <span class="message-prefix">{{ $message['prefix'] }}</span> {{ $message['text'] }}
                </div>
                @endforeach
            </div>
            @endif
            
            <form class="item-form" method="POST" action="#">
                @csrf
                @foreach($data['itemForm']['numericFields'] as $field)
                <div class="form-mobile-group">
                    <label for="{{ $field['id'] }}" class="form-mobile-label">{{ $field['label'] }}</label>
                    <div class="number-input-group">
                        <button type="button" class="decrement-button" aria-label="{{ $field['ariaLabels']['decrease'] }}">{{ $data['itemForm']['buttons']['decrement'] }}</button>
                        <input type="number" id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="number-input" value="{{ $field['defaultValue'] }}" min="0" step="1">
                        <button type="button" class="increment-button" aria-label="{{ $field['ariaLabels']['increase'] }}">{{ $data['itemForm']['buttons']['increment'] }}</button>
                    </div>
                </div>
                @endforeach
                <div class="form-mobile-group">
                    <label for="{{ $data['itemForm']['commentField']['id'] }}" class="form-mobile-label">{{ $data['itemForm']['commentField']['label'] }}</label>
                    <textarea id="{{ $data['itemForm']['commentField']['id'] }}" name="{{ $data['itemForm']['commentField']['name'] }}" class="comment-textarea" placeholder="{{ $data['itemForm']['commentField']['placeholder'] }}" rows="3">{{ $data['itemForm']['commentField']['defaultValue'] }}</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">{{ $data['itemForm']['buttons']['submit'] }}</button>
                </div>
            </form>
        </section> 
       <!-- Logged Item Display -->
        <section class="logged-items-section" aria-label="{{ $data['loggedItems']['ariaLabels']['section'] }}">
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