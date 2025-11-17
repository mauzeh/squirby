/**
 * Collapsible Form Section Toggle
 * Makes sections within forms collapsible with a chevron icon
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all collapsible form sections
    const collapsibleSections = document.querySelectorAll('.form-section-collapsible');
    
    collapsibleSections.forEach(section => {
        const header = section.querySelector('.form-section-header');
        
        if (!header) return;
        
        // Toggle function
        const toggleSection = () => {
            const isExpanded = section.classList.contains('expanded');
            
            if (isExpanded) {
                // Collapse
                section.classList.remove('expanded');
                section.classList.add('collapsed');
            } else {
                // Expand
                section.classList.remove('collapsed');
                section.classList.add('expanded');
                
                // Scroll to the section
                setTimeout(() => {
                    const sectionRect = section.getBoundingClientRect();
                    const targetPosition = window.scrollY + sectionRect.top - 20;
                    
                    window.scrollTo({
                        top: Math.max(0, targetPosition),
                        behavior: 'smooth'
                    });
                }, 100);
            }
        };
        
        // Add click handler to the header
        header.addEventListener('click', function(event) {
            event.preventDefault();
            toggleSection();
        });
    });
});
