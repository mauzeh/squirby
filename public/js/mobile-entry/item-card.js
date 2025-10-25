/**
 * Item Card Component
 * Handles item card interactions, animations, and accessibility
 * Requirements: 4.3, 4.5, 7.1
 */

export class ItemCard {
  constructor() {
    this.cards = new Set();
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupExistingCards();
  }

  bindEvents() {
    // Handle card clicks
    document.addEventListener('click', (event) => {
      if (event.target.matches('.item-list-item')) {
        this.handleCardClick(event.target, event);
      } else if (event.target.matches('.delete-button')) {
        this.handleDeleteClick(event.target, event);
      }
    });

    // Handle keyboard navigation
    document.addEventListener('keydown', (event) => {
      if (event.target.matches('.item-list-item')) {
        this.handleCardKeyDown(event);
      }
    });

    // Handle hover effects on non-touch devices
    if (!('ontouchstart' in window)) {
      this.setupHoverEffects();
    }
  }

  setupExistingCards() {
    document.querySelectorAll('.item-list-item, .program-card').forEach(card => {
      this.setupCard(card);
    });
  }

  setupCard(card) {
    // Make card focusable if it's interactive
    if (!card.hasAttribute('tabindex') && (card.href || card.onclick)) {
      card.setAttribute('tabindex', '0');
    }

    // Setup ARIA attributes
    if (card.classList.contains('item-list-item')) {
      card.setAttribute('role', 'button');
      
      const itemName = card.querySelector('.item-name');
      const itemLabel = card.querySelector('.item-label');
      
      if (itemName && itemLabel) {
        const description = `${itemName.textContent} - ${itemLabel.textContent}`;
        card.setAttribute('aria-label', description);
      }
    }

    // Setup delete button accessibility
    const deleteButton = card.querySelector('.delete-button');
    if (deleteButton) {
      const itemName = card.querySelector('.item-name, h2');
      if (itemName) {
        deleteButton.setAttribute('aria-label', `Delete ${itemName.textContent}`);
      }
    }

    this.cards.add(card);
  }

  setupHoverEffects() {
    // Add smooth hover transitions for desktop
    document.addEventListener('mouseenter', (event) => {
      if (event.target.matches('.item-list-item, .program-card')) {
        event.target.classList.add('hover');
      }
    }, true);

    document.addEventListener('mouseleave', (event) => {
      if (event.target.matches('.item-list-item, .program-card')) {
        event.target.classList.remove('hover');
      }
    }, true);
  }

  handleCardClick(card, event) {
    // Prevent click if delete button was clicked
    if (event.target.closest('.delete-button')) {
      return;
    }

    // Add click animation
    this.addClickAnimation(card);

    // Handle different card types
    if (card.classList.contains('item-list-item')) {
      this.handleItemSelection(card);
    } else if (card.classList.contains('program-card')) {
      this.handleProgramCardClick(card);
    }
  }

  handleDeleteClick(deleteButton, event) {
    event.stopPropagation();
    event.preventDefault();

    const card = deleteButton.closest('.item-list-item, .program-card');
    if (!card) return;

    // Show confirmation dialog
    this.showDeleteConfirmation(card, deleteButton);
  }

  handleCardKeyDown(event) {
    const card = event.target;

    switch (event.key) {
      case 'Enter':
      case ' ':
        event.preventDefault();
        card.click();
        break;
      case 'Delete':
      case 'Backspace':
        const deleteButton = card.querySelector('.delete-button');
        if (deleteButton) {
          event.preventDefault();
          this.handleDeleteClick(deleteButton, event);
        }
        break;
    }
  }

  handleItemSelection(item) {
    // Toggle selection state
    const wasSelected = item.classList.contains('selected');
    
    // Clear other selections in the same list
    const list = item.closest('.item-list');
    if (list) {
      list.querySelectorAll('.item-list-item.selected').forEach(selectedItem => {
        selectedItem.classList.remove('selected');
        selectedItem.setAttribute('aria-selected', 'false');
      });
    }

    if (!wasSelected) {
      item.classList.add('selected');
      item.setAttribute('aria-selected', 'true');
      
      // Announce selection to screen readers
      const itemName = item.querySelector('.item-name');
      if (itemName) {
        this.announceSelection(itemName.textContent);
      }
    }

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('itemSelected', {
      detail: { item, selected: !wasSelected }
    }));
  }

  handleProgramCardClick(card) {
    // Add visual feedback
    this.addClickAnimation(card);

    // Trigger custom event for program card interaction
    document.dispatchEvent(new CustomEvent('programCardClicked', {
      detail: { card }
    }));
  }

  showDeleteConfirmation(card, deleteButton) {
    const itemName = card.querySelector('.item-name, h2');
    const name = itemName ? itemName.textContent : 'this item';
    
    const confirmed = confirm(`Are you sure you want to delete "${name}"?`);
    
    if (confirmed) {
      this.deleteCard(card);
    }
  }

  deleteCard(card) {
    // Add delete animation
    card.classList.add('deleting');
    
    // Announce deletion to screen readers
    const itemName = card.querySelector('.item-name, h2');
    if (itemName) {
      this.announceDeletion(itemName.textContent);
    }

    // Remove after animation
    setTimeout(() => {
      if (card.parentNode) {
        card.parentNode.removeChild(card);
      }
      this.cards.delete(card);
      
      // Check if list is now empty
      this.checkEmptyState(card);
    }, 300);

    // Trigger custom event
    document.dispatchEvent(new CustomEvent('itemDeleted', {
      detail: { card }
    }));
  }

  addClickAnimation(card) {
    card.classList.add('clicked');
    setTimeout(() => card.classList.remove('clicked'), 150);
  }

  checkEmptyState(deletedCard) {
    const list = deletedCard.closest('.item-list');
    if (!list) return;

    const remainingItems = list.querySelectorAll('.item-list-item');
    if (remainingItems.length === 0) {
      // Show empty state
      const emptyState = document.createElement('div');
      emptyState.className = 'item-list-empty';
      emptyState.textContent = 'No items found';
      list.appendChild(emptyState);
    }
  }

  announceSelection(itemName) {
    const announcement = `Selected ${itemName}`;
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = announcement;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  announceDeletion(itemName) {
    const announcement = `Deleted ${itemName}`;
    const liveRegion = document.getElementById('aria-live-region');
    if (liveRegion) {
      liveRegion.textContent = announcement;
      setTimeout(() => liveRegion.textContent = '', 1000);
    }
  }

  getSelectedItems() {
    return Array.from(document.querySelectorAll('.item-list-item.selected'));
  }

  clearSelection() {
    document.querySelectorAll('.item-list-item.selected').forEach(item => {
      item.classList.remove('selected');
      item.setAttribute('aria-selected', 'false');
    });
  }

  selectItem(item) {
    this.handleItemSelection(item);
  }

  handleClick(event) {
    // Handle clicks with proper delegation
    if (event.target.matches('.item-list-item, .program-card')) {
      this.handleCardClick(event.target, event);
    }
  }

  handleResize() {
    // Adjust card layout for different screen sizes
    this.cards.forEach(card => {
      if (window.innerWidth <= 480) {
        card.classList.add('compact');
      } else {
        card.classList.remove('compact');
      }
    });
  }

  destroy() {
    this.cards.clear();
  }
}