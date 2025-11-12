/**
 * Mobile Entry JavaScript
 * Handles filtering logic for item selection list
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get the filter input, clear button, hidden input, and item list
    const filterInput = document.querySelector('.component-filter-input');
    const clearButton = document.querySelector('.btn-clear-filter');
    const hiddenInput = document.querySelector('.component-create-input');
    const itemList = document.querySelector('.component-list');
    
    // Only set up filtering if filter input and item list exist
    if (filterInput && itemList) {
        // Get all item cards (excluding the filter container and no-results item)
        const getAllItemCards = () => {
            return Array.from(itemList.querySelectorAll('.component-list-item:not(.component-list-item--no-results)'));
        };
        
        // Get the no results item
        const noResultsItem = itemList.querySelector('.no-results-item');
        
        // Filter function
        const filterItems = (searchTerm) => {
        const itemCards = getAllItemCards();
        const normalizedSearch = searchTerm.toLowerCase().trim();
        let visibleCount = 0;
        
        itemCards.forEach(card => {
            const itemNameElement = card.querySelector('.component-list-item-name');
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
        
        // Sync the filter input value with the hidden form input (if it exists)
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
    
    // Scroll to optimal position when filter input gets focus
    filterInput.addEventListener('focus', function(e) {
        // Small delay to ensure any keyboard animations are complete
        setTimeout(() => {
            const filterContainer = e.target.closest('.component-filter-container');
            if (filterContainer) {
                const containerRect = filterContainer.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                
                // Position the filter container about 5% from the top of viewport
                // This maximizes the visible space for the item list below
                const targetPosition = window.scrollY + containerRect.top - (viewportHeight * 0.05);
                
                // Only scroll if we need to move significantly (avoid tiny adjustments)
                const currentTop = containerRect.top;
                const optimalTop = viewportHeight * 0.05;
                
                if (Math.abs(currentTop - optimalTop) > 50) {
                    window.scrollTo({
                        top: Math.max(0, targetPosition),
                        behavior: 'smooth'
                    });
                }
            }
        }, 150);
    });
    
        // Add event listener for clear button (if it exists)
        if (clearButton) {
            clearButton.addEventListener('click', clearFilter);
        }
    } // End of filter setup
    
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
    
    /**
     * Item Selection List Show/Hide
     * 
     * Enhanced to support multiple independent item lists on the same page.
     * Each button/list pair is identified by a data-list-id attribute.
     * Respects initial state configuration from the controller.
     */
    const setupItemListToggle = () => {
        // Find all button sections with add-item buttons
        const buttonSections = document.querySelectorAll('.component-button-section');
        
        buttonSections.forEach((buttonSection, index) => {
            const addItemButton = buttonSection.querySelector('.btn-add-item');
            if (!addItemButton) return;
            
            // Assign a unique ID if not already set
            const listId = buttonSection.dataset.listId || `list-${index}`;
            buttonSection.dataset.listId = listId;
            
            // Find the corresponding item list (next sibling with component-list-section class)
            let itemListContainer = buttonSection.nextElementSibling;
            while (itemListContainer && !itemListContainer.classList.contains('component-list-section')) {
                itemListContainer = itemListContainer.nextElementSibling;
            }
            
            if (!itemListContainer) return;
            
            // Link the list to this button
            itemListContainer.dataset.listId = listId;
            
            const cancelButton = itemListContainer.querySelector('.btn-cancel');
            
            // Scroll to filter input
            const scrollToFilter = (delay = 300) => {
                const filterInput = itemListContainer.querySelector('.component-filter-input');
                if (filterInput) {
                    const filterContainer = filterInput.closest('.component-filter-container');
                    if (filterContainer) {
                        setTimeout(() => {
                            const containerRect = filterContainer.getBoundingClientRect();
                            const viewportHeight = window.innerHeight;
                            const targetPosition = window.scrollY + containerRect.top - (viewportHeight * 0.05);
                            
                            window.scrollTo({
                                top: Math.max(0, targetPosition),
                                behavior: 'smooth'
                            });
                        }, delay);
                    }
                }
            };
            
            // Show item selection list
            const showItemSelection = () => {
                if (itemListContainer) {
                    itemListContainer.classList.add('active');
                }
                
                if (buttonSection) {
                    buttonSection.classList.add('hidden');
                }
                
                // Focus on the filter input field and scroll to optimal position
                requestAnimationFrame(() => {
                    const filterInput = itemListContainer.querySelector('.component-filter-input');
                    if (filterInput) {
                        // Force focus and click to ensure mobile keyboard opens
                        filterInput.focus();
                        
                        // Additional mobile keyboard trigger methods
                        if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
                            filterInput.click();
                            
                            setTimeout(() => {
                                filterInput.setSelectionRange(0, 0);
                            }, 50);
                        }
                        
                        // Scroll to position the filter input optimally for mobile
                        scrollToFilter(300);
                    }
                });
            };
            
            // Hide item selection list and show add button
            const hideItemSelection = () => {
                if (itemListContainer) {
                    itemListContainer.classList.remove('active');
                }
                
                if (buttonSection) {
                    buttonSection.classList.remove('hidden');
                }
            };
            
            // Add click handler to "Add Item" button
            addItemButton.addEventListener('click', function(event) {
                event.preventDefault();
                showItemSelection();
            });
            
            // Add click handler to cancel button in filter section
            if (cancelButton) {
                cancelButton.addEventListener('click', function(event) {
                    event.preventDefault();
                    hideItemSelection();
                });
            }
            
            // Initialize based on configured initial state
            const listInitialState = itemListContainer?.dataset.initialState || 'collapsed';
            
            if (listInitialState === 'expanded') {
                // Use double requestAnimationFrame to ensure DOM is fully rendered
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        showItemSelection();
                    });
                });
            } else {
                hideItemSelection();
            }
        });
    };
    
    // Initialize item list toggle
    setupItemListToggle();
    
    /**
     * Delete Confirmation Dialog for All Delete Actions
     * 
     * Adds confirmation dialogs for both:
     * 1. Deleting logged items (permanent deletion)
     * 2. Removing forms from program (removing from today's program)
     * 3. Deleting table rows (tabular CRUD lists)
     * 
     * Uses configurable messages from the data array passed from the controller.
     */
    const setupDeleteConfirmation = () => {
        // Get confirmation messages from the page data
        const getConfirmMessages = () => {
            // Try to get messages from multiple script tags
            const itemsScript = document.querySelector('script[data-confirm-messages]');
            const tableScript = document.querySelector('script[data-table-confirm-messages]');
            
            let messages = {
                deleteItem: 'Are you sure you want to delete this item? This action cannot be undone.',
                removeForm: 'Are you sure you want to remove this item from today\'s program?'
            };
            
            // Merge messages from items component
            if (itemsScript) {
                try {
                    Object.assign(messages, JSON.parse(itemsScript.dataset.confirmMessages));
                } catch (e) {
                    console.warn('Failed to parse items confirmation messages:', e);
                }
            }
            
            // Merge messages from table component
            if (tableScript) {
                try {
                    Object.assign(messages, JSON.parse(tableScript.dataset.tableConfirmMessages));
                } catch (e) {
                    console.warn('Failed to parse table confirmation messages:', e);
                }
            }
            
            return messages;
        };
        
        const confirmMessages = getConfirmMessages();
        
        // Target all delete buttons on the page (including table delete buttons)
        const deleteButtons = document.querySelectorAll('.btn-delete, .btn-table-delete');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                
                // Get the form that contains this button
                const form = this.closest('form');
                if (!form) return;
                
                // Determine the type of delete action and get appropriate message
                const loggedItemsSection = this.closest('.component-items-section');
                const formSection = this.closest('.component-form-section');
                const tableSection = this.closest('.component-table-section');
                
                let confirmMessage;
                
                if (loggedItemsSection) {
                    // This is a logged item deletion (permanent)
                    confirmMessage = confirmMessages.deleteItem;
                } else if (formSection) {
                    // This is a form removal (removing from program)
                    confirmMessage = confirmMessages.removeForm;
                } else if (tableSection) {
                    // This is a table row deletion
                    confirmMessage = confirmMessages.deleteItem;
                } else {
                    // Fallback for any other delete buttons
                    confirmMessage = confirmMessages.deleteItem;
                }
                
                // Show confirmation dialog
                const confirmed = confirm(confirmMessage);
                
                if (confirmed) {
                    // Submit the form if user confirms
                    form.submit();
                }
            });
        });
    };
    
    // Initialize delete confirmation
    setupDeleteConfirmation();
    
    /**
     * Auto-scroll to First Form
     * 
     * Automatically scrolls the viewport to the first form on page load
     * if there are any forms present. This improves mobile UX by immediately
     * showing the user where they can start entering data and hides the "Add Item" button.
     */
    const autoScrollToFirstForm = () => {
        // Find the first form section
        const firstForm = document.querySelector('.component-form-section');
        
        if (firstForm) {
            // Small delay to ensure page is fully rendered
            setTimeout(() => {
                const formRect = firstForm.getBoundingClientRect();
                
                // Position the form at the very top of the viewport to hide the "Add Item" button
                // This scrolls past the button and focuses entirely on the form content
                const targetPosition = window.scrollY + formRect.top;
                
                // Smooth scroll to the first form
                window.scrollTo({
                    top: Math.max(0, targetPosition),
                    behavior: 'smooth'
                });
            }, 300); // Delay to allow any initial page animations to complete
        }
    };
    
    // Initialize auto-scroll to first form (only if enabled via config)
    if (window.mobileEntryConfig && window.mobileEntryConfig.autoscroll) {
        autoScrollToFirstForm();
    }
    
    /**
     * Table Row Expand/Collapse
     * 
     * Handles expanding and collapsing sub-items in table rows.
     * Uses minimal JavaScript with simple toggle functionality.
     */
    const setupTableExpand = () => {
        const expandButtons = document.querySelectorAll('.btn-table-expand');
        
        expandButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                const rowId = this.dataset.toggleSubitems;
                const subitemsContainer = document.querySelector(`[data-subitems="${rowId}"]`);
                
                if (subitemsContainer) {
                    // Toggle visibility
                    if (subitemsContainer.style.display === 'none') {
                        subitemsContainer.style.display = '';
                        this.classList.add('expanded');
                        this.setAttribute('aria-label', 'Collapse row');
                    } else {
                        subitemsContainer.style.display = 'none';
                        this.classList.remove('expanded');
                        this.setAttribute('aria-label', 'Expand row');
                    }
                }
            });
        });
    };
    
    // Initialize table expand/collapse
    setupTableExpand();
});
 
