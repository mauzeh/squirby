/**
 * Mobile Entry JavaScript
 * Handles filtering logic for item selection list
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get the filter input, clear button, hidden input, and item list
    const filterInput = document.querySelector('.item-filter-input');
    const clearButton = document.querySelector('.btn-clear-filter');
    const hiddenInput = document.querySelector('.create-item-input');
    const itemList = document.querySelector('.item-selection-list');
    
    if (!filterInput || !itemList || !clearButton || !hiddenInput) {
        return; // Exit if elements don't exist
    }
    
    // Get all item cards (excluding the filter container and no-results item)
    const getAllItemCards = () => {
        return Array.from(itemList.querySelectorAll('.item-selection-card:not(.item-selection-card--no-results)'));
    };
    
    // Get the no results item
    const noResultsItem = itemList.querySelector('.no-results-item');
    
    // Filter function
    const filterItems = (searchTerm) => {
        const itemCards = getAllItemCards();
        const normalizedSearch = searchTerm.toLowerCase().trim();
        let visibleCount = 0;
        
        itemCards.forEach(card => {
            const itemNameElement = card.querySelector('.item-name');
            if (!itemNameElement) return;
            
            const itemName = itemNameElement.textContent.toLowerCase();
            const listItem = card.closest('li');
            
            if (normalizedSearch === '' || itemName.includes(normalizedSearch)) {
                // Show the item
                listItem.style.display = '';
                visibleCount++;
            } else {
                // Hide the item
                listItem.style.display = 'none';
            }
        });
        
        // Show/hide no results item based on whether we have visible items and a search term
        if (noResultsItem) {
            if (normalizedSearch !== '' && visibleCount === 0) {
                noResultsItem.style.display = '';
            } else {
                noResultsItem.style.display = 'none';
            }
        }
        
        // Show/hide clear button based on whether there's text in the input
        if (clearButton) {
            if (normalizedSearch !== '') {
                clearButton.style.display = '';
            } else {
                clearButton.style.display = 'none';
            }
        }
        
        // Sync the filter input value with the hidden form input
        if (hiddenInput) {
            hiddenInput.value = searchTerm;
        }
    };
    
    // Clear filter function
    const clearFilter = () => {
        filterInput.value = '';
        filterItems('');
        filterInput.focus();
    };
    
    // Add event listener for input changes
    filterInput.addEventListener('input', function(e) {
        filterItems(e.target.value);
    });
    
    // Also handle keyup for better responsiveness
    filterInput.addEventListener('keyup', function(e) {
        filterItems(e.target.value);
    });
    
    // Clear filter when input is cleared
    filterInput.addEventListener('change', function(e) {
        if (e.target.value === '') {
            filterItems('');
        }
    });
    
    // Add event listener for clear button
    clearButton.addEventListener('click', clearFilter);
    
    /**
     * Numeric Input Increment/Decrement System
     * 
     * This system provides interactive +/- buttons for numeric input fields with:
     * - Configurable increment amounts (integers or decimals)
     * - Boundary enforcement (min/max values)
     * - Button state management (disabled at limits)
     * - Accessibility support (ARIA labels, keyboard navigation)
     * - Manual input preservation (typing still works)
     * 
     * Configuration comes from the Laravel controller via data attributes:
     * - data-increment: Step amount for +/- buttons
     * - data-min: Minimum allowed value
     * - data-max: Maximum allowed value (empty = no limit)
     * 
     * HTML Structure Expected:
     * <div class="number-input-group" data-increment="1" data-min="0" data-max="">
     *   <button class="decrement-button">-</button>
     *   <input class="number-input" type="number" min="0" step="1">
     *   <button class="increment-button">+</button>
     * </div>
     * 
     * Features:
     * - Respects min/max boundaries from controller configuration
     * - Disables buttons when limits are reached
     * - Supports decimal increments (0.5, 0.25, etc.)
     * - Dispatches input events for other listeners
     * - Updates button states on manual input changes
     * - Handles edge cases (empty values, invalid numbers)
     */
    const setupNumericInputs = () => {
        // Find all numeric input groups on the page
        const numberInputGroups = document.querySelectorAll('.number-input-group');
        
        // Set up each numeric input group independently
        numberInputGroups.forEach(group => {
            // Get the required DOM elements for this group
            const input = group.querySelector('.number-input');
            const decrementBtn = group.querySelector('.decrement-button');
            const incrementBtn = group.querySelector('.increment-button');
            
            // Skip this group if any required elements are missing
            if (!input || !decrementBtn || !incrementBtn) return;
            
            // Extract configuration from data attributes set by the Blade template
            // These values come from the Laravel controller configuration
            const increment = parseFloat(group.dataset.increment) || 1;  // Default to 1 if not set
            const min = parseFloat(group.dataset.min) || 0;              // Default to 0 if not set
            const max = group.dataset.max ? parseFloat(group.dataset.max) : null; // null = no limit
            
            /**
             * Decrement Function
             * Decreases the input value by the configured increment amount
             * Respects the minimum boundary and updates button states
             */
            const decrementValue = () => {
                // Get current value, defaulting to 0 if empty or invalid
                const currentValue = parseFloat(input.value) || 0;
                
                // Calculate new value by subtracting the increment
                const newValue = currentValue - increment;
                
                // Only update if the new value doesn't go below the minimum
                if (newValue >= min) {
                    // Round to avoid floating-point precision issues
                    // Determine decimal places from increment value
                    const decimalPlaces = getDecimalPlaces(increment);
                    input.value = parseFloat(newValue.toFixed(decimalPlaces));
                    
                    // Dispatch input event so other code can listen for changes
                    // This ensures form validation, auto-save, etc. still work
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                // Update button disabled states after the change
                updateButtonStates();
            };
            
            /**
             * Increment Function
             * Increases the input value by the configured increment amount
             * Respects the maximum boundary (if set) and updates button states
             */
            const incrementValue = () => {
                // Get current value, defaulting to 0 if empty or invalid
                const currentValue = parseFloat(input.value) || 0;
                
                // Calculate new value by adding the increment
                const newValue = currentValue + increment;
                
                // Only update if there's no max limit OR the new value doesn't exceed it
                if (max === null || newValue <= max) {
                    // Round to avoid floating-point precision issues
                    // Determine decimal places from increment value
                    const decimalPlaces = getDecimalPlaces(increment);
                    input.value = parseFloat(newValue.toFixed(decimalPlaces));
                    
                    // Dispatch input event for other listeners
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                // Update button disabled states after the change
                updateButtonStates();
            };
            
            /**
             * Button State Management
             * Updates the disabled state of increment/decrement buttons based on current value
             * This provides visual feedback when users reach min/max limits
             */
            const updateButtonStates = () => {
                // Get current value, treating empty/invalid as 0
                const currentValue = parseFloat(input.value) || 0;
                
                // Disable decrement button if we're at or below the minimum
                // This prevents users from going below the configured limit
                decrementBtn.disabled = currentValue <= min;
                
                // Handle increment button based on whether there's a maximum limit
                if (max !== null) {
                    // If there's a max limit, disable when we reach or exceed it
                    incrementBtn.disabled = currentValue >= max;
                } else {
                    // If no max limit, increment button is always enabled
                    incrementBtn.disabled = false;
                }
            };
            
            /**
             * Event Listener Setup
             * Connects the button functions to click events and sets up input monitoring
             */
            
            // Connect decrement button to its function
            decrementBtn.addEventListener('click', decrementValue);
            
            // Connect increment button to its function
            incrementBtn.addEventListener('click', incrementValue);
            
            // Monitor manual input changes (when user types directly)
            // This ensures button states update even when users don't use the buttons
            input.addEventListener('input', updateButtonStates);
            
            // Set initial button states when the page loads
            // This handles cases where inputs have default values
            updateButtonStates();
        });
    };
    
    /**
     * Helper function to determine decimal places from increment value
     * Used to properly round values and avoid floating-point precision issues
     * 
     * Examples:
     * - getDecimalPlaces(1) returns 0 (whole numbers)
     * - getDecimalPlaces(0.1) returns 1 (one decimal place)
     * - getDecimalPlaces(0.25) returns 2 (two decimal places)
     * - getDecimalPlaces(0.001) returns 3 (three decimal places)
     */
    const getDecimalPlaces = (value) => {
        const str = value.toString();
        if (str.indexOf('.') !== -1 && str.indexOf('e-') === -1) {
            return str.split('.')[1].length;
        } else if (str.indexOf('e-') !== -1) {
            const parts = str.split('e-');
            return parseInt(parts[1], 10);
        }
        return 0;
    };
    
    /**
     * Initialize Numeric Input System
     * 
     * This function is called when the DOM is ready to set up all numeric inputs
     * on the page. It's safe to call multiple times and will only affect elements
     * that haven't been set up yet.
     * 
     * The system is designed to be:
     * - Modular: Each input group is independent
     * - Configurable: All behavior comes from controller data
     * - Accessible: Proper ARIA labels and keyboard support
     * - Robust: Handles edge cases and invalid input gracefully
     * - Extensible: Easy to add new numeric fields or modify behavior
     */
    setupNumericInputs();
});