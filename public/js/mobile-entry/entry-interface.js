/**
 * Entry Interface JavaScript - Mobile-First Unified Architecture
 * Consolidated mobile-first JavaScript for all entry interfaces (lift-logs, food-logs)
 * Requirements: 4.1, 4.2, 7.1
 * 
 * Architecture Philosophy:
 * - Mobile-first responsive behavior
 * - Component-based organization (scripts grouped by UI component)
 * - Unified event handling system
 * - No device-type separation (unified responsive system)
 */

// Import component modules
import { MessageSystem } from './message-system.js';
import { DateNavigation } from './date-navigation.js';
import { NumberInput } from './number-input.js';
import { ItemCard } from './item-card.js';
import { AddItemButton } from './add-item-button.js';
import { PageTitle } from './page-title.js';
import { EmptyState } from './empty-state.js';
import { BaseLayout } from './layouts/base-layout.js';

/**
 * Main Entry Interface Controller
 */
class EntryInterface {
  constructor() {
    this.components = new Map();
    this.config = {
      touchTargetSize: 44,
      animationDuration: 200,
      debounceDelay: 300,
      isMobile: window.innerWidth <= 768
    };
    
    this.init();
  }

  /**
   * Initialize the entry interface
   */
  init() {
    this.setupComponents();
    this.bindEvents();
    this.setupResponsiveHandling();
    this.setupAccessibility();
  }

  /**
   * Setup all component instances
   */
  setupComponents() {
    // Initialize core components
    this.components.set('messageSystem', new MessageSystem());
    this.components.set('dateNavigation', new DateNavigation());
    this.components.set('numberInput', new NumberInput());
    this.components.set('itemCard', new ItemCard());
    this.components.set('addItemButton', new AddItemButton());
    this.components.set('pageTitle', new PageTitle());
    this.components.set('emptyState', new EmptyState());
    this.components.set('baseLayout', new BaseLayout());
  }

  /**
   * Bind global event listeners
   */
  bindEvents() {
    // Global touch and click handling
    document.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
    document.addEventListener('click', this.handleClick.bind(this));
    
    // Keyboard navigation
    document.addEventListener('keydown', this.handleKeyDown.bind(this));
    
    // Form submission handling
    document.addEventListener('submit', this.handleFormSubmit.bind(this));
    
    // Window resize handling
    window.addEventListener('resize', this.debounce(this.handleResize.bind(this), this.config.debounceDelay));
    
    // Orientation change handling
    window.addEventListener('orientationchange', this.handleOrientationChange.bind(this));
  }

  /**
   * Setup responsive behavior handling
   */
  setupResponsiveHandling() {
    // Update mobile state on resize
    this.updateMobileState();
    
    // Setup intersection observer for lazy loading
    if ('IntersectionObserver' in window) {
      this.setupIntersectionObserver();
    }
  }

  /**
   * Setup accessibility features
   */
  setupAccessibility() {
    // Focus management
    this.setupFocusManagement();
    
    // ARIA live regions
    this.setupAriaLiveRegions();
    
    // Reduced motion handling
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      document.documentElement.classList.add('reduced-motion');
    }
  }

  /**
   * Handle touch start events for mobile optimization
   */
  handleTouchStart(event) {
    // Add touch feedback class
    const target = event.target.closest('[data-touch-feedback]');
    if (target) {
      target.classList.add('touch-active');
      setTimeout(() => target.classList.remove('touch-active'), 150);
    }
  }

  /**
   * Handle click events with delegation
   */
  handleClick(event) {
    const target = event.target;
    
    // Handle component-specific clicks
    this.components.forEach(component => {
      if (component.handleClick) {
        component.handleClick(event);
      }
    });
  }

  /**
   * Handle keyboard navigation
   */
  handleKeyDown(event) {
    // Escape key handling
    if (event.key === 'Escape') {
      this.handleEscape(event);
    }
    
    // Enter key handling for buttons
    if (event.key === 'Enter' && event.target.matches('[role="button"]')) {
      event.target.click();
    }
    
    // Arrow key navigation for lists
    if (['ArrowUp', 'ArrowDown'].includes(event.key)) {
      this.handleArrowNavigation(event);
    }
  }

  /**
   * Handle form submissions
   */
  handleFormSubmit(event) {
    const form = event.target;
    
    // Validate form before submission
    if (!this.validateForm(form)) {
      event.preventDefault();
      return false;
    }
    
    // Show loading state
    this.showFormLoading(form);
  }

  /**
   * Handle window resize
   */
  handleResize() {
    this.updateMobileState();
    
    // Notify components of resize
    this.components.forEach(component => {
      if (component.handleResize) {
        component.handleResize();
      }
    });
  }

  /**
   * Handle orientation change
   */
  handleOrientationChange() {
    // Delay to allow for orientation change to complete
    setTimeout(() => {
      this.handleResize();
    }, 100);
  }

  /**
   * Update mobile state based on viewport
   */
  updateMobileState() {
    const wasMobile = this.config.isMobile;
    this.config.isMobile = window.innerWidth <= 768;
    
    if (wasMobile !== this.config.isMobile) {
      document.documentElement.classList.toggle('mobile', this.config.isMobile);
      
      // Notify components of mobile state change
      this.components.forEach(component => {
        if (component.handleMobileStateChange) {
          component.handleMobileStateChange(this.config.isMobile);
        }
      });
    }
  }

  /**
   * Setup intersection observer for performance
   */
  setupIntersectionObserver() {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-viewport');
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '50px'
    });

    // Observe lazy-loadable elements
    document.querySelectorAll('[data-lazy]').forEach(el => {
      observer.observe(el);
    });
  }

  /**
   * Setup focus management
   */
  setupFocusManagement() {
    // Track focus for keyboard navigation
    let isKeyboardNavigation = false;
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Tab') {
        isKeyboardNavigation = true;
        document.documentElement.classList.add('keyboard-navigation');
      }
    });
    
    document.addEventListener('mousedown', () => {
      isKeyboardNavigation = false;
      document.documentElement.classList.remove('keyboard-navigation');
    });
  }

  /**
   * Setup ARIA live regions
   */
  setupAriaLiveRegions() {
    // Create live region for announcements
    if (!document.getElementById('aria-live-region')) {
      const liveRegion = document.createElement('div');
      liveRegion.id = 'aria-live-region';
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      liveRegion.className = 'sr-only';
      document.body.appendChild(liveRegion);
    }
  }

  /**
   * Announce message to screen readers
   */
  announce(message) {
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = message;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  /**
   * Handle escape key press
   */
  handleEscape(event) {
    // Close modals, dropdowns, etc.
    const activeModal = document.querySelector('.modal.active');
    if (activeModal) {
      this.closeModal(activeModal);
    }
    
    // Clear focus from inputs
    if (event.target.matches('input, textarea')) {
      event.target.blur();
    }
  }

  /**
   * Handle arrow key navigation in lists
   */
  handleArrowNavigation(event) {
    const currentItem = event.target.closest('.item-list-item');
    if (!currentItem) return;
    
    const list = currentItem.closest('.item-list');
    if (!list) return;
    
    const items = Array.from(list.querySelectorAll('.item-list-item'));
    const currentIndex = items.indexOf(currentItem);
    
    let nextIndex;
    if (event.key === 'ArrowUp') {
      nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
    } else {
      nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
    }
    
    items[nextIndex].focus();
    event.preventDefault();
  }

  /**
   * Validate form before submission
   */
  validateForm(form) {
    let isValid = true;
    const errors = [];
    
    // Check required fields
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
      if (!field.value.trim()) {
        isValid = false;
        errors.push(`${field.labels[0]?.textContent || field.name} is required`);
        field.classList.add('input-error');
      } else {
        field.classList.remove('input-error');
      }
    });
    
    // Show validation errors
    if (!isValid) {
      this.components.get('messageSystem').showError(errors.join(', '));
    }
    
    return isValid;
  }

  /**
   * Show loading state on form
   */
  showFormLoading(form) {
    const submitButton = form.querySelector('[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Loading...';
      submitButton.classList.add('loading');
    }
  }

  /**
   * Debounce utility function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  /**
   * Get component instance
   */
  getComponent(name) {
    return this.components.get(name);
  }

  /**
   * Destroy the entry interface
   */
  destroy() {
    // Cleanup components
    this.components.forEach(component => {
      if (component.destroy) {
        component.destroy();
      }
    });
    
    this.components.clear();
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.entryInterface = new EntryInterface();
  });
} else {
  window.entryInterface = new EntryInterface();
}

// Export for module usage
export default EntryInterface;