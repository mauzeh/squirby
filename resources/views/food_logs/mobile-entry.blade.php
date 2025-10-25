@extends('app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/mobile-entry/entry-interface.css') }}">
@endsection

@section('content')
    <div class="mobile-entry-container">
        <x-mobile-entry.date-navigation 
            :selected-date="$selectedDate" 
            route-name="food-logs.mobile-entry" 
        />

        <x-mobile-entry.page-title :selected-date="$selectedDate" />

        {{-- Daily nutrition totals display --}}
        <div class="daily-nutrition-totals">
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

        {{-- Food selection interface --}}
        <x-mobile-entry.add-item-button 
            id="add-food-button"
            label="Add Food"
            target-container="food-list-container"
            container-class="add-food-container"
        />

        <div class="item-list-container hidden" id="food-list-container">
            <div class="food-list item-list">
                @foreach ($meals as $meal)
                    <a href="#" class="food-list-item item-list-item meal-item"
                       data-type="meal"
                       data-id="{{ $meal->id }}"
                       data-name="{{ $meal->name }}">
                        <span class="food-name item-name">{{ $meal->name }}</span>
                        <span class="food-label item-label"><em>Meal</em></span>
                    </a>
                @endforeach
                
                @foreach ($ingredients as $ingredient)
                    <a href="#" class="food-list-item item-list-item ingredient-item" 
                       data-type="ingredient" 
                       data-id="{{ $ingredient->id }}"
                       data-name="{{ $ingredient->name }}"
                       data-unit="{{ $ingredient->baseUnit->name }}"
                       data-base-quantity="{{ $ingredient->base_quantity }}">
                        <span class="food-name item-name">{{ $ingredient->name }}</span>
                        <span class="food-label item-label"><em>Ingredient</em></span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Message system using shared component --}}
        <x-mobile-entry.message-system :errors="$errors ?? null" />

        {{-- Dynamic form fields for logging --}}
        <div class="item-list-container hidden" id="logging-form-container">
            <form id="food-logging-form" method="POST" action="{{ route('food-logs.store') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="mobile-entry">
                <input type="hidden" name="date" value="{{ $selectedDate->toDateString() }}">
                <input type="hidden" name="selected_type" id="selected-type">
                <input type="hidden" name="selected_id" id="selected-id">
                <input type="hidden" name="selected_name" id="selected-name">



                <div class="selected-food-display">
                    <h3 id="selected-food-name"></h3>
                    <span id="selected-food-type-label"></span>
                </div>

                {{-- Ingredient form fields --}}
                <div id="ingredient-fields" class="form-fields hidden">
                    <div class="form-group">
                        <label for="quantity" class="form-label-centered">Quantity:</label>
                        <div class="input-group">
                            <button type="button" class="decrement-button" data-target="quantity">-</button>
                            <input type="number" name="quantity" id="quantity" class="large-input" inputmode="decimal" value="1" step="0.01" min="0">
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
                    <x-mobile-entry.number-input 
                        name="portion"
                        id="portion"
                        :value="1"
                        label="Portion:"
                        unit="servings"
                        :step="0.01"
                        :min="0"
                    />
                    
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
                        <x-mobile-entry.item-card 
                            :title="$log->ingredient->name"
                            :delete-route="route('food-logs.destroy', $log)"
                            delete-confirm-text="Are you sure you want to delete this entry?"
                            :hidden-fields="[]"
                        >
                            <div class="log-header">
                                <div class="log-time">{{ $log->logged_at->format('g:i A') }}</div>
                            </div>
                            <div class="log-details">
                                <div class="quantity-unit">{{ number_format($log->quantity, 2) }} {{ $log->unit->name }}</div>
                                @if($log->notes)
                                    <div class="log-notes details">{{ $log->notes }}</div>
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
                        </x-mobile-entry.item-card>
                    @endforeach
                </div>
            </div>
        @else
            <x-mobile-entry.empty-state message="No food logged for this date yet." />
        @endif


    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Food selection interface functionality is handled by shared add-item-button component

            // Food item selection - show appropriate form fields
            document.querySelectorAll('.food-list-item').forEach(item => {
                item.addEventListener('click', function(event) {
                    event.preventDefault();
                    
                    const type = this.dataset.type;
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const unit = this.dataset.unit;
                    const baseQuantity = this.dataset.baseQuantity;
                    
                    // Validate that the selected item still exists (handle deleted ingredients/meals)
                    if (!id || !name) {
                        if (window.showValidationError) {
                            window.showValidationError('Selected item is no longer available. Please refresh the page.');
                        }
                        return;
                    }
                    
                    // Hide food list and show logging form
                    document.getElementById('food-list-container').classList.add('hidden');
                    document.getElementById('logging-form-container').classList.remove('hidden');
                    
                    // Clear any previous validation errors
                    if (window.hideValidationError) {
                        window.hideValidationError();
                    }
                    
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
                        
                        // Reset ingredient form values - use base_quantity as default
                        document.getElementById('quantity').value = baseQuantity || '1';
                        document.getElementById('ingredient-notes').value = '';
                    } else if (type === 'meal') {
                        mealFields.classList.remove('hidden');
                        ingredientFields.classList.add('hidden');
                        
                        // Reset meal form values
                        document.getElementById('portion').value = '1';
                        document.getElementById('meal-notes').value = '';
                    }
                    
                    // Scroll to the logging form to ensure it's visible
                    setTimeout(() => {
                        document.getElementById('logging-form-container').scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }, 100);
                });
            });

            // Cancel logging functionality
            document.getElementById('cancel-logging').addEventListener('click', function() {
                // Hide logging form and show add food button
                document.getElementById('logging-form-container').classList.add('hidden');
                const addButton = document.getElementById('add-food-button');
                if (addButton) {
                    addButton.style.display = 'block';
                }
                
                // Reset form
                document.getElementById('food-logging-form').reset();
                
                // Hide all form fields
                document.getElementById('ingredient-fields').classList.add('hidden');
                document.getElementById('meal-fields').classList.add('hidden');
            });

            // Increment/decrement functionality for quantity (needs custom logic for dynamic units)
            document.querySelectorAll('[data-target="quantity"]').forEach(button => {
                button.addEventListener('click', function() {
                    const input = document.getElementById('quantity');
                    const isIncrement = this.classList.contains('increment-button');
                    
                    let currentValue = parseFloat(input.value) || 0;
                    let incrementAmount = getIncrementAmountForQuantity();
                    
                    if (isIncrement) {
                        currentValue += incrementAmount;
                    } else {
                        currentValue = Math.max(0, currentValue - incrementAmount);
                    }
                    
                    // Round to 2 decimal places to avoid floating point issues
                    input.value = Math.round(currentValue * 100) / 100;
                    
                    // Clear validation error styling when user interacts with input
                    input.classList.remove('input-error');
                    if (window.hideValidationError) {
                        window.hideValidationError();
                    }
                });
            });
            
            // Function to determine increment amount based on unit
            function getIncrementAmountForQuantity() {
                const unit = document.getElementById('ingredient-unit').textContent.toLowerCase();
                
                // grams or milliliters increment by 10
                if (unit.includes('g') || unit.includes('ml') || unit.includes('gram') || unit.includes('milliliter')) {
                    return 10;
                } 
                // kilograms, pounds, or liters increment by 0.1
                else if (unit.includes('kg') || unit.includes('lb') || unit.includes('liter') || 
                         unit.includes('pound') || unit.includes('kilogram')) {
                    return 0.1;
                } 
                // pieces or servings increment by 0.25
                else if (unit.includes('pc') || unit.includes('serving') || unit.includes('piece') ||
                         unit.includes('pcs') || unit.includes('each') || unit.includes('item')) {
                    return 0.25;
                }
                
                return 1; // Default increment for other units
            }

            // Add input validation on manual entry
            document.querySelectorAll('#quantity, #portion').forEach(input => {
                input.addEventListener('input', function() {
                    // Clear validation error styling when user types
                    this.classList.remove('input-error');
                    if (window.hideValidationError) {
                        window.hideValidationError();
                    }
                    
                    // Validate positive numbers
                    const value = parseFloat(this.value);
                    if (this.value && (isNaN(value) || value < 0)) {
                        this.classList.add('input-error');
                        if (window.showValidationError) {
                            window.showValidationError('Please enter a positive number.');
                        }
                    }
                });

                input.addEventListener('blur', function() {
                    // Ensure minimum value on blur
                    const value = parseFloat(this.value);
                    if (this.value && (isNaN(value) || value <= 0)) {
                        this.value = '1';
                        this.classList.remove('input-error');
                        if (window.hideValidationError) {
                            window.hideValidationError();
                        }
                    }
                });
            });



            // Form submission handling with comprehensive validation
            document.getElementById('food-logging-form').addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default submission for validation
                
                const type = document.getElementById('selected-type').value;
                const selectedId = document.getElementById('selected-id').value;
                const selectedName = document.getElementById('selected-name').value;
                
                // Clear any previous validation errors
                if (window.hideValidationError) {
                    window.hideValidationError();
                }
                
                // Validate form data
                const validationResult = validateForm(type);
                if (!validationResult.isValid) {
                    if (window.showValidationError) {
                        window.showValidationError(validationResult.message);
                    }
                    return;
                }
                
                // Check if selected item still exists (edge case handling)
                if (!selectedId || !selectedName) {
                    if (window.showValidationError) {
                        window.showValidationError('Selected item is no longer available. Please select a food item again.');
                    }
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

        // Validation functions are now provided globally by the message-system component

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