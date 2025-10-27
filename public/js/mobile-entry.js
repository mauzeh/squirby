/**
 * Mobile Entry JavaScript
 * Handles filtering logic for item selection list
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get the filter input and item list
    const filterInput = document.querySelector('.item-filter-input');
    const itemList = document.querySelector('.item-selection-list');
    
    if (!filterInput || !itemList) {
        return; // Exit if elements don't exist
    }
    
    // Get all item cards (excluding the filter container)
    const getAllItemCards = () => {
        return Array.from(itemList.querySelectorAll('.item-selection-card'));
    };
    
    // Filter function
    const filterItems = (searchTerm) => {
        const itemCards = getAllItemCards();
        const normalizedSearch = searchTerm.toLowerCase().trim();
        
        itemCards.forEach(card => {
            const itemNameElement = card.querySelector('.item-name');
            if (!itemNameElement) return;
            
            const itemName = itemNameElement.textContent.toLowerCase();
            const listItem = card.closest('li');
            
            if (normalizedSearch === '' || itemName.includes(normalizedSearch)) {
                // Show the item
                listItem.style.display = '';
            } else {
                // Hide the item
                listItem.style.display = 'none';
            }
        });
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
});