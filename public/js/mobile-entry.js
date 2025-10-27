/**
 * Mobile Entry JavaScript
 * Handles filtering logic for item selection list
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get the filter input, clear button, hidden input, and item list
    const filterInput = document.querySelector('.item-filter-input');
    const clearButton = document.querySelector('.btn-clear-filter');
    const hiddenInput = document.querySelector('.create-item-input');
    const itemList = document.querySelector('.item-selection-list');
    
    if (!filterInput || !itemList || !clearButton || !hiddenInput) {
        return; // Exit if elements don't exist
    }
    
    // Get all item cards (excluding the filter container and no-results item)
    const getAllItemCards = () => {
        return Array.from(itemList.querySelectorAll('.item-selection-card:not(.item-selection-card--no-results)'));
    };
    
    // Get the no results item
    const noResultsItem = itemList.querySelector('.no-results-item');
    
    // Filter function
    const filterItems = (searchTerm) => {
        const itemCards = getAllItemCards();
        const normalizedSearch = searchTerm.toLowerCase().trim();
        let visibleCount = 0;
        
        itemCards.forEach(card => {
            const itemNameElement = card.querySelector('.item-name');
            if (!itemNameElement) return;
            
            const itemName = itemNameElement.textContent.toLowerCase();
            const listItem = card.closest('li');
            
            if (normalizedSearch === '' || itemName.includes(normalizedSearch)) {
                // Show the item
                listItem.style.display = '';
                visibleCount++;
            } else {
                // Hide the item
                listItem.style.display = 'none';
            }
        });
        
        // Show/hide no results item based on whether we have visible items and a search term
        if (noResultsItem) {
            if (normalizedSearch !== '' && visibleCount === 0) {
                noResultsItem.style.display = '';
            } else {
                noResultsItem.style.display = 'none';
            }
        }
        
        // Show/hide clear button based on whether there's text in the input
        if (clearButton) {
            if (normalizedSearch !== '') {
                clearButton.style.display = '';
            } else {
                clearButton.style.display = 'none';
            }
        }
        
        // Sync the filter input value with the hidden form input
        if (hiddenInput) {
            hiddenInput.value = searchTerm;
        }
    };
    
    // Clear filter function
    const clearFilter = () => {
        filterInput.value = '';
        filterItems('');
        filterInput.focus();
    };
    
    // Add event listener for input changes
    filterInput.addEventListener('input', function(e) {
        filterItems(e.target.value);
    });
    
    // Also handle keyup for better responsiveness
    filterInput.addEventListener('keyup', function(e) {
        filterItems(e.target.value);
    });
    
    // Clear filter when input is cleared
    filterInput.addEventListener('change', function(e) {
        if (e.target.value === '') {
            filterItems('');
        }
    });
    
    // Add event listener for clear button
    clearButton.addEventListener('click', clearFilter);
});