/**
 * Date Navigation Component
 * Handles date navigation functionality and keyboard shortcuts
 * Requirements: 4.3, 4.5, 7.1
 */

export class DateNavigation {
  constructor() {
    this.currentDate = new Date();
    this.dateFormat = new Intl.DateTimeFormat('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupKeyboardShortcuts();
    this.updateDateDisplay();
  }

  bindEvents() {
    // Handle navigation button clicks
    document.addEventListener('click', (event) => {
      if (event.target.matches('.nav-button.nav-prev')) {
        this.navigateToPreviousDay();
      } else if (event.target.matches('.nav-button.nav-next')) {
        this.navigateToNextDay();
      } else if (event.target.matches('.nav-button.nav-today')) {
        this.navigateToToday();
      }
    });

    // Handle touch gestures for mobile
    this.setupTouchGestures();
  }

  setupKeyboardShortcuts() {
    document.addEventListener('keydown', (event) => {
      // Only handle shortcuts when not in input fields
      if (event.target.matches('input, textarea, select')) {
        return;
      }

      switch (event.key) {
        case 'ArrowLeft':
          if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            this.navigateToPreviousDay();
          }
          break;
        case 'ArrowRight':
          if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            this.navigateToNextDay();
          }
          break;
        case 't':
        case 'T':
          if (event.ctrlKey || event.metaKey) {
            event.preventDefault();
            this.navigateToToday();
          }
          break;
      }
    });
  }

  setupTouchGestures() {
    const dateNavigation = document.querySelector('.date-navigation-mobile');
    if (!dateNavigation) return;

    let startX = 0;
    let startY = 0;
    let threshold = 50; // Minimum distance for swipe

    dateNavigation.addEventListener('touchstart', (event) => {
      startX = event.touches[0].clientX;
      startY = event.touches[0].clientY;
    }, { passive: true });

    dateNavigation.addEventListener('touchend', (event) => {
      if (!startX || !startY) return;

      const endX = event.changedTouches[0].clientX;
      const endY = event.changedTouches[0].clientY;
      
      const deltaX = endX - startX;
      const deltaY = endY - startY;

      // Check if horizontal swipe is more significant than vertical
      if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > threshold) {
        if (deltaX > 0) {
          // Swipe right - previous day
          this.navigateToPreviousDay();
        } else {
          // Swipe left - next day
          this.navigateToNextDay();
        }
      }

      startX = 0;
      startY = 0;
    }, { passive: true });
  }

  navigateToPreviousDay() {
    const newDate = new Date(this.currentDate);
    newDate.setDate(newDate.getDate() - 1);
    this.navigateToDate(newDate);
  }

  navigateToNextDay() {
    const newDate = new Date(this.currentDate);
    newDate.setDate(newDate.getDate() + 1);
    this.navigateToDate(newDate);
  }

  navigateToToday() {
    this.navigateToDate(new Date());
  }

  navigateToDate(date) {
    this.currentDate = date;
    this.updateDateDisplay();
    this.updateUrl();
    this.announceNavigation();
    
    // Trigger custom event for other components
    document.dispatchEvent(new CustomEvent('dateChanged', {
      detail: { date: this.currentDate }
    }));
  }

  updateDateDisplay() {
    const dateDisplay = document.querySelector('.date-display');
    if (dateDisplay) {
      dateDisplay.textContent = this.dateFormat.format(this.currentDate);
    }

    // Update navigation button states
    this.updateNavigationButtons();
  }

  updateNavigationButtons() {
    const today = new Date();
    const isToday = this.isSameDay(this.currentDate, today);
    
    const todayButton = document.querySelector('.nav-button.nav-today');
    if (todayButton) {
      todayButton.disabled = isToday;
      todayButton.setAttribute('aria-pressed', isToday.toString());
    }

    // Update prev/next button accessibility
    const prevButton = document.querySelector('.nav-button.nav-prev');
    const nextButton = document.querySelector('.nav-button.nav-next');
    
    if (prevButton) {
      const prevDate = new Date(this.currentDate);
      prevDate.setDate(prevDate.getDate() - 1);
      prevButton.setAttribute('aria-label', `Go to ${this.dateFormat.format(prevDate)}`);
    }
    
    if (nextButton) {
      const nextDate = new Date(this.currentDate);
      nextDate.setDate(nextDate.getDate() + 1);
      nextButton.setAttribute('aria-label', `Go to ${this.dateFormat.format(nextDate)}`);
    }
  }

  updateUrl() {
    // Update URL with current date (if using client-side routing)
    const dateString = this.currentDate.toISOString().split('T')[0];
    const url = new URL(window.location);
    url.searchParams.set('date', dateString);
    
    // Use replaceState to avoid adding to browser history
    window.history.replaceState({}, '', url);
  }

  announceNavigation() {
    // Announce date change to screen readers
    const announcement = `Navigated to ${this.dateFormat.format(this.currentDate)}`;
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = announcement;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  isSameDay(date1, date2) {
    return date1.getFullYear() === date2.getFullYear() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getDate() === date2.getDate();
  }

  getCurrentDate() {
    return new Date(this.currentDate);
  }

  setCurrentDate(date) {
    this.navigateToDate(date);
  }

  getFormattedDate(format = 'iso') {
    switch (format) {
      case 'iso':
        return this.currentDate.toISOString().split('T')[0];
      case 'display':
        return this.dateFormat.format(this.currentDate);
      case 'short':
        return this.currentDate.toLocaleDateString();
      default:
        return this.currentDate.toString();
    }
  }

  handleClick(event) {
    // Handle navigation button clicks
    if (event.target.matches('.nav-button')) {
      // Add visual feedback
      event.target.classList.add('clicked');
      setTimeout(() => event.target.classList.remove('clicked'), 150);
    }
  }

  handleResize() {
    // Adjust navigation layout for different screen sizes
    const navigation = document.querySelector('.date-navigation-mobile');
    if (!navigation) return;

    if (window.innerWidth <= 480) {
      navigation.classList.add('compact');
    } else {
      navigation.classList.remove('compact');
    }
  }

  destroy() {
    // Cleanup event listeners if needed
    // (Most are on document, so they'll persist)
  }
}