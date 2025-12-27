/**
 * Tabs Component JavaScript
 * 
 * Handles tab switching functionality with keyboard navigation support
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeTabs();
});

function initializeTabs() {
    const tabContainers = document.querySelectorAll('.component-tabs-section');
    
    tabContainers.forEach(container => {
        const tabButtons = container.querySelectorAll('.tab-button');
        const tabPanels = container.querySelectorAll('.tab-panel');
        
        // Add click event listeners
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                switchTab(container, tabId);
            });
            
            // Add keyboard navigation
            button.addEventListener('keydown', function(e) {
                handleTabKeydown(e, tabButtons);
            });
        });
    });
}

function switchTab(container, targetTabId) {
    const tabButtons = container.querySelectorAll('.tab-button');
    const tabPanels = container.querySelectorAll('.tab-panel');
    
    // Update button states
    tabButtons.forEach(button => {
        const isActive = button.getAttribute('data-tab') === targetTabId;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    
    // Update panel visibility
    tabPanels.forEach(panel => {
        const isActive = panel.getAttribute('data-tab') === targetTabId;
        panel.classList.toggle('active', isActive);
        
        if (isActive) {
            panel.removeAttribute('hidden');
            panel.style.display = '';
        } else {
            panel.setAttribute('hidden', '');
            panel.style.display = 'none';
        }
    });
    
    // Trigger custom event for other components to react
    const event = new CustomEvent('tabChanged', {
        detail: { tabId: targetTabId, container: container }
    });
    container.dispatchEvent(event);
}

function handleTabKeydown(e, tabButtons) {
    const currentIndex = Array.from(tabButtons).indexOf(e.target);
    let targetIndex;
    
    switch (e.key) {
        case 'ArrowLeft':
            e.preventDefault();
            targetIndex = currentIndex > 0 ? currentIndex - 1 : tabButtons.length - 1;
            tabButtons[targetIndex].focus();
            tabButtons[targetIndex].click();
            break;
            
        case 'ArrowRight':
            e.preventDefault();
            targetIndex = currentIndex < tabButtons.length - 1 ? currentIndex + 1 : 0;
            tabButtons[targetIndex].focus();
            tabButtons[targetIndex].click();
            break;
            
        case 'Home':
            e.preventDefault();
            tabButtons[0].focus();
            tabButtons[0].click();
            break;
            
        case 'End':
            e.preventDefault();
            tabButtons[tabButtons.length - 1].focus();
            tabButtons[tabButtons.length - 1].click();
            break;
    }
}

// Export for use by other scripts
window.TabsComponent = {
    switchTab: switchTab,
    initializeTabs: initializeTabs
};