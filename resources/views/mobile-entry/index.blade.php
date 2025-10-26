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
            <form class="item-form" method="POST" action="#">
                @csrf
                <div class="form-mobile-group">
                    <label for="item-value" class="form-mobile-label">{{ $data['itemForm']['labels']['value'] }}</label>
                    <div class="number-input-group">
                        <button type="button" class="decrement-button" aria-label="{{ $data['itemForm']['ariaLabels']['decreaseValue'] }}">{{ $data['itemForm']['buttons']['decrement'] }}</button>
                        <input type="number" id="item-value" name="value" class="number-input" value="{{ $data['itemForm']['defaults']['value'] }}" min="0" step="1">
                        <button type="button" class="increment-button" aria-label="{{ $data['itemForm']['ariaLabels']['increaseValue'] }}">{{ $data['itemForm']['buttons']['increment'] }}</button>
                    </div>
                </div>
                <div class="form-mobile-group">
                    <label for="item-comment" class="form-mobile-label">{{ $data['itemForm']['labels']['comment'] }}</label>
                    <textarea id="item-comment" name="comment" class="comment-textarea" placeholder="{{ $data['itemForm']['placeholders']['comment'] }}" rows="3">{{ $data['itemForm']['defaults']['comment'] }}</textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">{{ $data['itemForm']['buttons']['submit'] }}</button>
                </div>
            </form>
        </section> 
       <!-- Logged Item Display -->
        <section class="logged-items-section" aria-label="{{ $data['loggedItems']['ariaLabels']['section'] }}">
            <div class="logged-item">
                <div class="item-header">
                    <h2 class="item-title">{{ $data['loggedItems']['title'] }}</h2>
                    <span class="item-value">{{ $data['loggedItems']['sampleItem']['value'] }}</span>
                    <form class="delete-form" method="POST" action="#">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete" aria-label="{{ $data['loggedItems']['ariaLabels']['deleteItem'] }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <div class="item-comment">{{ $data['loggedItems']['sampleItem']['comment'] }}</div>
            </div>
        </section>
    </div>
@endsection