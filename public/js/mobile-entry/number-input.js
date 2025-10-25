/**
 * Number Input Component
 * Handles number input with increment/decrement buttons and validation
 * Requirements: 4.3, 4.5, 7.1
 */

export class NumberInput {
  constructor() {
    this.inputs = new Map();
    this.debounceDelay = 300;
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupExistingInputs();
  }

  bindEvents() {
    // Handle increment/decrement button clicks
    document.addEventListener('click', (event) => {
      if (event.target.matches('.increment-button')) {
        this.handleIncrement(event.target);
      } else if (event.target.matches('.decrement-button')) {
        this.handleDecrement(event.target);
      }
    });

    // Handle input changes
    document.addEventListener('input', (event) => {
      if (event.target.matches('.large-input[type="number"]')) {
        this.handleInputChange(event.target);
      }
    });

    // Handle keyboard shortcuts
    document.addEventListener('keydown', (event) => {
      if (event.target.matches('.large-input[type="number"]')) {
        this.handleKeyDown(event);
      }
    });

    // Handle focus events
    document.addEventListener('focus', (event) => {
      if (event.target.matches('.large-input')) {
        this.handleInputFocus(event.target);
      }
    }, true);

    // Handle blur events for validation
    document.addEventListener('blur', (event) => {
      if (event.target.matches('.large-input')) {
        this.handleInputBlur(event.target);
      }
    }, true);
  }

  setupExistingInputs() {
    document.querySelectorAll('.input-group').forEach(group => {
      this.setupInputGroup(group);
    });
  }

  setupInputGroup(group) {
    const input = group.querySelector('.large-input');
    const incrementButton = group.querySelector('.increment-button');
    const decrementButton = group.querySelector('.decrement-button');
    
    if (!input) return;

    const config = {
      min: parseFloat(input.getAttribute('min')) || 0,
      max: parseFloat(input.getAttribute('max')) || Infinity,
      step: parseFloat(input.getAttribute('step')) || 1,
      decimals: this.getDecimalPlaces(input.getAttribute('step') || '1')
    };

    this.inputs.set(input, config);

    // Setup button states
    this.updateButtonStates(input, config);

    // Setup ARIA attributes
    if (incrementButton) {
      incrementButton.setAttribute('aria-label', `Increase ${input.labels[0]?.textContent || 'value'}`);
    }
    if (decrementButton) {
      decrementButton.setAttribute('aria-label', `Decrease ${input.labels[0]?.textContent || 'value'}`);
    }
  }

  handleIncrement(button) {
    const group = button.closest('.input-group');
    const input = group.querySelector('.large-input');
    if (!input) return;

    const config = this.inputs.get(input);
    if (!config) return;

    const currentValue = parseFloat(input.value) || 0;
    const newValue = Math.min(currentValue + config.step, config.max);
    
    this.setValue(input, newValue, config);
    this.triggerChange(input);
  }

  handleDecrement(button) {
    const group = button.closest('.input-group');
    const input = group.querySelector('.large-input');
    if (!input) return;

    const config = this.inputs.get(input);
    if (!config) return;

    const currentValue = parseFloat(input.value) || 0;
    const newValue = Math.max(currentValue - config.step, config.min);
    
    this.setValue(input, newValue, config);
    this.triggerChange(input);
  }

  handleInputChange(input) {
    const config = this.inputs.get(input);
    if (!config) return;

    // Debounce validation
    clearTimeout(input.validationTimeout);
    input.validationTimeout = setTimeout(() => {
      this.validateInput(input, config);
    }, this.debounceDelay);

    this.updateButtonStates(input, config);
  }

  handleKeyDown(event) {
    const input = event.target;
    const config = this.inputs.get(input);
    if (!config) return;

    switch (event.key) {
      case 'ArrowUp':
        event.preventDefault();
        this.handleIncrement(input.parentNode.querySelector('.increment-button'));
        break;
      case 'ArrowDown':
        event.preventDefault();
        this.handleDecrement(input.parentNode.querySelector('.decrement-button'));
        break;
      case 'Enter':
        event.preventDefault();
        input.blur(); // Trigger validation
        break;
    }
  }

  handleInputFocus(input) {
    // Select all text on focus for easy editing
    if (input.type === 'number' || input.classList.contains('large-input')) {
      setTimeout(() => input.select(), 0);
    }
  }

  handleInputBlur(input) {
    const config = this.inputs.get(input);
    if (config) {
      this.validateInput(input, config);
    }
  }

  setValue(input, value, config) {
    const formattedValue = this.formatValue(value, config.decimals);
    input.value = formattedValue;
    this.updateButtonStates(input, config);
  }

  validateInput(input, config) {
    const value = parseFloat(input.value);
    let isValid = true;
    let errorMessage = '';

    // Clear previous validation state
    input.classList.remove('input-error', 'input-success');
    this.clearFieldMessage(input);

    if (isNaN(value)) {
      if (input.hasAttribute('required') && input.value.trim() === '') {
        isValid = false;
        errorMessage = 'This field is required';
      }
    } else {
      if (value < config.min) {
        isValid = false;
        errorMessage = `Value must be at least ${config.min}`;
        this.setValue(input, config.min, config);
      } else if (value > config.max) {
        isValid = false;
        errorMessage = `Value must be no more than ${config.max}`;
        this.setValue(input, config.max, config);
      } else {
        // Value is valid, format it properly
        this.setValue(input, value, config);
      }
    }

    // Apply validation state
    if (isValid) {
      input.classList.add('input-success');
    } else {
      input.classList.add('input-error');
      this.showFieldMessage(input, errorMessage, 'error');
    }

    return isValid;
  }

  updateButtonStates(input, config) {
    const group = input.closest('.input-group');
    if (!group) return;

    const incrementButton = group.querySelector('.increment-button');
    const decrementButton = group.querySelector('.decrement-button');
    const currentValue = parseFloat(input.value) || 0;

    if (incrementButton) {
      incrementButton.disabled = currentValue >= config.max;
    }
    if (decrementButton) {
      decrementButton.disabled = currentValue <= config.min;
    }
  }

  showFieldMessage(input, message, type) {
    let messageElement = input.parentNode.querySelector('.field-message');
    
    if (!messageElement) {
      messageElement = document.createElement('span');
      messageElement.className = 'field-message';
      input.parentNode.appendChild(messageElement);
    }

    messageElement.className = `field-message field-${type}`;
    messageElement.textContent = message;
  }

  clearFieldMessage(input) {
    const messageElement = input.parentNode.querySelector('.field-message');
    if (messageElement) {
      messageElement.remove();
    }
  }

  triggerChange(input) {
    // Trigger change event for other components to listen to
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  formatValue(value, decimals) {
    if (decimals === 0) {
      return Math.round(value).toString();
    }
    return value.toFixed(decimals);
  }

  getDecimalPlaces(step) {
    const stepStr = step.toString();
    if (stepStr.indexOf('.') === -1) {
      return 0;
    }
    return stepStr.split('.')[1].length;
  }

  handleClick(event) {
    // Add visual feedback for button clicks
    if (event.target.matches('.increment-button, .decrement-button')) {
      event.target.classList.add('clicked');
      setTimeout(() => event.target.classList.remove('clicked'), 150);
    }
  }

  handleMobileStateChange(isMobile) {
    // Adjust input behavior for mobile
    document.querySelectorAll('.large-input').forEach(input => {
      if (isMobile) {
        // On mobile, prevent zoom on focus
        input.setAttribute('inputmode', 'decimal');
      } else {
        input.removeAttribute('inputmode');
      }
    });
  }

  destroy() {
    this.inputs.clear();
    
    // Clear any pending timeouts
    document.querySelectorAll('.large-input').forEach(input => {
      if (input.validationTimeout) {
        clearTimeout(input.validationTimeout);
      }
    });
  }
}