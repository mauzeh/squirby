@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry.css') }}">
@endsection

@section('content')
    <div class="mobile-entry-container">
        <!-- Date Navigation -->
        <nav class="date-navigation" aria-label="Date navigation">
            <button type="button" class="nav-button nav-button--prev" aria-label="Previous day">
                ← Prev
            </button>
            <button type="button" class="nav-button nav-button--today" aria-label="Go to today">
                Today
            </button>
            <button type="button" class="nav-button nav-button--next" aria-label="Next day">
                Next →
            </button>
        </nav>

        <!-- Date Title -->
        <h1 class="date-title">Today</h1>       
 <!-- Summary -->
        <section class="summary" aria-label="Daily summary">
            <div class="summary-item summary-item--total">
                <span class="summary-value">1,250</span>
                <span class="summary-label">Total</span>
            </div>
            <div class="summary-item summary-item--completed">
                <span class="summary-value">3</span>
                <span class="summary-label">Completed</span>
            </div>
            <div class="summary-item summary-item--average">
                <span class="summary-value">85</span>
                <span class="summary-label">Average</span>
            </div>
            <div class="summary-item summary-item--today">
                <span class="summary-value">12</span>
                <span class="summary-label">Today</span>
            </div>
        </section>  
      <!-- New Item Form -->
        <section class="item-logging-section" aria-label="Log new item">
            <div class="item-header">
                <form class="delete-form" method="POST" action="#">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete" aria-label="Delete form">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <form class="item-form" method="POST" action="#">
                @csrf
                <div class="form-mobile-group">
                    <label for="item-value" class="form-mobile-label">Value:</label>
                    <div class="number-input-group">
                        <button type="button" class="decrement-button" aria-label="Decrease value">-</button>
                        <input type="number" id="item-value" name="value" class="number-input" value="10" min="0" step="1">
                        <button type="button" class="increment-button" aria-label="Increase value">+</button>
                    </div>
                </div>
                <div class="form-mobile-group">
                    <label for="item-comment" class="form-mobile-label">Comment:</label>
                    <textarea id="item-comment" name="comment" class="comment-textarea" placeholder="Add a comment..." rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Log Item</button>
                </div>
            </form>
        </section> 
       <!-- Logged Item Display -->
        <section class="logged-items-section" aria-label="Logged items">
            <div class="logged-item">
                <div class="item-header">
                    <span class="item-value">25</span>
                    <form class="delete-form" method="POST" action="#">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete" aria-label="Delete logged item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                <div class="item-comment">Morning workout completed</div>
            </div>
        </section>
    </div>
@endsection