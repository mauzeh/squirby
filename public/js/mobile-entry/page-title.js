/**
 * Page Title Component
 * Handles page title updates and dynamic content
 * Requirements: 4.3, 4.5, 7.1
 */

export class PageTitle {
  constructor() {
    this.titles = new Map();
    this.init();
  }

  init() {
    this.setupExistingTitles();
    this.bindEvents();
  }

  bindEvents() {
    // Listen for date changes to update titles
    document.addEventListener('dateChanged', (event) => {
      this.updateDateDependentTitles(event.detail.date);
    });

    // Listen for content changes
    document.addEventListener('contentUpdated', (event) => {
      this.updateDynamicTitles(event.detail);
    });
  }

  setupExistingTitles() {
    // Setup main page titles
    document.querySelectorAll('h1, .page-title').forEach(title => {
      this.setupTitle(title, 'main');
    });

    // Setup section titles
    document.querySelectorAll('h2, .section-title').forEach(title => {
      this.setupTitle(title, 'section');
    });

    // Setup subsection titles
    document.querySelectorAll('h3, .subsection-title').forEach(title => {
      this.setupTitle(title, 'subsection');
    });
  }

  setupTitle(element, type) {
    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    const config = {
      element,
      type,
      originalText: element.textContent,
      template: element.dataset.template || null,
      dynamic: element.hasAttribute('data-dynamic')
    };

    this.titles.set(id, config);

    // Setup ARIA attributes for better accessibility
    if (type === 'main') {
      element.setAttribute('role', 'heading');
      element.setAttribute('aria-level', '1');
    } else if (type === 'section') {
      element.setAttribute('role', 'heading');
      element.setAttribute('aria-level', '2');
    } else if (type === 'subsection') {
      element.setAttribute('role', 'heading');
      element.setAttribute('aria-level', '3');
    }
  }

  updateTitle(id, newText, options = {}) {
    const config = this.titles.get(id);
    if (!config) return;

    const { announce = true, updateDocumentTitle = false } = options;

    // Update the element text
    config.element.textContent = newText;

    // Update document title if this is the main title
    if (updateDocumentTitle || config.type === 'main') {
      document.title = newText;
    }

    // Announce change to screen readers
    if (announce) {
      this.announceTitle(newText);
    }

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('titleUpdated', {
      detail: { id, newText, type: config.type }
    }));
  }

  updateDateDependentTitles(date) {
    const dateString = date.toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });

    this.titles.forEach((config, id) => {
      if (config.template && config.template.includes('{date}')) {
        const newText = config.template.replace('{date}', dateString);
        this.updateTitle(id, newText, { announce: false });
      }
    });
  }

  updateDynamicTitles(data) {
    this.titles.forEach((config, id) => {
      if (!config.dynamic) return;

      let newText = config.template || config.originalText;

      // Replace template variables
      Object.keys(data).forEach(key => {
        const placeholder = `{${key}}`;
        if (newText.includes(placeholder)) {
          newText = newText.replace(new RegExp(placeholder, 'g'), data[key]);
        }
      });

      if (newText !== config.element.textContent) {
        this.updateTitle(id, newText, { announce: false });
      }
    });
  }

  setTitleTemplate(id, template) {
    const config = this.titles.get(id);
    if (config) {
      config.template = template;
      config.dynamic = true;
    }
  }

  addTitle(element, type = 'section', options = {}) {
    const id = element.id || this.generateId();
    if (!element.id) {
      element.id = id;
    }

    this.setupTitle(element, type);

    if (options.template) {
      this.setTitleTemplate(id, options.template);
    }

    return id;
  }

  removeTitle(id) {
    const config = this.titles.get(id);
    if (config && config.element.parentNode) {
      config.element.parentNode.removeChild(config.element);
      this.titles.delete(id);
    }
  }

  getTitleText(id) {
    const config = this.titles.get(id);
    return config ? config.element.textContent : null;
  }

  getAllTitles() {
    const result = {};
    this.titles.forEach((config, id) => {
      result[id] = {
        text: config.element.textContent,
        type: config.type,
        dynamic: config.dynamic
      };
    });
    return result;
  }

  announceTitle(text) {
    // Announce title change to screen readers
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = `Page title: ${text}`;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  generateId() {
    return 'title-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  // Utility methods for common title updates
  updateMainTitle(text) {
    const mainTitle = document.querySelector('h1, .page-title');
    if (mainTitle) {
      const id = mainTitle.id || this.addTitle(mainTitle, 'main');
      this.updateTitle(id, text, { updateDocumentTitle: true });
    }
  }

  updateSectionTitle(selector, text) {
    const sectionTitle = document.querySelector(selector);
    if (sectionTitle) {
      const id = sectionTitle.id || this.addTitle(sectionTitle, 'section');
      this.updateTitle(id, text);
    }
  }

  showLoadingTitle(id, loadingText = 'Loading...') {
    const config = this.titles.get(id);
    if (config) {
      config.element.classList.add('loading');
      config.element.textContent = loadingText;
    }
  }

  hideLoadingTitle(id, finalText = null) {
    const config = this.titles.get(id);
    if (config) {
      config.element.classList.remove('loading');
      if (finalText) {
        config.element.textContent = finalText;
      } else {
        config.element.textContent = config.originalText;
      }
    }
  }

  handleResize() {
    // Adjust title layout for different screen sizes
    this.titles.forEach((config) => {
      const element = config.element;
      
      if (window.innerWidth <= 480) {
        element.classList.add('compact');
      } else {
        element.classList.remove('compact');
      }
    });
  }

  destroy() {
    this.titles.clear();
  }
}