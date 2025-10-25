/**
 * Mobile Entry Shared JavaScript Utilities
 * 
 * This file contains shared utilities for mobile-entry interfaces across
 * lift-logs and food-logs templates. It provides modular functions for
 * message systems, form handling, and navigation.
 * 
 * Requirements: 5.1, 5.2, 9.1
 */

// Main namespace for mobile entry utilities
window.MobileEntry = window.MobileEntry || {};

/**
 * Message System Utilities
 * Handles error, success, and validation message display and auto-hide functionality
 */
MobileEntry.Messages = {
    /**
     * Auto-hide messages after specified duration
     * @param {number} duration - Duration in milliseconds (default: 5000)
     */
    autoHide: function(duration = 5000) {
        setTimeout(function() {
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
            if (successMessage) {
                successMessage.style.display = 'none';
            }
        }, duration);
    },

    /**
     * Show a message with specified type
     * @param {string} message - Message text to display
     * @param {string} type - Message type ('error', 'success', 'validation')
     * @param {string} containerId - Optional container ID (defaults based on type)
     */
    show: function(message, type, containerId = null) {
        let container;
        
        if (containerId) {
            container = document.getElementById(containerId);
        } else {
            // Default container IDs based on type
            const containerMap = {
                'error': 'error-message',
                'success': 'success-message',
                'validation': 'validation-errors'
            };
            container = document.getElementById(containerMap[type]);
        }
        
        if (container) {
            const messageText = container.querySelector('.message-text');
            if (messageText) {
                messageText.textContent = message;
            }
            
            container.classList.remove('hidden');
            container.style.display = 'block';
            
            // Scroll to message for better visibility
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    },

    /**
     * Hide a specific message by ID
     * @param {string} messageId - ID of the message container to hide
     */
    hide: function(messageId) {
        const container = document.getElementById(messageId);
        if (container) {
            container.classList.add('hidden');
            container.style.display = 'none';
        }
    },

    /**
     * Show validation error message
     * @param {string} message - Error message to display
     */
    showValidationError: function(message) {
        this.show(message, 'validation');
    },

    /**
     * Hide validation error message
     */
    hideValidationError: function() {
        this.hide('validation-errors');
    }
};

/**
 * Form Utilities
 * Handles form validation, button interactions, and submission management
 */
MobileEntry.Forms = {
    /**
     * Setup increment/decrement buttons for numeric inputs
     * @param {string} selector - CSS selector for buttons (default: '.increment-button, .decrement-button')
     */
    setupIncrementButtons: function(selector = '.increment-button, .decrement-button') {
        document.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', function() {
                const target = this.dataset.target || this.dataset.field;
                const input = document.getElementById(target);
                
                if (!input) return;
                
                const isIncrement = this.classList.contains('increment-button');
                let currentValue = parseFloat(input.value) || 0;
                let incrementAmount = MobileEntry.Forms.getIncrementAmount(target, input);
                
                if (isIncrement) {
                    currentValue += incrementAmount;
                } else {
                    // Prevent negative values
                    currentValue = Math.max(0, currentValue - incrementAmount);
                }
                
                // Round to 2 decimal places to avoid floating point issues
                input.value = Math.round(currentValue * 100) / 100;
                
                // Clear validation error styling
                input.classList.remove('input-error');
                MobileEntry.Messages.hideValidationError();
            });
        });
    },

    /**
     * Get appropriate increment amount based on field type and unit
     * @param {string} fieldId - ID of the input field
     * @param {HTMLElement} input - Input element
     * @returns {number} Increment amount
     */
    getIncrementAmount: function(fieldId, input) {
        // Weight fields increment by 5
        if (fieldId.includes('weight')) {
            return 5;
        }
        
        // Quantity fields - check unit for smart increments
        if (fieldId.includes('quantity')) {
            const unitElement = document.getElementById('ingredient-unit');
            if (unitElement) {
                const unit = unitElement.textContent.toLowerCase();
                
                // Grams or milliliters increment by 10
                if (unit.includes('g') || unit.includes('ml') || unit.includes('gram') || unit.includes('milliliter')) {
                    return 10;
                }
                // Kilograms, pounds, or liters increment by 0.1
                else if (unit.includes('kg') || unit.includes('lb') || unit.includes('liter') || 
                         unit.includes('pound') || unit.includes('kilogram')) {
                    return 0.1;
                }
                // Pieces or servings increment by 0.25
                else if (unit.includes('pc') || unit.includes('serving') || unit.includes('piece') ||
                         unit.includes('pcs') || unit.includes('each') || unit.includes('item')) {
                    return 0.25;
                }
            }
        }
        
        // Portion fields increment by 0.25
        if (fieldId.includes('portion')) {
            return 0.25;
        }
        
        // Default increment
        return 1;
    },

    /**
     * Validate form data with extensible rule system
     * @param {FormData|Object} formData - Form data to validate
     * @param {Object} rules - Validation rules
     * @returns {Object} Validation result with isValid and message properties
     */
    validateForm: function(formData, rules = {}) {
        // Convert FormData to object if needed
        const data = formData instanceof FormData ? Object.fromEntries(formData) : formData;
        
        // Default validation rules
        const defaultRules = {
            positiveNumbers: ['quantity', 'portion', 'weight', 'reps', 'rounds'],
            required: [],
            maxValues: {
                quantity: 10000,
                portion: 100,
                weight: 5000,
                reps: 1000,
                rounds: 100
            }
        };
        
        // Merge with custom rules
        const validationRules = { ...defaultRules, ...rules };
        
        // Check required fields
        for (const field of validationRules.required) {
            if (!data[field] || data[field].toString().trim() === '') {
                return { 
                    isValid: false, 
                    message: `Please enter a value for ${field.replace('_', ' ')}.` 
                };
            }
        }
        
        // Check positive numbers
        for (const field of validationRules.positiveNumbers) {
            if (data[field] !== undefined && data[field] !== '') {
                const value = parseFloat(data[field]);
                if (isNaN(value) || value <= 0) {
                    return { 
                        isValid: false, 
                        message: `${field.charAt(0).toUpperCase() + field.slice(1)} must be a positive number.` 
                    };
                }
                
                // Check maximum values
                if (validationRules.maxValues[field] && value > validationRules.maxValues[field]) {
                    return { 
                        isValid: false, 
                        message: `${field.charAt(0).toUpperCase() + field.slice(1)} seems too large. Please check your input.` 
                    };
                }
            }
        }
        
        return { isValid: true };
    },

    /**
     * Prevent double form submission
     * @param {HTMLFormElement} form - Form element to protect
     * @param {string} submitButtonSelector - Selector for submit button (default: 'button[type="submit"]')
     */
    preventDoubleSubmit: function(form, submitButtonSelector = 'button[type="submit"]') {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector(submitButtonSelector);
            if (submitButton) {
                submitButton.disabled = true;
                const originalText = submitButton.textContent;
                submitButton.textContent = submitButton.textContent.includes('Log') ? 'Logging...' : 'Processing...';
                
                // Re-enable after a delay in case of validation errors
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }, 3000);
            }
        });
    },

    /**
     * Setup input validation for numeric fields
     * @param {string} selector - CSS selector for inputs to validate
     */
    setupInputValidation: function(selector = 'input[type="number"]') {
        document.querySelectorAll(selector).forEach(input => {
            // Validate on input
            input.addEventListener('input', function() {
                this.classList.remove('input-error');
                MobileEntry.Messages.hideValidationError();
                
                const value = parseFloat(this.value);
                if (this.value && (isNaN(value) || value < 0)) {
                    this.classList.add('input-error');
                    MobileEntry.Messages.showValidationError('Please enter a positive number.');
                }
            });
            
            // Ensure minimum value on blur
            input.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (this.value && (isNaN(value) || value <= 0)) {
                    this.value = this.dataset.defaultValue || '1';
                    this.classList.remove('input-error');
                    MobileEntry.Messages.hideValidationError();
                }
            });
        });
    }
};

/**
 * Navigation Utilities
 * Handles add-item buttons, container management, and navigation flows
 */
MobileEntry.Navigation = {
    /**
     * Setup add-item buttons with container management
     * @param {Object} config - Configuration object with button and container mappings
     */
    setupAddItemButtons: function(config = {}) {
        // Default configuration for common patterns
        const defaultConfig = {
            buttons: [
                { id: 'add-food-button', container: 'food-list-container' },
                { id: 'add-exercise-button', container: 'exercise-list-container' },
                { id: 'add-exercise-button-bottom', container: 'exercise-list-container-bottom' }
            ]
        };
        
        const finalConfig = { ...defaultConfig, ...config };
        
        finalConfig.buttons.forEach(buttonConfig => {
            const button = document.getElementById(buttonConfig.id);
            if (button) {
                button.addEventListener('click', function() {
                    // Hide all other containers and show their buttons
                    MobileEntry.Navigation.hideAllContainers();
                    
                    // Show target container and hide this button
                    const container = document.getElementById(buttonConfig.container);
                    if (container) {
                        container.classList.remove('hidden');
                        this.style.display = 'none';
                    }
                });
            }
        });
    },

    /**
     * Hide all item list containers and show all buttons
     * @param {Array} containerIds - Array of container IDs to hide (optional)
     * @param {Array} buttonIds - Array of button IDs to show (optional)
     */
    hideAllContainers: function(containerIds = null, buttonIds = null) {
        // Default container IDs
        const defaultContainers = [
            'food-list-container',
            'exercise-list-container', 
            'exercise-list-container-bottom',
            'logging-form-container',
            'new-exercise-form-container',
            'new-exercise-form-container-bottom'
        ];
        
        // Default button IDs
        const defaultButtons = [
            'add-food-button',
            'add-exercise-button', 
            'add-exercise-button-bottom'
        ];
        
        const containers = containerIds || defaultContainers;
        const buttons = buttonIds || defaultButtons;
        
        // Hide containers
        containers.forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) {
                container.classList.add('hidden');
            }
        });
        
        // Show buttons
        buttons.forEach(buttonId => {
            const button = document.getElementById(buttonId);
            if (button) {
                button.style.display = '';
            }
        });
        
        // Show new exercise links
        const links = ['new-exercise-link', 'new-exercise-link-bottom'];
        links.forEach(linkId => {
            const link = document.getElementById(linkId);
            if (link) {
                link.style.display = '';
            }
        });
    },

    /**
     * Show a specific container and hide others
     * @param {string} containerId - ID of container to show
     */
    showContainer: function(containerId) {
        this.hideAllContainers();
        const container = document.getElementById(containerId);
        if (container) {
            container.classList.remove('hidden');
        }
    },

    /**
     * Setup new exercise link functionality
     * @param {string} linkId - ID of the link element
     * @param {string} formId - ID of the form to show
     * @param {string} inputId - ID of the input to focus
     */
    setupNewExerciseLink: function(linkId, formId, inputId) {
        const link = document.getElementById(linkId);
        if (link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                const form = document.getElementById(formId);
                const input = document.getElementById(inputId);
                
                if (form) {
                    form.classList.remove('hidden');
                }
                if (input) {
                    input.focus();
                }
                
                this.style.display = 'none';
                
                // Scroll to form
                setTimeout(() => {
                    if (form) {
                        form.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                }, 100);
            });
        }
    }
};

/**
 * Initialize shared functionality when DOM is ready
 */
MobileEntry.init = function() {
    // Auto-hide messages
    MobileEntry.Messages.autoHide();
    
    // Setup increment buttons
    MobileEntry.Forms.setupIncrementButtons();
    
    // Setup input validation
    MobileEntry.Forms.setupInputValidation();
    
    // Setup add item buttons
    MobileEntry.Navigation.setupAddItemButtons();
    
    // Setup new exercise links
    MobileEntry.Navigation.setupNewExerciseLink('new-exercise-link', 'new-exercise-form-container', 'exercise_name');
    MobileEntry.Navigation.setupNewExerciseLink('new-exercise-link-bottom', 'new-exercise-form-container-bottom', 'exercise_name_bottom');
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', MobileEntry.init);
} else {
    MobileEntry.init();
}

/**
 * Event Handlers Module
 * Provides reusable event handlers with configurable behavior
 * Requirements: 5.3, 5.4, 9.1
 */
MobileEntry.EventHandlers = {
    /**
     * Create increment/decrement button handler with configurable step values
     * @param {Object} config - Configuration object
     * @returns {Function} Event handler function
     */
    createIncrementHandler: function(config = {}) {
        const defaultConfig = {
            stepValues: {
                weight: 5,
                quantity: 1,
                portion: 0.25,
                reps: 1,
                rounds: 1
            },
            minValue: 0,
            maxDecimals: 2,
            preventNegative: true
        };
        
        const finalConfig = { ...defaultConfig, ...config };
        
        return function(event) {
            const target = this.dataset.target || this.dataset.field;
            const input = document.getElementById(target);
            
            if (!input) return;
            
            const isIncrement = this.classList.contains('increment-button');
            let currentValue = parseFloat(input.value) || 0;
            
            // Determine step value based on field type
            let stepValue = 1;
            for (const [fieldType, step] of Object.entries(finalConfig.stepValues)) {
                if (target.includes(fieldType)) {
                    stepValue = step;
                    break;
                }
            }
            
            // Apply increment/decrement
            if (isIncrement) {
                currentValue += stepValue;
            } else {
                currentValue -= stepValue;
                if (finalConfig.preventNegative) {
                    currentValue = Math.max(finalConfig.minValue, currentValue);
                }
            }
            
            // Round to specified decimal places
            input.value = Math.round(currentValue * Math.pow(10, finalConfig.maxDecimals)) / Math.pow(10, finalConfig.maxDecimals);
            
            // Clear validation errors
            input.classList.remove('input-error');
            MobileEntry.Messages.hideValidationError();
            
            // Trigger input event for any listeners
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
    },

    /**
     * Create form validation handler with extensible rule system
     * @param {Object} validationRules - Custom validation rules
     * @returns {Function} Event handler function
     */
    createValidationHandler: function(validationRules = {}) {
        return function(event) {
            const form = event.target;
            const formData = new FormData(form);
            
            // Clear previous validation errors
            MobileEntry.Messages.hideValidationError();
            
            // Validate form
            const validationResult = MobileEntry.Forms.validateForm(formData, validationRules);
            
            if (!validationResult.isValid) {
                event.preventDefault();
                MobileEntry.Messages.showValidationError(validationResult.message);
                return false;
            }
            
            // Prevent double submission
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                const originalText = submitButton.textContent;
                submitButton.textContent = submitButton.textContent.includes('Log') ? 'Logging...' : 'Processing...';
            }
            
            return true;
        };
    },

    /**
     * Create item selection handler for food/exercise lists
     * @param {Object} config - Configuration object
     * @returns {Function} Event handler function
     */
    createItemSelectionHandler: function(config = {}) {
        const defaultConfig = {
            hideContainer: true,
            showFormContainer: 'logging-form-container',
            scrollToForm: true,
            clearValidationErrors: true
        };
        
        const finalConfig = { ...defaultConfig, ...config };
        
        return function(event) {
            event.preventDefault();
            
            const type = this.dataset.type;
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            // Validate selection data
            if (!id || !name) {
                MobileEntry.Messages.showValidationError('Selected item is no longer available. Please refresh the page.');
                return;
            }
            
            // Clear validation errors
            if (finalConfig.clearValidationErrors) {
                MobileEntry.Messages.hideValidationError();
            }
            
            // Hide current container
            if (finalConfig.hideContainer) {
                const container = this.closest('.item-list-container');
                if (container) {
                    container.classList.add('hidden');
                }
            }
            
            // Show form container
            if (finalConfig.showFormContainer) {
                const formContainer = document.getElementById(finalConfig.showFormContainer);
                if (formContainer) {
                    formContainer.classList.remove('hidden');
                    
                    // Scroll to form
                    if (finalConfig.scrollToForm) {
                        setTimeout(() => {
                            formContainer.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'start' 
                            });
                        }, 100);
                    }
                }
            }
            
            // Set form values
            this.setFormValues(type, id, name);
        };
    },

    /**
     * Create cancel handler for forms
     * @param {Object} config - Configuration object
     * @returns {Function} Event handler function
     */
    createCancelHandler: function(config = {}) {
        const defaultConfig = {
            hideFormContainer: true,
            showAddButton: true,
            resetForm: true,
            hideFieldGroups: []
        };
        
        const finalConfig = { ...defaultConfig, ...config };
        
        return function(event) {
            event.preventDefault();
            
            // Hide form container
            if (finalConfig.hideFormContainer) {
                const formContainer = this.closest('.item-list-container') || 
                                   document.getElementById('logging-form-container');
                if (formContainer) {
                    formContainer.classList.add('hidden');
                }
            }
            
            // Show add button
            if (finalConfig.showAddButton) {
                const addButtons = ['add-food-button', 'add-exercise-button'];
                addButtons.forEach(buttonId => {
                    const button = document.getElementById(buttonId);
                    if (button) {
                        button.style.display = 'block';
                    }
                });
            }
            
            // Reset form
            if (finalConfig.resetForm) {
                const form = this.closest('form');
                if (form) {
                    form.reset();
                }
            }
            
            // Hide specific field groups
            finalConfig.hideFieldGroups.forEach(groupId => {
                const group = document.getElementById(groupId);
                if (group) {
                    group.classList.add('hidden');
                }
            });
            
            // Clear validation errors
            MobileEntry.Messages.hideValidationError();
        };
    },

    /**
     * Create band color selection handler
     * @param {Object} config - Configuration object
     * @returns {Function} Event handler function
     */
    createBandColorHandler: function(config = {}) {
        return function(event) {
            const programId = this.dataset.programId;
            const selectedColor = this.dataset.color;
            const hiddenInput = document.getElementById('band_color_' + programId);
            
            if (hiddenInput) {
                hiddenInput.value = selectedColor;
            }
            
            // Remove 'selected' class from all buttons for this program
            const selector = `#band-color-selector-${programId} .band-color-button`;
            document.querySelectorAll(selector).forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Add 'selected' class to clicked button
            this.classList.add('selected');
            
            // Clear validation errors
            MobileEntry.Messages.hideValidationError();
        };
    },

    /**
     * Create toggle handler for bodyweight exercise weight fields
     * @param {Object} config - Configuration object
     * @returns {Function} Event handler function
     */
    createToggleWeightHandler: function(config = {}) {
        return function(event) {
            const programId = this.dataset.programId;
            const weightField = document.getElementById('weight-form-group-' + programId);
            const weightInput = document.getElementById('weight_' + programId);
            
            if (weightField) {
                weightField.classList.toggle('hidden');
                
                if (weightInput) {
                    weightInput.required = !weightField.classList.contains('hidden');
                }
            }
            
            // Hide the toggle button container
            const buttonContainer = this.parentElement;
            if (buttonContainer) {
                buttonContainer.style.display = 'none';
            }
        };
    }
};

/**
 * Validation Rules Module
 * Provides extensible validation rule sets for different form types
 */
MobileEntry.ValidationRules = {
    /**
     * Food logging validation rules
     */
    foodLogging: {
        required: ['selected_type', 'selected_id'],
        positiveNumbers: ['quantity', 'portion'],
        maxValues: {
            quantity: 10000,
            portion: 100
        },
        customValidators: {
            ingredient: function(data) {
                if (data.selected_type === 'ingredient' && (!data.quantity || parseFloat(data.quantity) <= 0)) {
                    return { isValid: false, message: 'Please enter a quantity for the ingredient.' };
                }
                return { isValid: true };
            },
            meal: function(data) {
                if (data.selected_type === 'meal' && (!data.portion || parseFloat(data.portion) <= 0)) {
                    return { isValid: false, message: 'Please enter a portion size for the meal.' };
                }
                return { isValid: true };
            }
        }
    },

    /**
     * Lift logging validation rules
     */
    liftLogging: {
        required: ['exercise_id'],
        positiveNumbers: ['weight', 'reps', 'rounds'],
        maxValues: {
            weight: 5000,
            reps: 1000,
            rounds: 100
        },
        customValidators: {
            bandColor: function(data) {
                // Band color validation if needed
                return { isValid: true };
            }
        }
    },

    /**
     * Exercise creation validation rules
     */
    exerciseCreation: {
        required: ['exercise_name'],
        maxLengths: {
            exercise_name: 255
        }
    }
};

/**
 * Enhanced form validation with custom validators
 * @param {FormData|Object} formData - Form data to validate
 * @param {Object} rules - Validation rules with custom validators
 * @returns {Object} Validation result
 */
MobileEntry.Forms.validateFormExtended = function(formData, rules = {}) {
    // Run basic validation first
    const basicResult = this.validateForm(formData, rules);
    if (!basicResult.isValid) {
        return basicResult;
    }
    
    // Run custom validators if present
    if (rules.customValidators) {
        const data = formData instanceof FormData ? Object.fromEntries(formData) : formData;
        
        for (const [validatorName, validator] of Object.entries(rules.customValidators)) {
            const result = validator(data);
            if (!result.isValid) {
                return result;
            }
        }
    }
    
    return { isValid: true };
};/**
 
* Performance Optimization Module
 * Implements event delegation, memory management, and performance optimizations
 * Requirements: 5.5, 7.2, 9.1
 */
MobileEntry.Performance = {
    /**
     * Registered event listeners for cleanup
     */
    registeredListeners: new Map(),

    /**
     * Setup event delegation for dynamic content handling
     * This reduces memory usage and handles dynamically added elements
     */
    setupEventDelegation: function() {
        const container = document.body;
        
        // Delegate increment/decrement button clicks
        this.addDelegatedListener(container, 'click', '.increment-button, .decrement-button', 
            MobileEntry.EventHandlers.createIncrementHandler());
        
        // Delegate item selection clicks
        this.addDelegatedListener(container, 'click', '.food-list-item, .exercise-list-item',
            MobileEntry.EventHandlers.createItemSelectionHandler());
        
        // Delegate cancel button clicks
        this.addDelegatedListener(container, 'click', '#cancel-logging, .cancel-button',
            MobileEntry.EventHandlers.createCancelHandler());
        
        // Delegate band color button clicks
        this.addDelegatedListener(container, 'click', '.band-color-button',
            MobileEntry.EventHandlers.createBandColorHandler());
        
        // Delegate toggle weight button clicks
        this.addDelegatedListener(container, 'click', '.toggle-weight-field',
            MobileEntry.EventHandlers.createToggleWeightHandler());
        
        // Delegate message close button clicks
        this.addDelegatedListener(container, 'click', '.message-close', function(event) {
            const messageContainer = this.closest('.message-container');
            if (messageContainer) {
                messageContainer.style.display = 'none';
                messageContainer.classList.add('hidden');
            }
        });
        
        // Delegate form submissions with validation
        this.addDelegatedListener(container, 'submit', '.lift-log-form', 
            MobileEntry.EventHandlers.createValidationHandler(MobileEntry.ValidationRules.liftLogging));
        
        this.addDelegatedListener(container, 'submit', '#food-logging-form',
            MobileEntry.EventHandlers.createValidationHandler(MobileEntry.ValidationRules.foodLogging));
    },

    /**
     * Add delegated event listener with cleanup tracking
     * @param {Element} container - Container element for delegation
     * @param {string} eventType - Event type (click, submit, etc.)
     * @param {string} selector - CSS selector for target elements
     * @param {Function} handler - Event handler function
     */
    addDelegatedListener: function(container, eventType, selector, handler) {
        const delegatedHandler = function(event) {
            const target = event.target.closest(selector);
            if (target) {
                handler.call(target, event);
            }
        };
        
        container.addEventListener(eventType, delegatedHandler);
        
        // Track for cleanup
        const listenerId = `${eventType}-${selector}-${Date.now()}`;
        this.registeredListeners.set(listenerId, {
            container,
            eventType,
            handler: delegatedHandler
        });
        
        return listenerId;
    },

    /**
     * Remove duplicate event handler registrations
     * Scans for and removes duplicate listeners that may have been added multiple times
     */
    removeDuplicateHandlers: function() {
        // Remove inline onclick handlers that duplicate delegated functionality
        const inlineHandlers = [
            'onclick="this.parentElement.parentElement.style.display=\'none\'"',
            'onclick="document.getElementById(\'validation-errors\').classList.add(\'hidden\')"'
        ];
        
        document.querySelectorAll('[onclick]').forEach(element => {
            const onclickValue = element.getAttribute('onclick');
            if (inlineHandlers.some(handler => onclickValue.includes(handler))) {
                element.removeAttribute('onclick');
            }
        });
        
        // Remove duplicate addEventListener calls by checking for existing listeners
        // This is handled by using event delegation instead of multiple direct listeners
    },

    /**
     * Optimize memory usage by cleaning up unused event listeners
     */
    optimizeMemoryUsage: function() {
        // Clean up listeners on elements that no longer exist
        const elementsToCheck = document.querySelectorAll('[data-listener-id]');
        elementsToCheck.forEach(element => {
            if (!document.contains(element)) {
                const listenerId = element.dataset.listenerId;
                this.removeListener(listenerId);
            }
        });
        
        // Throttle resize and scroll events if any are added in the future
        this.throttleEvents();
    },

    /**
     * Remove a specific event listener
     * @param {string} listenerId - ID of the listener to remove
     */
    removeListener: function(listenerId) {
        const listener = this.registeredListeners.get(listenerId);
        if (listener) {
            listener.container.removeEventListener(listener.eventType, listener.handler);
            this.registeredListeners.delete(listenerId);
        }
    },

    /**
     * Throttle high-frequency events for better performance
     */
    throttleEvents: function() {
        let resizeTimeout;
        let scrollTimeout;
        
        // Throttled resize handler
        const throttledResize = function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                // Handle resize events if needed
                window.dispatchEvent(new Event('throttledResize'));
            }, 250);
        };
        
        // Throttled scroll handler
        const throttledScroll = function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                // Handle scroll events if needed
                window.dispatchEvent(new Event('throttledScroll'));
            }, 100);
        };
        
        // Only add if not already added
        if (!window.mobileEntryResizeAdded) {
            window.addEventListener('resize', throttledResize);
            window.mobileEntryResizeAdded = true;
        }
        
        if (!window.mobileEntryScrollAdded) {
            window.addEventListener('scroll', throttledScroll);
            window.mobileEntryScrollAdded = true;
        }
    },

    /**
     * Cleanup all registered event listeners
     * Call this when the page is being unloaded or when cleaning up
     */
    cleanup: function() {
        this.registeredListeners.forEach((listener, listenerId) => {
            this.removeListener(listenerId);
        });
        this.registeredListeners.clear();
    },

    /**
     * Debounce function for input validation
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring: function() {
        // Monitor DOM mutations for performance impact
        if (window.MutationObserver) {
            const observer = new MutationObserver(this.debounce((mutations) => {
                // Log excessive DOM mutations in development
                if (mutations.length > 50 && console && console.warn) {
                    console.warn('High DOM mutation count detected:', mutations.length);
                }
            }, 1000));
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: false
            });
        }
        
        // Monitor memory usage if available
        if (performance && performance.memory) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > memory.jsHeapSizeLimit * 0.9) {
                    console.warn('High memory usage detected');
                    this.optimizeMemoryUsage();
                }
            }, 30000); // Check every 30 seconds
        }
    }
};

/**
 * Enhanced initialization with performance optimizations
 */
MobileEntry.initOptimized = function() {
    // Remove duplicate handlers first
    MobileEntry.Performance.removeDuplicateHandlers();
    
    // Setup event delegation instead of individual listeners
    MobileEntry.Performance.setupEventDelegation();
    
    // Auto-hide messages
    MobileEntry.Messages.autoHide();
    
    // Setup performance monitoring
    MobileEntry.Performance.setupPerformanceMonitoring();
    
    // Optimize memory usage
    MobileEntry.Performance.optimizeMemoryUsage();
    
    // Setup cleanup on page unload
    window.addEventListener('beforeunload', () => {
        MobileEntry.Performance.cleanup();
    });
    
    // Setup visibility change handler for re-enabling forms
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            // Re-enable submit buttons if page becomes visible again
            document.querySelectorAll('button[type="submit"][disabled]').forEach(button => {
                button.disabled = false;
                if (button.textContent.includes('...')) {
                    button.textContent = button.textContent.includes('Log') ? 'Log Food' : 'Complete this lift';
                }
            });
        }
    });
};

// Override the original init function with the optimized version
MobileEntry.init = MobileEntry.initOptimized;

/**
 * Backward compatibility functions
 * These maintain compatibility with existing inline JavaScript
 */
window.showValidationError = function(message) {
    MobileEntry.Messages.showValidationError(message);
};

window.hideValidationError = function() {
    MobileEntry.Messages.hideValidationError();
};

window.validateForm = function(type) {
    const rules = type === 'ingredient' || type === 'meal' ? 
        MobileEntry.ValidationRules.foodLogging : 
        MobileEntry.ValidationRules.liftLogging;
    
    const form = document.getElementById('food-logging-form') || 
                 document.querySelector('.lift-log-form');
    
    if (form) {
        const formData = new FormData(form);
        return MobileEntry.Forms.validateFormExtended(formData, rules);
    }
    
    return { isValid: false, message: 'Form not found' };
};

window.validateLiftForm = function(formData) {
    return MobileEntry.Forms.validateFormExtended(formData, MobileEntry.ValidationRules.liftLogging);
};

window.hideAllExerciseLists = function() {
    MobileEntry.Navigation.hideAllContainers();
};

window.setupAddExerciseButton = function(buttonId, containerId) {
    MobileEntry.Navigation.setupAddItemButtons({
        buttons: [{ id: buttonId, container: containerId }]
    });
};

window.setupNewExerciseLink = function(linkId, formId, inputId) {
    MobileEntry.Navigation.setupNewExerciseLink(linkId, formId, inputId);
};