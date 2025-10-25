/**
 * Base Layout Component
 * Handles layout management and responsive behavior
 * Requirements: 4.3, 4.5, 7.1
 */

export class BaseLayout {
  constructor() {
    this.layouts = new Map();
    this.breakpoints = {
      mobile: 480,
      tablet: 768,
      desktop: 1024
    };
    this.currentBreakpoint = this.getCurrentBreakpoint();
    this.init();
  }

  init() {
    this.setupExistingLayouts();
    this.bindEvents();
    this.setupResponsiveHandling();
  }

  bindEvents() {
    // Handle window resize
    window.addEventListener('resize', this.debounce(this.handleResize.bind(this), 250));
    
    // Handle orientation change
    window.addEventListener('orientationchange', () => {
      setTimeout(() => this.handleResize(), 100);
    });

    // Handle layout toggle buttons
    document.addEventListener('click', (event) => {
      if (event.target.matches('[data-layout-toggle]')) {
        this.handleLayoutToggle(event.target);
      }
    });
  }

  setupExistingLayouts() {
    // Setup main layout containers
    document.querySelectorAll('.mobile-entry-layout, .mobile-entry-container').forEach(layout => {
      this.setupLayout(layout);
    });

    // Setup grid layouts
    document.querySelectorAll('.entry-grid').forEach(grid => {
      this.setupGridLayout(grid);
    });

    // Setup flex layouts
    document.querySelectorAll('.entry-flex').forEach(flex => {
      this.setupFlexLayout(flex);
    });
  }

  setupLayout(element) {
    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    const config = {
      element,
      type: this.getLayoutType(element),
      responsive: element.hasAttribute('data-responsive'),
      breakpoints: this.parseBreakpoints(element.dataset.breakpoints)
    };

    this.layouts.set(id, config);
    this.applyResponsiveLayout(config);
  }

  setupGridLayout(element) {
    const config = {
      element,
      type: 'grid',
      columns: element.dataset.columns || 'auto',
      responsive: true,
      breakpoints: {
        mobile: element.dataset.mobileColumns || '1',
        tablet: element.dataset.tabletColumns || '2',
        desktop: element.dataset.desktopColumns || element.dataset.columns || 'auto'
      }
    };

    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    this.layouts.set(id, config);
    this.applyGridLayout(config);
  }

  setupFlexLayout(element) {
    const config = {
      element,
      type: 'flex',
      direction: element.dataset.direction || 'row',
      responsive: true,
      breakpoints: {
        mobile: element.dataset.mobileDirection || 'column',
        tablet: element.dataset.tabletDirection || element.dataset.direction || 'row',
        desktop: element.dataset.direction || 'row'
      }
    };

    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    this.layouts.set(id, config);
    this.applyFlexLayout(config);
  }

  setupResponsiveHandling() {
    // Apply initial responsive states
    this.updateBreakpointClasses();
    this.applyAllResponsiveLayouts();
  }

  handleResize() {
    const newBreakpoint = this.getCurrentBreakpoint();
    
    if (newBreakpoint !== this.currentBreakpoint) {
      this.currentBreakpoint = newBreakpoint;
      this.updateBreakpointClasses();
      this.applyAllResponsiveLayouts();
      
      // Trigger custom event
      document.dispatchEvent(new CustomEvent('breakpointChanged', {
        detail: { breakpoint: newBreakpoint }
      }));
    }

    // Update layout-specific responsive behavior
    this.layouts.forEach(config => {
      this.updateLayoutForBreakpoint(config);
    });
  }

  handleLayoutToggle(button) {
    const targetId = button.dataset.layoutToggle;
    const config = this.layouts.get(targetId);
    
    if (!config) return;

    const toggleClass = button.dataset.toggleClass || 'collapsed';
    config.element.classList.toggle(toggleClass);
    
    // Update button state
    const isToggled = config.element.classList.contains(toggleClass);
    button.setAttribute('aria-expanded', (!isToggled).toString());
    
    // Announce state change
    const announcement = isToggled ? 'collapsed' : 'expanded';
    this.announceLayoutChange(config.element, announcement);
  }

  getCurrentBreakpoint() {
    const width = window.innerWidth;
    
    if (width <= this.breakpoints.mobile) {
      return 'mobile';
    } else if (width <= this.breakpoints.tablet) {
      return 'tablet';
    } else {
      return 'desktop';
    }
  }

  updateBreakpointClasses() {
    const html = document.documentElement;
    
    // Remove existing breakpoint classes
    html.classList.remove('mobile', 'tablet', 'desktop');
    
    // Add current breakpoint class
    html.classList.add(this.currentBreakpoint);
  }

  applyAllResponsiveLayouts() {
    this.layouts.forEach(config => {
      this.applyResponsiveLayout(config);
    });
  }

  applyResponsiveLayout(config) {
    if (!config.responsive) return;

    switch (config.type) {
      case 'grid':
        this.applyGridLayout(config);
        break;
      case 'flex':
        this.applyFlexLayout(config);
        break;
      case 'container':
        this.applyContainerLayout(config);
        break;
    }
  }

  applyGridLayout(config) {
    const columns = config.breakpoints[this.currentBreakpoint] || config.columns;
    
    if (columns === 'auto') {
      config.element.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
    } else {
      config.element.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;
    }
  }

  applyFlexLayout(config) {
    const direction = config.breakpoints[this.currentBreakpoint] || config.direction;
    config.element.style.flexDirection = direction;
  }

  applyContainerLayout(config) {
    // Apply container-specific responsive behavior
    const element = config.element;
    
    switch (this.currentBreakpoint) {
      case 'mobile':
        element.classList.add('mobile-layout');
        element.classList.remove('tablet-layout', 'desktop-layout');
        break;
      case 'tablet':
        element.classList.add('tablet-layout');
        element.classList.remove('mobile-layout', 'desktop-layout');
        break;
      case 'desktop':
        element.classList.add('desktop-layout');
        element.classList.remove('mobile-layout', 'tablet-layout');
        break;
    }
  }

  updateLayoutForBreakpoint(config) {
    // Update layout-specific behavior for current breakpoint
    const element = config.element;
    
    // Update spacing
    this.updateSpacing(element);
    
    // Update component visibility
    this.updateComponentVisibility(element);
    
    // Update touch targets
    this.updateTouchTargets(element);
  }

  updateSpacing(element) {
    // Adjust spacing based on breakpoint
    if (this.currentBreakpoint === 'mobile') {
      element.classList.add('compact-spacing');
    } else {
      element.classList.remove('compact-spacing');
    }
  }

  updateComponentVisibility(element) {
    // Handle responsive component visibility
    const hideOnMobile = element.querySelectorAll('[data-hide-mobile]');
    const hideOnDesktop = element.querySelectorAll('[data-hide-desktop]');
    
    hideOnMobile.forEach(el => {
      el.style.display = this.currentBreakpoint === 'mobile' ? 'none' : '';
    });
    
    hideOnDesktop.forEach(el => {
      el.style.display = this.currentBreakpoint === 'desktop' ? 'none' : '';
    });
  }

  updateTouchTargets(element) {
    // Ensure proper touch targets on mobile
    if (this.currentBreakpoint === 'mobile') {
      const interactiveElements = element.querySelectorAll('button, a, input, [role="button"]');
      interactiveElements.forEach(el => {
        if (!el.classList.contains('large-input')) {
          el.style.minHeight = '44px';
          el.style.minWidth = '44px';
        }
      });
    }
  }

  getLayoutType(element) {
    if (element.classList.contains('entry-grid')) {
      return 'grid';
    } else if (element.classList.contains('entry-flex')) {
      return 'flex';
    } else {
      return 'container';
    }
  }

  parseBreakpoints(breakpointsString) {
    if (!breakpointsString) return {};
    
    try {
      return JSON.parse(breakpointsString);
    } catch (e) {
      return {};
    }
  }

  announceLayoutChange(element, state) {
    const elementName = element.getAttribute('aria-label') || 
                       element.querySelector('h1, h2, h3')?.textContent || 
                       'Layout section';
    
    const announcement = `${elementName} ${state}`;
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = announcement;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  addLayout(element, config = {}) {
    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    const layoutConfig = {
      element,
      type: config.type || this.getLayoutType(element),
      responsive: config.responsive !== false,
      breakpoints: config.breakpoints || {}
    };

    this.layouts.set(id, layoutConfig);
    this.applyResponsiveLayout(layoutConfig);
    
    return id;
  }

  removeLayout(id) {
    const config = this.layouts.get(id);
    if (config) {
      // Reset inline styles
      config.element.style.gridTemplateColumns = '';
      config.element.style.flexDirection = '';
      
      this.layouts.delete(id);
    }
  }

  getLayout(id) {
    return this.layouts.get(id);
  }

  getCurrentBreakpointName() {
    return this.currentBreakpoint;
  }

  isBreakpoint(breakpoint) {
    return this.currentBreakpoint === breakpoint;
  }

  isMobile() {
    return this.currentBreakpoint === 'mobile';
  }

  isTablet() {
    return this.currentBreakpoint === 'tablet';
  }

  isDesktop() {
    return this.currentBreakpoint === 'desktop';
  }

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

  generateId() {
    return 'layout-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  handleResize() {
    // This method is called by the main resize handler
    const newBreakpoint = this.getCurrentBreakpoint();
    
    if (newBreakpoint !== this.currentBreakpoint) {
      this.currentBreakpoint = newBreakpoint;
      this.updateBreakpointClasses();
      this.applyAllResponsiveLayouts();
      
      // Trigger custom event
      document.dispatchEvent(new CustomEvent('breakpointChanged', {
        detail: { breakpoint: newBreakpoint }
      }));
    }

    // Update layout-specific responsive behavior
    this.layouts.forEach(config => {
      this.updateLayoutForBreakpoint(config);
    });
  }

  destroy() {
    // Reset all layouts
    this.layouts.forEach((config, id) => {
      this.removeLayout(id);
    });
    
    this.layouts.clear();
  }
}