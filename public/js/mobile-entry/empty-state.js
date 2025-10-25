/**
 * Empty State Component
 * Handles empty state display and loading states
 * Requirements: 4.3, 4.5, 7.1
 */

export class EmptyState {
  constructor() {
    this.states = new Map();
    this.init();
  }

  init() {
    this.setupExistingStates();
    this.bindEvents();
  }

  bindEvents() {
    // Listen for content changes
    document.addEventListener('contentLoaded', (event) => {
      this.handleContentLoaded(event.detail);
    });

    document.addEventListener('contentEmpty', (event) => {
      this.showEmptyState(event.detail);
    });

    document.addEventListener('loadingStarted', (event) => {
      this.showLoadingState(event.detail);
    });
  }

  setupExistingStates() {
    // Setup existing empty states
    document.querySelectorAll('.empty-state, .loading-state, .error-state, .success-state').forEach(state => {
      this.setupState(state);
    });

    // Setup item list empty states
    document.querySelectorAll('.item-list-empty, .item-list-loading').forEach(state => {
      this.setupState(state);
    });
  }

  setupState(element) {
    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    const config = {
      element,
      type: this.getStateType(element),
      container: element.closest('.item-list-container, .mobile-entry-container'),
      originalContent: element.innerHTML
    };

    this.states.set(id, config);

    // Setup ARIA attributes
    this.setupAccessibility(element, config.type);
  }

  setupAccessibility(element, type) {
    // Add appropriate ARIA attributes based on state type
    switch (type) {
      case 'empty':
        element.setAttribute('role', 'status');
        element.setAttribute('aria-label', 'No content available');
        break;
      case 'loading':
        element.setAttribute('role', 'status');
        element.setAttribute('aria-label', 'Loading content');
        element.setAttribute('aria-live', 'polite');
        break;
      case 'error':
        element.setAttribute('role', 'alert');
        element.setAttribute('aria-label', 'Error occurred');
        break;
      case 'success':
        element.setAttribute('role', 'status');
        element.setAttribute('aria-label', 'Success message');
        break;
    }
  }

  getStateType(element) {
    if (element.classList.contains('loading-state') || element.classList.contains('item-list-loading')) {
      return 'loading';
    } else if (element.classList.contains('error-state')) {
      return 'error';
    } else if (element.classList.contains('success-state')) {
      return 'success';
    } else {
      return 'empty';
    }
  }

  showEmptyState(options = {}) {
    const {
      container = '.mobile-entry-container',
      type = 'no-items',
      title = 'No items found',
      message = 'There are no items to display.',
      icon = '📝',
      actionButton = null
    } = options;

    const containerElement = typeof container === 'string' 
      ? document.querySelector(container) 
      : container;

    if (!containerElement) return null;

    // Remove existing content
    this.clearContainer(containerElement);

    // Create empty state element
    const emptyState = this.createEmptyStateElement({
      type,
      title,
      message,
      icon,
      actionButton
    });

    containerElement.appendChild(emptyState);
    this.setupState(emptyState);

    return emptyState.id;
  }

  showLoadingState(options = {}) {
    const {
      container = '.mobile-entry-container',
      message = 'Loading...',
      showSpinner = true
    } = options;

    const containerElement = typeof container === 'string' 
      ? document.querySelector(container) 
      : container;

    if (!containerElement) return null;

    // Remove existing content
    this.clearContainer(containerElement);

    // Create loading state element
    const loadingState = this.createLoadingStateElement({
      message,
      showSpinner
    });

    containerElement.appendChild(loadingState);
    this.setupState(loadingState);

    return loadingState.id;
  }

  showErrorState(options = {}) {
    const {
      container = '.mobile-entry-container',
      title = 'Error',
      message = 'Something went wrong. Please try again.',
      actionButton = null
    } = options;

    const containerElement = typeof container === 'string' 
      ? document.querySelector(container) 
      : container;

    if (!containerElement) return null;

    // Remove existing content
    this.clearContainer(containerElement);

    // Create error state element
    const errorState = this.createErrorStateElement({
      title,
      message,
      actionButton
    });

    containerElement.appendChild(errorState);
    this.setupState(errorState);

    return errorState.id;
  }

  showSuccessState(options = {}) {
    const {
      container = '.mobile-entry-container',
      title = 'Success',
      message = 'Operation completed successfully.',
      autoHide = true,
      duration = 3000
    } = options;

    const containerElement = typeof container === 'string' 
      ? document.querySelector(container) 
      : container;

    if (!containerElement) return null;

    // Create success state element
    const successState = this.createSuccessStateElement({
      title,
      message
    });

    containerElement.insertBefore(successState, containerElement.firstChild);
    this.setupState(successState);

    if (autoHide) {
      setTimeout(() => {
        this.hideState(successState.id);
      }, duration);
    }

    return successState.id;
  }

  createEmptyStateElement(options) {
    const { type, title, message, icon, actionButton } = options;
    
    const element = document.createElement('div');
    element.className = `empty-state ${type}`;
    
    let html = '';
    
    if (icon) {
      html += `<div class="empty-state-icon">${icon}</div>`;
    }
    
    if (title) {
      html += `<h3 class="empty-state-title">${title}</h3>`;
    }
    
    if (message) {
      html += `<p class="empty-state-message">${message}</p>`;
    }
    
    if (actionButton) {
      html += `<div class="empty-state-action">${actionButton}</div>`;
    }
    
    element.innerHTML = html;
    return element;
  }

  createLoadingStateElement(options) {
    const { message, showSpinner } = options;
    
    const element = document.createElement('div');
    element.className = 'loading-state';
    
    let html = '';
    
    if (showSpinner) {
      html += '<div class="loading-spinner"></div>';
    }
    
    html += `<p class="loading-message">${message}</p>`;
    
    element.innerHTML = html;
    return element;
  }

  createErrorStateElement(options) {
    const { title, message, actionButton } = options;
    
    const element = document.createElement('div');
    element.className = 'error-state';
    
    let html = '<div class="error-state-icon">⚠️</div>';
    
    if (title) {
      html += `<h3 class="error-state-title">${title}</h3>`;
    }
    
    if (message) {
      html += `<p class="error-state-message">${message}</p>`;
    }
    
    if (actionButton) {
      html += `<div class="error-state-action">${actionButton}</div>`;
    }
    
    element.innerHTML = html;
    return element;
  }

  createSuccessStateElement(options) {
    const { title, message } = options;
    
    const element = document.createElement('div');
    element.className = 'success-state';
    
    let html = '<div class="success-state-icon">✅</div>';
    
    if (title) {
      html += `<h3 class="success-state-title">${title}</h3>`;
    }
    
    if (message) {
      html += `<p class="success-state-message">${message}</p>`;
    }
    
    element.innerHTML = html;
    return element;
  }

  clearContainer(container) {
    // Remove existing state elements
    const existingStates = container.querySelectorAll('.empty-state, .loading-state, .error-state, .success-state');
    existingStates.forEach(state => state.remove());
  }

  hideState(id) {
    const config = this.states.get(id);
    if (!config) return;

    // Add fade out animation
    config.element.classList.add('fade-out');
    
    // Remove after animation
    setTimeout(() => {
      if (config.element.parentNode) {
        config.element.parentNode.removeChild(config.element);
      }
      this.states.delete(id);
    }, 300);
  }

  updateState(id, options = {}) {
    const config = this.states.get(id);
    if (!config) return;

    const { title, message, icon } = options;

    if (title) {
      const titleElement = config.element.querySelector('.empty-state-title, .error-state-title, .success-state-title');
      if (titleElement) {
        titleElement.textContent = title;
      }
    }

    if (message) {
      const messageElement = config.element.querySelector('.empty-state-message, .error-state-message, .success-state-message, .loading-message');
      if (messageElement) {
        messageElement.textContent = message;
      }
    }

    if (icon) {
      const iconElement = config.element.querySelector('.empty-state-icon, .error-state-icon, .success-state-icon');
      if (iconElement) {
        iconElement.textContent = icon;
      }
    }
  }

  handleContentLoaded(data) {
    // Hide loading states when content is loaded
    this.states.forEach((config, id) => {
      if (config.type === 'loading') {
        this.hideState(id);
      }
    });

    // Show empty state if no content
    if (data && data.isEmpty) {
      this.showEmptyState(data.emptyStateOptions || {});
    }
  }

  generateId() {
    return 'state-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  getAllStates() {
    const result = {};
    this.states.forEach((config, id) => {
      result[id] = {
        type: config.type,
        visible: config.element.parentNode !== null
      };
    });
    return result;
  }

  handleResize() {
    // Adjust state layout for different screen sizes
    this.states.forEach((config) => {
      const element = config.element;
      
      if (window.innerWidth <= 480) {
        element.classList.add('compact');
      } else {
        element.classList.remove('compact');
      }
    });
  }

  destroy() {
    // Hide all states
    this.states.forEach((config, id) => {
      this.hideState(id);
    });
    
    this.states.clear();
  }
}