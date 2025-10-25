/**
 * Add Item Button Component
 * Handles add item button interactions and form submissions
 * Requirements: 4.3, 4.5, 7.1
 */

export class AddItemButton {
  constructor() {
    this.buttons = new Set();
    this.loadingStates = new Map();
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupExistingButtons();
  }

  bindEvents() {
    // Handle button clicks
    document.addEventListener('click', (event) => {
      if (event.target.matches('.add-item-button, .button-large, .submit-button')) {
        this.handleButtonClick(event.target, event);
      } else if (event.target.matches('.toggle-button')) {
        this.handleToggleClick(event.target, event);
      }
    });

    // Handle form submissions
    document.addEventListener('submit', (event) => {
      const submitButton = event.target.querySelector('[type="submit"]');
      if (submitButton) {
        this.handleFormSubmit(submitButton, event);
      }
    });

    // Handle keyboard interactions
    document.addEventListener('keydown', (event) => {
      if (event.target.matches('.add-item-button, .button-large, .submit-button, .toggle-button')) {
        this.handleButtonKeyDown(event);
      }
    });
  }

  setupExistingButtons() {
    document.querySelectorAll('.add-item-button, .button-large, .submit-button, .toggle-button').forEach(button => {
      this.setupButton(button);
    });
  }

  setupButton(button) {
    // Ensure proper ARIA attributes
    if (!button.hasAttribute('role') && button.tagName !== 'BUTTON') {
      button.setAttribute('role', 'button');
    }

    // Make focusable if not already
    if (!button.hasAttribute('tabindex') && button.tagName !== 'BUTTON') {
      button.setAttribute('tabindex', '0');
    }

    // Setup toggle button state
    if (button.classList.contains('toggle-button')) {
      const isActive = button.classList.contains('active');
      button.setAttribute('aria-pressed', isActive.toString());
    }

    // Setup loading state tracking
    this.loadingStates.set(button, false);
    this.buttons.add(button);
  }

  handleButtonClick(button, event) {
    // Prevent double-clicks during loading
    if (this.loadingStates.get(button)) {
      event.preventDefault();
      return;
    }

    // Add click animation
    this.addClickAnimation(button);

    // Handle different button types
    if (button.classList.contains('add-item-button')) {
      this.handleAddItemClick(button, event);
    } else if (button.classList.contains('submit-button')) {
      this.handleSubmitClick(button, event);
    } else if (button.classList.contains('button-large')) {
      this.handleLargeButtonClick(button, event);
    }
  }

  handleToggleClick(button, event) {
    event.preventDefault();
    
    const wasActive = button.classList.contains('active');
    button.classList.toggle('active');
    button.setAttribute('aria-pressed', (!wasActive).toString());

    // Add click animation
    this.addClickAnimation(button);

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('toggleChanged', {
      detail: { button, active: !wasActive }
    }));

    // Announce state change to screen readers
    const state = !wasActive ? 'activated' : 'deactivated';
    this.announceStateChange(button, state);
  }

  handleButtonKeyDown(event) {
    const button = event.target;

    switch (event.key) {
      case 'Enter':
      case ' ':
        event.preventDefault();
        button.click();
        break;
    }
  }

  handleAddItemClick(button, event) {
    // Show loading state
    this.setLoadingState(button, true);

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('addItemClicked', {
      detail: { button, event }
    }));
  }

  handleSubmitClick(button, event) {
    // Form validation will be handled by the form submit event
    // Just show loading state here
    this.setLoadingState(button, true);
  }

  handleLargeButtonClick(button, event) {
    // Add visual feedback
    this.addClickAnimation(button);

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('largeButtonClicked', {
      detail: { button, event }
    }));
  }

  handleFormSubmit(submitButton, event) {
    // Validate form
    const form = event.target;
    if (!this.validateForm(form)) {
      event.preventDefault();
      this.setLoadingState(submitButton, false);
      return;
    }

    // Show loading state
    this.setLoadingState(submitButton, true);

    // Set timeout to reset loading state if form doesn't redirect
    setTimeout(() => {
      this.setLoadingState(submitButton, false);
    }, 10000); // 10 second timeout
  }

  setLoadingState(button, isLoading) {
    this.loadingStates.set(button, isLoading);

    if (isLoading) {
      button.disabled = true;
      button.classList.add('loading');
      
      // Store original text
      if (!button.dataset.originalText) {
        button.dataset.originalText = button.textContent;
      }
      
      // Show loading text
      button.textContent = 'Loading...';
      
      // Add loading spinner if needed
      this.addLoadingSpinner(button);
    } else {
      button.disabled = false;
      button.classList.remove('loading');
      
      // Restore original text
      if (button.dataset.originalText) {
        button.textContent = button.dataset.originalText;
      }
      
      // Remove loading spinner
      this.removeLoadingSpinner(button);
    }
  }

  addLoadingSpinner(button) {
    if (button.querySelector('.loading-spinner')) return;

    const spinner = document.createElement('span');
    spinner.className = 'loading-spinner';
    button.appendChild(spinner);
  }

  removeLoadingSpinner(button) {
    const spinner = button.querySelector('.loading-spinner');
    if (spinner) {
      spinner.remove();
    }
  }

  validateForm(form) {
    let isValid = true;
    const errors = [];

    // Check required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
      if (!field.value.trim()) {
        isValid = false;
        const label = field.labels[0]?.textContent || field.name || 'Field';
        errors.push(`${label} is required`);
        field.classList.add('input-error');
      } else {
        field.classList.remove('input-error');
      }
    });

    // Check number field ranges
    const numberFields = form.querySelectorAll('input[type="number"]');
    numberFields.forEach(field => {
      const value = parseFloat(field.value);
      const min = parseFloat(field.min);
      const max = parseFloat(field.max);

      if (!isNaN(value)) {
        if (!isNaN(min) && value < min) {
          isValid = false;
          errors.push(`${field.labels[0]?.textContent || field.name} must be at least ${min}`);
          field.classList.add('input-error');
        } else if (!isNaN(max) && value > max) {
          isValid = false;
          errors.push(`${field.labels[0]?.textContent || field.name} must be no more than ${max}`);
          field.classList.add('input-error');
        } else {
          field.classList.remove('input-error');
        }
      }
    });

    // Show validation errors
    if (!isValid && window.entryInterface) {
      const messageSystem = window.entryInterface.getComponent('messageSystem');
      if (messageSystem) {
        messageSystem.showValidation(errors.join(', '));
      }
    }

    return isValid;
  }

  addClickAnimation(button) {
    button.classList.add('clicked');
    setTimeout(() => button.classList.remove('clicked'), 150);
  }

  announceStateChange(button, state) {
    const buttonText = button.textContent || button.getAttribute('aria-label') || 'Button';
    const announcement = `${buttonText} ${state}`;
    
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = announcement;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  resetLoadingStates() {
    this.buttons.forEach(button => {
      this.setLoadingState(button, false);
    });
  }

  getButtonState(button) {
    return {
      loading: this.loadingStates.get(button) || false,
      active: button.classList.contains('active'),
      disabled: button.disabled
    };
  }

  handleClick(event) {
    // Handle clicks with proper delegation
    if (event.target.matches('.add-item-button, .button-large, .submit-button')) {
      this.handleButtonClick(event.target, event);
    } else if (event.target.matches('.toggle-button')) {
      this.handleToggleClick(event.target, event);
    }
  }

  handleResize() {
    // Adjust button layout for different screen sizes
    this.buttons.forEach(button => {
      if (window.innerWidth <= 480) {
        button.classList.add('compact');
      } else {
        button.classList.remove('compact');
      }
    });
  }

  destroy() {
    this.resetLoadingStates();
    this.buttons.clear();
    this.loadingStates.clear();
  }
}