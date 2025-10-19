@extends('app')

@section('content')
    <div class="mobile-entry-container">
        <div class="date-navigation-mobile">
            @php
                $today = \Carbon\Carbon::today();
                $prevDay = $selectedDate->copy()->subDay();
                $nextDay = $selectedDate->copy()->addDay();
            @endphp
            <a href="{{ route('food-logs.mobile-entry', ['date' => $prevDay->toDateString()]) }}" class="nav-button">&lt; Prev</a>
            <a href="{{ route('food-logs.mobile-entry', ['date' => $today->toDateString()]) }}" class="nav-button">Today</a>
            <a href="{{ route('food-logs.mobile-entry', ['date' => $nextDay->toDateString()]) }}" class="nav-button">Next &gt;</a>
        </div>

        <h1>
            @if ($selectedDate->isToday())
                Today
            @elseif ($selectedDate->isYesterday())
                Yesterday
            @elseif ($selectedDate->isTomorrow())
                Tomorrow
            @else
                {{ $selectedDate->format('M d, Y') }}
            @endif
        </h1>

        {{-- Food selection interface --}}
        <div class="add-food-container">
            <button type="button" id="add-food-button" class="button-large button-green">Add Food</button>
        </div>

        <div id="food-list-container" class="hidden">
            <div class="food-list">
                @foreach ($ingredients as $ingredient)
                    <a href="#" class="food-list-item ingredient-item" 
                       data-type="ingredient" 
                       data-id="{{ $ingredient->id }}"
                       data-name="{{ $ingredient->name }}"
                       data-unit="{{ $ingredient->baseUnit->name }}">
                        <span class="food-name">{{ $ingredient->name }}</span>
                        <span class="food-label"><em>Ingredient</em></span>
                    </a>
                @endforeach
                
                @foreach ($meals as $meal)
                    <a href="#" class="food-list-item meal-item"
                       data-type="meal"
                       data-id="{{ $meal->id }}"
                       data-name="{{ $meal->name }}">
                        <span class="food-name">{{ $meal->name }}</span>
                        <span class="food-label"><em>Meal</em></span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Error display section --}}
        @if(session('error'))
            <div class="error-message" id="error-message">
                <div class="error-content">
                    <span class="error-text">{{ session('error') }}</span>
                    <button type="button" class="error-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="success-message" id="success-message">
                <div class="success-content">
                    <span class="success-text">{{ session('success') }}</span>
                    <button type="button" class="success-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                </div>
            </div>
        @endif

        {{-- Dynamic form fields for logging --}}
        <div id="logging-form-container" class="hidden">
            <form id="food-logging-form" method="POST" action="{{ route('food-logs.store') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="mobile-entry">
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                <input type="hidden" name="selected_type" id="selected-type">
                <input type="hidden" name="selected_id" id="selected-id">
                <input type="hidden" name="selected_name" id="selected-name">

                {{-- Client-side validation error display --}}
                <div id="validation-errors" class="validation-errors hidden">
                    <div class="validation-content">
                        <span class="validation-text"></span>
                        <button type="button" class="validation-close" onclick="document.getElementById('validation-errors').classList.add('hidden')">&times;</button>
                    </div>
                </div>

                <div class="selected-food-display">
                    <h3 id="selected-food-name"></h3>
                    <span id="selected-food-type-label"></span>
                </div>

                {{-- Ingredient form fields --}}
                <div id="ingredient-fields" class="form-fields hidden">
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <div class="input-group">
                            <button type="button" class="decrement-button" data-target="quantity">-</button>
                            <input type="number" name="quantity" id="quantity" class="large-input" step="0.01" min="0" value="1">
                            <button type="button" class="increment-button" data-target="quantity">+</button>
                        </div>
                        <span class="unit-display" id="ingredient-unit"></span>
                    </div>
                    <div class="form-group">
                        <label for="ingredient-notes">Notes:</label>
                        <textarea name="notes" id="ingredient-notes" class="large-textarea" placeholder="Optional notes..."></textarea>
                    </div>
                </div>

                {{-- Meal form fields --}}
                <div id="meal-fields" class="form-fields hidden">
                    <div class="form-group">
                        <label for="portion">Portion:</label>
                        <div class="input-group">
                            <button type="button" class="decrement-button" data-target="portion">-</button>
                            <input type="number" name="portion" id="portion" class="large-input" step="0.01" min="0" value="1">
                            <button type="button" class="increment-button" data-target="portion">+</button>
                        </div>
                        <span class="unit-display">servings</span>
                    </div>
                    <div class="form-group">
                        <label for="meal-notes">Notes:</label>
                        <textarea name="notes" id="meal-notes" class="large-textarea" placeholder="Optional notes..."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" id="submit-button" class="button-large button-blue">Log Food</button>
                    <button type="button" id="cancel-logging" class="button-large button-gray">Cancel</button>
                </div>
            </form>
        </div>

        {{-- Existing food logs display --}}
        @if($foodLogs->count() > 0)
            <div class="existing-logs-section">
                <h2>Today's Food Log</h2>
                <div class="food-logs-list">
                    @foreach($foodLogs as $log)
                        <div class="food-log-entry">
                            <div class="log-header">
                                <div class="log-time">{{ $log->logged_at->format('g:i A') }}</div>
                                <form method="POST" action="{{ route('food-logs.destroy', $log) }}" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this entry?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="delete-button" title="Delete entry">×</button>
                                </form>
                            </div>
                            <div class="log-details">
                                <div class="ingredient-name">{{ $log->ingredient->name }}</div>
                                <div class="quantity-unit">{{ number_format($log->quantity, 2) }} {{ $log->unit->name }}</div>
                                @if($log->notes)
                                    <div class="log-notes">{{ $log->notes }}</div>
                                @endif
                            </div>
                            <div class="log-nutrition">
                                @php
                                    $calories = $nutritionService->calculateTotalMacro($log->ingredient, 'calories', $log->quantity);
                                    $protein = $nutritionService->calculateTotalMacro($log->ingredient, 'protein', $log->quantity);
                                    $carbs = $nutritionService->calculateTotalMacro($log->ingredient, 'carbs', $log->quantity);
                                    $fats = $nutritionService->calculateTotalMacro($log->ingredient, 'fats', $log->quantity);
                                @endphp
                                <div class="nutrition-item">
                                    <span class="nutrition-label">Cal:</span>
                                    <span class="nutrition-value">{{ round($calories) }}</span>
                                </div>
                                <div class="nutrition-item">
                                    <span class="nutrition-label">P:</span>
                                    <span class="nutrition-value">{{ round($protein, 1) }}g</span>
                                </div>
                                <div class="nutrition-item">
                                    <span class="nutrition-label">C:</span>
                                    <span class="nutrition-value">{{ round($carbs, 1) }}g</span>
                                </div>
                                <div class="nutrition-item">
                                    <span class="nutrition-label">F:</span>
                                    <span class="nutrition-value">{{ round($fats, 1) }}g</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="no-logs-message">
                <p>No food logged for this date yet.</p>
            </div>
        @endif

        {{-- Daily nutrition totals display --}}
        <div class="daily-nutrition-totals">
            <h2>Daily Totals</h2>
            <div class="totals-grid">
                <div class="total-item calories">
                    <div class="total-value">{{ round($dailyTotals['calories']) }}</div>
                    <div class="total-label">Calories</div>
                </div>
                <div class="total-item protein">
                    <div class="total-value">{{ round($dailyTotals['protein'], 1) }}g</div>
                    <div class="total-label">Protein</div>
                </div>
                <div class="total-item carbs">
                    <div class="total-value">{{ round($dailyTotals['carbs'], 1) }}g</div>
                    <div class="total-label">Carbs</div>
                </div>
                <div class="total-item fats">
                    <div class="total-value">{{ round($dailyTotals['fats'], 1) }}g</div>
                    <div class="total-label">Fats</div>
                </div>
            </div>
            
            {{-- Additional nutrition details (collapsible) --}}
            <div class="additional-totals">
                <button type="button" id="toggle-additional-totals" class="toggle-button">
                    Show More Details
                </button>
                <div id="additional-totals-content" class="additional-content hidden">
                    <div class="additional-grid">
                        <div class="additional-item">
                            <span class="additional-label">Fiber:</span>
                            <span class="additional-value">{{ round($dailyTotals['fiber'], 1) }}g</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Added Sugars:</span>
                            <span class="additional-value">{{ round($dailyTotals['added_sugars'], 1) }}g</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Sodium:</span>
                            <span class="additional-value">{{ round($dailyTotals['sodium'], 1) }}mg</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Calcium:</span>
                            <span class="additional-value">{{ round($dailyTotals['calcium'], 1) }}mg</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Iron:</span>
                            <span class="additional-value">{{ round($dailyTotals['iron'], 1) }}mg</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Potassium:</span>
                            <span class="additional-value">{{ round($dailyTotals['potassium'], 1) }}mg</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Caffeine:</span>
                            <span class="additional-value">{{ round($dailyTotals['caffeine'], 1) }}mg</span>
                        </div>
                        <div class="additional-item">
                            <span class="additional-label">Cost:</span>
                            <span class="additional-value">${{ number_format($dailyTotals['cost'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .mobile-entry-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 15px;
            background-color: #2a2a2a;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
            color: #f2f2f2;
        }
        
        .date-navigation-mobile {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 1.2em;
        }
        
        .date-navigation-mobile .nav-button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .date-navigation-mobile .nav-button:hover {
            background-color: #0056b3;
        }
        
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 25px;
            font-size: 2em;
        }
        
        .placeholder-message {
            text-align: center;
            color: #aaa;
            font-size: 1.1em;
            padding: 20px;
            border: 1px dashed #555;
            border-radius: 5px;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .food-entry-placeholder,
        .existing-logs-placeholder,
        .nutrition-totals-placeholder {
            margin-bottom: 20px;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .mobile-entry-container {
                margin: 10px;
                padding: 10px;
            }
            
            .date-navigation-mobile {
                font-size: 1.1em;
            }
            
            .date-navigation-mobile .nav-button {
                padding: 8px 12px;
                font-size: 0.9em;
            }
            
            h1 {
                font-size: 1.8em;
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .mobile-entry-container {
                margin: 5px;
                padding: 8px;
            }
            
            .date-navigation-mobile .nav-button {
                padding: 6px 10px;
                font-size: 0.8em;
            }
            
            h1 {
                font-size: 1.6em;
            }
        }
        
        /* Food selection interface styles */
        .add-food-container {
            margin-top: 20px;
        }
        
        .button-large {
            background-color: #28a745;
            color: white;
            text-align: center;
            display: block;
            width: 100%;
            box-sizing: border-box;
            border: none;
            padding: 15px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.5em;
            font-weight: bold;
        }
        
        .button-green {
            background-color: #28a745;
        }
        
        .button-green:hover {
            background-color: #218838;
        }
        
        #food-list-container {
            margin-top: 20px;
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 8px;
        }
        
        .food-list {
            display: flex;
            flex-direction: column;
        }
        
        .food-list-item {
            color: #f2f2f2;
            padding: 15px;
            text-decoration: none;
            border-bottom: 1px solid #555;
            font-size: 1.2em;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .food-name {
            flex: 1;
            min-width: 0;
            z-index: 2;
            position: relative;
            background: inherit;
            padding-right: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .food-label {
            flex-shrink: 0;
            font-size: 0.9em;
            opacity: 0.8;
            z-index: 1;
        }
        
        .food-list-item:hover {
            background-color: #4a4a4a;
        }
        
        /* Visual distinction between ingredients and meals */
        .ingredient-item {
            background-color: #2d4a3a; /* Green tint for ingredients */
        }
        
        .ingredient-item:hover {
            background-color: #3a5a4a;
        }
        
        .meal-item {
            background-color: #4a3a2d; /* Brown tint for meals */
        }
        
        .meal-item:hover {
            background-color: #5a4a3a;
        }
        
        .hidden {
            display: none;
        }

        /* Dynamic form fields styles */
        #logging-form-container {
            margin-top: 20px;
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 8px;
        }

        .selected-food-display {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #4a4a4a;
            border-radius: 5px;
        }

        .selected-food-display h3 {
            margin: 0 0 5px 0;
            color: #f2f2f2;
            font-size: 1.4em;
        }

        #selected-food-type-label {
            color: #aaa;
            font-style: italic;
            font-size: 0.9em;
        }

        .form-fields {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #f2f2f2;
            font-weight: bold;
            font-size: 1.1em;
        }

        .input-group {
            display: flex;
            align-items: center;
        }

        .large-input {
            text-align: center;
            flex-grow: 1;
            border-radius: 0; /* Remove radius from input to make it seamless with buttons */
            font-size: 2.2em;
            border: none;
            padding: 15px 10px;
            background-color: #4a4a4a;
            color: #f2f2f2;
            box-sizing: border-box;
            font-weight: bold; /* Make the value more prominent */
        }

        .large-input:focus {
            background-color: #1a1a1a;
            outline: none;
        }

        .decrement-button,
        .increment-button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 1.5em;
            touch-action: manipulation;
        }

        .decrement-button {
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
        }

        .increment-button {
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
        }

        .decrement-button:hover,
        .increment-button:hover {
            background-color: #5a6268;
        }

        .decrement-button:active,
        .increment-button:active {
            background-color: #495057;
        }

        .unit-display {
            display: block;
            text-align: center;
            color: #aaa;
            font-size: 1em;
            margin-top: 5px;
        }

        .large-textarea {
            width: 100%;
            background-color: #2a2a2a;
            border: 1px solid #555;
            border-radius: 5px;
            color: #f2f2f2;
            font-size: 1.1em;
            padding: 15px;
            resize: vertical;
            min-height: 80px;
            box-sizing: border-box;
        }

        .large-textarea:focus {
            background-color: #1a1a1a;
            border-color: #007bff;
            outline: none;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .form-actions .button-large {
            flex: 1;
        }

        .button-blue {
            background-color: #007bff;
        }

        .button-blue:hover {
            background-color: #0056b3;
        }

        .button-gray {
            background-color: #6c757d;
        }

        .button-gray:hover {
            background-color: #545b62;
        }

        /* Existing food logs display styles */
        .existing-logs-section {
            margin-top: 30px;
        }

        .existing-logs-section h2 {
            color: #f2f2f2;
            font-size: 1.4em;
            margin-bottom: 15px;
            text-align: center;
        }

        .food-logs-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .food-log-entry {
            background-color: #3a3a3a;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #007bff;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .log-time {
            color: #aaa;
            font-size: 0.9em;
            font-weight: bold;
        }

        .delete-form {
            margin: 0;
        }

        .delete-button {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .delete-button:hover {
            background-color: #c82333;
        }

        .log-details {
            margin-bottom: 10px;
        }

        .ingredient-name {
            color: #f2f2f2;
            font-size: 1.1em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .quantity-unit {
            color: #aaa;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .log-notes {
            color: #ccc;
            font-size: 0.85em;
            font-style: italic;
            margin-top: 5px;
        }

        .log-nutrition {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .nutrition-item {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .nutrition-label {
            color: #aaa;
            font-size: 0.8em;
            font-weight: bold;
        }

        .nutrition-value {
            color: #f2f2f2;
            font-size: 0.85em;
            font-weight: bold;
        }

        .no-logs-message {
            text-align: center;
            color: #aaa;
            font-size: 1.1em;
            padding: 30px 20px;
            margin-top: 30px;
            background-color: #3a3a3a;
            border-radius: 8px;
            font-style: italic;
        }

        /* Daily nutrition totals styles */
        .daily-nutrition-totals {
            margin-top: 30px;
            padding: 20px;
            background-color: #3a3a3a;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }

        .daily-nutrition-totals h2 {
            color: #f2f2f2;
            font-size: 1.4em;
            margin-bottom: 20px;
            text-align: center;
        }

        .totals-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .total-item {
            background-color: #4a4a4a;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border-left: 3px solid #007bff;
        }

        .total-item.calories {
            border-left-color: #ff6b35;
        }

        .total-item.protein {
            border-left-color: #28a745;
        }

        .total-item.carbs {
            border-left-color: #ffc107;
        }

        .total-item.fats {
            border-left-color: #dc3545;
        }

        .total-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #f2f2f2;
            margin-bottom: 5px;
        }

        .total-label {
            font-size: 0.9em;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .additional-totals {
            border-top: 1px solid #555;
            padding-top: 15px;
        }

        .toggle-button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            width: 100%;
            text-align: center;
        }

        .toggle-button:hover {
            background-color: #5a6268;
        }

        .additional-content {
            margin-top: 15px;
        }

        .additional-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .additional-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background-color: #2a2a2a;
            border-radius: 5px;
        }

        .additional-label {
            color: #aaa;
            font-size: 0.85em;
        }

        .additional-value {
            color: #f2f2f2;
            font-weight: bold;
            font-size: 0.9em;
        }

        /* Error and success message styles */
        .error-message,
        .success-message,
        .validation-errors {
            margin: 15px 0;
            padding: 0;
            border-radius: 8px;
            animation: slideIn 0.3s ease-out;
        }

        .error-message {
            background-color: #dc3545;
            border-left: 4px solid #a71e2a;
        }

        .success-message {
            background-color: #28a745;
            border-left: 4px solid #1e7e34;
        }

        .validation-errors {
            background-color: #ffc107;
            border-left: 4px solid #d39e00;
        }

        .error-content,
        .success-content,
        .validation-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
        }

        .error-text,
        .success-text,
        .validation-text {
            color: white;
            font-weight: bold;
            font-size: 1em;
            flex: 1;
        }

        .validation-text {
            color: #212529;
        }

        .error-close,
        .success-close,
        .validation-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5em;
            font-weight: bold;
            cursor: pointer;
            padding: 0;
            margin-left: 15px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .validation-close {
            color: #212529;
        }

        .error-close:hover,
        .success-close:hover,
        .validation-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }



        /* Input validation styles */
        .input-error {
            border: 2px solid #dc3545 !important;
            background-color: #2a1f1f !important;
        }

        .input-error:focus {
            border-color: #dc3545 !important;
            background-color: #1a1a1a !important;
        }

        /* Ensure minimum touch target sizes for mobile */
        @media (max-width: 768px) {
            .nav-button {
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .food-list-item {
                min-height: 44px;
                padding: 12px 15px;
            }

            .decrement-button,
            .increment-button {
                min-width: 44px;
                min-height: 44px;
                padding: 12px 16px; /* Adjust padding for mobile */
            }

            .large-input {
                min-height: 44px;
                font-size: 2em; /* Slightly smaller on mobile for better fit */
            }

            .delete-button {
                min-width: 32px;
                min-height: 32px;
                width: 32px;
                height: 32px;
                font-size: 18px;
            }

            .log-nutrition {
                gap: 10px;
            }

            .nutrition-item {
                gap: 2px;
            }

            /* Mobile responsive styles for daily totals */
            .totals-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .total-item {
                padding: 12px;
            }

            .total-value {
                font-size: 1.5em;
            }

            .total-label {
                font-size: 0.8em;
            }

            .additional-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .additional-item {
                padding: 6px 10px;
            }

            .additional-label,
            .additional-value {
                font-size: 0.8em;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Food selection interface functionality
            document.getElementById('add-food-button').addEventListener('click', function() {
                const foodListContainer = document.getElementById('food-list-container');
                foodListContainer.classList.remove('hidden');
                this.style.display = 'none';
            });

            // Food item selection - show appropriate form fields
            document.querySelectorAll('.food-list-item').forEach(item => {
                item.addEventListener('click', function(event) {
                    event.preventDefault();
                    
                    const type = this.dataset.type;
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const unit = this.dataset.unit;
                    
                    // Validate that the selected item still exists (handle deleted ingredients/meals)
                    if (!id || !name) {
                        showValidationError('Selected item is no longer available. Please refresh the page.');
                        return;
                    }
                    
                    // Hide food list and show logging form
                    document.getElementById('food-list-container').classList.add('hidden');
                    document.getElementById('logging-form-container').classList.remove('hidden');
                    
                    // Clear any previous validation errors
                    hideValidationError();
                    
                    // Set hidden form values
                    document.getElementById('selected-type').value = type;
                    document.getElementById('selected-id').value = id;
                    document.getElementById('selected-name').value = name;
                    
                    // Update display
                    document.getElementById('selected-food-name').textContent = name;
                    document.getElementById('selected-food-type-label').textContent = type === 'ingredient' ? 'Ingredient' : 'Meal';
                    
                    // Show appropriate form fields
                    const ingredientFields = document.getElementById('ingredient-fields');
                    const mealFields = document.getElementById('meal-fields');
                    
                    if (type === 'ingredient') {
                        ingredientFields.classList.remove('hidden');
                        mealFields.classList.add('hidden');
                        
                        // Set unit display
                        document.getElementById('ingredient-unit').textContent = unit || '';
                        
                        // Reset ingredient form values
                        document.getElementById('quantity').value = '1';
                        document.getElementById('ingredient-notes').value = '';
                    } else if (type === 'meal') {
                        mealFields.classList.remove('hidden');
                        ingredientFields.classList.add('hidden');
                        
                        // Reset meal form values
                        document.getElementById('portion').value = '1';
                        document.getElementById('meal-notes').value = '';
                    }
                });
            });

            // Cancel logging functionality
            document.getElementById('cancel-logging').addEventListener('click', function() {
                // Hide logging form and show add food button
                document.getElementById('logging-form-container').classList.add('hidden');
                document.getElementById('add-food-button').style.display = 'block';
                
                // Reset form
                document.getElementById('food-logging-form').reset();
                
                // Hide all form fields
                document.getElementById('ingredient-fields').classList.add('hidden');
                document.getElementById('meal-fields').classList.add('hidden');
            });

            // Increment/decrement button functionality
            document.querySelectorAll('.increment-button, .decrement-button').forEach(button => {
                button.addEventListener('click', function() {
                    const target = this.dataset.target;
                    const input = document.getElementById(target);
                    const isIncrement = this.classList.contains('increment-button');
                    
                    let currentValue = parseFloat(input.value) || 0;
                    let incrementAmount = getIncrementAmount(target);
                    
                    if (isIncrement) {
                        currentValue += incrementAmount;
                    } else {
                        // Prevent negative quantities (Requirement 6.4)
                        currentValue = Math.max(0, currentValue - incrementAmount);
                    }
                    
                    // Round to 2 decimal places to avoid floating point issues
                    input.value = Math.round(currentValue * 100) / 100;
                    
                    // Clear validation error styling when user interacts with input
                    input.classList.remove('input-error');
                    hideValidationError();
                });
            });

            // Add input validation on manual entry
            document.querySelectorAll('#quantity, #portion').forEach(input => {
                input.addEventListener('input', function() {
                    // Clear validation error styling when user types
                    this.classList.remove('input-error');
                    hideValidationError();
                    
                    // Validate positive numbers
                    const value = parseFloat(this.value);
                    if (this.value && (isNaN(value) || value < 0)) {
                        this.classList.add('input-error');
                        showValidationError('Please enter a positive number.');
                    }
                });

                input.addEventListener('blur', function() {
                    // Ensure minimum value on blur
                    const value = parseFloat(this.value);
                    if (this.value && (isNaN(value) || value <= 0)) {
                        this.value = '1';
                        this.classList.remove('input-error');
                        hideValidationError();
                    }
                });
            });

            // Function to determine increment amount based on field type and unit (Requirements 6.1, 6.2, 6.3)
            function getIncrementAmount(target) {
                if (target === 'quantity') {
                    const unit = document.getElementById('ingredient-unit').textContent.toLowerCase();
                    
                    // Requirement 6.1: grams or milliliters increment by 10
                    if (unit.includes('g') || unit.includes('ml') || unit.includes('gram') || unit.includes('milliliter')) {
                        return 10;
                    } 
                    // Requirement 6.2: kilograms, pounds, or liters increment by 0.1
                    else if (unit.includes('kg') || unit.includes('lb') || unit.includes('liter') || 
                             unit.includes('pound') || unit.includes('kilogram')) {
                        return 0.1;
                    } 
                    // Requirement 6.3: pieces or servings increment by 0.25
                    else if (unit.includes('pc') || unit.includes('serving') || unit.includes('piece') ||
                             unit.includes('pcs') || unit.includes('each') || unit.includes('item')) {
                        return 0.25;
                    }
                    
                    return 1; // Default increment for other units
                } else if (target === 'portion') {
                    // Meal portions increment by 0.25 (similar to servings)
                    return 0.25;
                }
                
                return 1; // Default
            }

            // Form submission handling with comprehensive validation
            document.getElementById('food-logging-form').addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default submission for validation
                
                const type = document.getElementById('selected-type').value;
                const selectedId = document.getElementById('selected-id').value;
                const selectedName = document.getElementById('selected-name').value;
                
                // Clear any previous validation errors
                hideValidationError();
                
                // Validate form data
                const validationResult = validateForm(type);
                if (!validationResult.isValid) {
                    showValidationError(validationResult.message);
                    return;
                }
                
                // Check if selected item still exists (edge case handling)
                if (!selectedId || !selectedName) {
                    showValidationError('Selected item is no longer available. Please select a food item again.');
                    return;
                }
                
                // Disable submit button to prevent double submission
                const submitButton = document.getElementById('submit-button');
                submitButton.disabled = true;
                submitButton.textContent = 'Logging...';
                
                // Submit the form
                this.submit();
            });

            // Delete form handling - prevent double submission
            document.querySelectorAll('.delete-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    const deleteButton = this.querySelector('.delete-button');
                    
                    // Disable button to prevent double submission
                    deleteButton.disabled = true;
                });
            });

            // Toggle additional nutrition details
            const toggleButton = document.getElementById('toggle-additional-totals');
            const additionalContent = document.getElementById('additional-totals-content');
            
            if (toggleButton && additionalContent) {
                toggleButton.addEventListener('click', function() {
                    const isHidden = additionalContent.classList.contains('hidden');
                    
                    if (isHidden) {
                        additionalContent.classList.remove('hidden');
                        this.textContent = 'Show Less Details';
                    } else {
                        additionalContent.classList.add('hidden');
                        this.textContent = 'Show More Details';
                    }
                });
            }

            // Auto-hide success/error messages after 5 seconds
            setTimeout(function() {
                const errorMessage = document.getElementById('error-message');
                const successMessage = document.getElementById('success-message');
                
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }
                if (successMessage) {
                    successMessage.style.display = 'none';
                }
            }, 5000);
        });

        // Validation functions
        function validateForm(type) {
            if (type === 'ingredient') {
                const quantity = document.getElementById('quantity').value;
                const quantityNum = parseFloat(quantity);
                
                if (!quantity || quantity.trim() === '') {
                    return { isValid: false, message: 'Please enter a quantity for the ingredient.' };
                }
                
                if (isNaN(quantityNum) || quantityNum <= 0) {
                    return { isValid: false, message: 'Quantity must be a positive number.' };
                }
                
                if (quantityNum > 10000) {
                    return { isValid: false, message: 'Quantity seems too large. Please check your input.' };
                }
                
            } else if (type === 'meal') {
                const portion = document.getElementById('portion').value;
                const portionNum = parseFloat(portion);
                
                if (!portion || portion.trim() === '') {
                    return { isValid: false, message: 'Please enter a portion size for the meal.' };
                }
                
                if (isNaN(portionNum) || portionNum <= 0) {
                    return { isValid: false, message: 'Portion must be a positive number.' };
                }
                
                if (portionNum > 100) {
                    return { isValid: false, message: 'Portion size seems too large. Please check your input.' };
                }
            } else {
                return { isValid: false, message: 'Please select a food item first.' };
            }
            
            return { isValid: true };
        }

        function showValidationError(message) {
            const errorContainer = document.getElementById('validation-errors');
            const errorText = errorContainer.querySelector('.validation-text');
            
            errorText.textContent = message;
            errorContainer.classList.remove('hidden');
            
            // Scroll to error message
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function hideValidationError() {
            const errorContainer = document.getElementById('validation-errors');
            errorContainer.classList.add('hidden');
        }

        // Handle page visibility changes to re-enable form if needed
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Re-enable submit button if page becomes visible again
                const submitButton = document.getElementById('submit-button');
                if (submitButton && submitButton.disabled) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Log Food';
                }
            }
        });
    </script>
@endsection