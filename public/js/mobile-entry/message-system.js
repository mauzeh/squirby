/**
 * Message System Component
 * Handles message display, auto-hide, and user interactions
 * Requirements: 4.3, 4.5, 7.1
 */

export class MessageSystem {
  constructor() {
    this.messages = new Map();
    this.autoHideTimeout = 5000; // 5 seconds
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupExistingMessages();
  }

  bindEvents() {
    // Handle message close buttons
    document.addEventListener('click', (event) => {
      if (event.target.matches('.message-close')) {
        this.closeMessage(event.target.closest('.message-container'));
      }
    });

    // Handle keyboard navigation
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.closeAllMessages();
      }
    });
  }

  setupExistingMessages() {
    // Setup existing messages in the DOM
    document.querySelectorAll('.message-container').forEach(container => {
      this.setupMessage(container);
    });
  }

  setupMessage(container) {
    const id = container.dataset.messageId || this.generateId();
    container.dataset.messageId = id;
    
    // Setup auto-hide if specified
    if (container.classList.contains('auto-hide')) {
      this.setupAutoHide(container);
    }

    // Make focusable for accessibility
    if (!container.hasAttribute('tabindex')) {
      container.setAttribute('tabindex', '-1');
    }

    this.messages.set(id, container);
  }

  showMessage(text, type = 'info', options = {}) {
    const {
      autoHide = true,
      duration = this.autoHideTimeout,
      closable = true
    } = options;

    const container = this.createMessageContainer(text, type, { closable });
    
    // Insert at the top of the mobile entry container
    const entryContainer = document.querySelector('.mobile-entry-container');
    if (entryContainer) {
      entryContainer.insertBefore(container, entryContainer.firstChild);
    } else {
      document.body.appendChild(container);
    }

    this.setupMessage(container);

    if (autoHide) {
      this.setupAutoHide(container, duration);
    }

    // Announce to screen readers
    this.announceMessage(text);

    return container.dataset.messageId;
  }

  showError(text, options = {}) {
    return this.showMessage(text, 'error', { autoHide: false, ...options });
  }

  showSuccess(text, options = {}) {
    return this.showMessage(text, 'success', options);
  }

  showValidation(text, options = {}) {
    return this.showMessage(text, 'validation', options);
  }

  showInfo(text, options = {}) {
    return this.showMessage(text, 'info', options);
  }

  createMessageContainer(text, type, options) {
    const container = document.createElement('div');
    container.className = `message-container message-${type}`;
    
    const content = document.createElement('div');
    content.className = 'message-content';
    
    const textElement = document.createElement('span');
    textElement.className = 'message-text';
    textElement.textContent = text;
    
    content.appendChild(textElement);
    
    if (options.closable) {
      const closeButton = document.createElement('button');
      closeButton.className = 'message-close';
      closeButton.innerHTML = '×';
      closeButton.setAttribute('aria-label', 'Close message');
      closeButton.type = 'button';
      content.appendChild(closeButton);
    }
    
    container.appendChild(content);
    return container;
  }

  setupAutoHide(container, duration = this.autoHideTimeout) {
    container.classList.add('auto-hide');
    container.style.setProperty('--auto-hide-duration', `${duration}ms`);
    
    setTimeout(() => {
      this.closeMessage(container);
    }, duration);
  }

  closeMessage(container) {
    if (!container) return;
    
    const id = container.dataset.messageId;
    
    // Add fade out animation
    container.classList.add('fade-out');
    
    // Remove after animation
    setTimeout(() => {
      if (container.parentNode) {
        container.parentNode.removeChild(container);
      }
      if (id) {
        this.messages.delete(id);
      }
    }, 300);
  }

  closeAllMessages() {
    this.messages.forEach(container => {
      this.closeMessage(container);
    });
  }

  closeMessageById(id) {
    const container = this.messages.get(id);
    if (container) {
      this.closeMessage(container);
    }
  }

  announceMessage(text) {
    // Announce to screen readers via live region
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = text;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  generateId() {
    return 'message-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  handleClick(event) {
    // Handle clicks on message containers
    if (event.target.matches('.message-close')) {
      this.closeMessage(event.target.closest('.message-container'));
    }
  }

  destroy() {
    this.closeAllMessages();
    this.messages.clear();
  }
}