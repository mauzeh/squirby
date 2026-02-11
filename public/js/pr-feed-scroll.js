// PR Feed Scroll to Anchor
(function() {
    'use strict';
    
    console.log('[PR Scroll] Script loaded');
    console.log('[PR Scroll] Current URL:', window.location.href);
    console.log('[PR Scroll] Hash:', window.location.hash);
    console.log('[PR Scroll] Document ready state:', document.readyState);
    
    function scrollToAnchor() {
        console.log('[PR Scroll] scrollToAnchor function called');
        console.log('[PR Scroll] Hash at execution:', window.location.hash);
        
        if (!window.location.hash) {
            console.log('[PR Scroll] No hash found, exiting');
            return;
        }
        
        const targetId = window.location.hash.substring(1);
        console.log('[PR Scroll] Looking for element with ID:', targetId);
        
        // List all elements with IDs that start with 'pr-'
        const allPrElements = document.querySelectorAll('[id^="pr-"]');
        console.log('[PR Scroll] Found', allPrElements.length, 'elements with IDs starting with "pr-"');
        allPrElements.forEach(el => {
            console.log('[PR Scroll] - Found element:', el.id);
        });
        
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            console.log('[PR Scroll] Found target element!', targetElement);
            console.log('[PR Scroll] Element position:', targetElement.getBoundingClientRect());
            
            // Find the parent pr-lift-session to highlight
            const liftSession = targetElement.closest('.pr-lift-session');
            
            if (liftSession) {
                console.log('[PR Scroll] Found parent lift session to highlight');
                
                // Scroll to the lift session (not the anchor) and position it near the top
                liftSession.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                console.log('[PR Scroll] Scroll initiated');
                
                // Add a smooth highlight effect to the lift session
                liftSession.style.transition = 'box-shadow 0.6s ease-in-out, background-color 0.6s ease-in-out';
                liftSession.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.8)';
                liftSession.style.backgroundColor = 'rgba(255, 107, 107, 0.1)';
                
                // Fade out after 5 seconds
                setTimeout(() => {
                    liftSession.style.boxShadow = '';
                    liftSession.style.backgroundColor = '';
                    console.log('[PR Scroll] Highlight fading out');
                }, 5000);
            } else {
                console.log('[PR Scroll] Could not find parent lift session');
                // Fallback: just scroll
                targetElement.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
        } else {
            console.log('[PR Scroll] Element not found with ID:', targetId);
            console.log('[PR Scroll] Total elements in document:', document.querySelectorAll('*').length);
        }
    }
    
    // Try multiple approaches to ensure it runs
    console.log('[PR Scroll] Setting up event listeners');
    
    // Approach 1: Immediate if already loaded
    if (document.readyState === 'complete') {
        console.log('[PR Scroll] Document already complete, running immediately');
        setTimeout(scrollToAnchor, 100);
    } else {
        console.log('[PR Scroll] Document not complete, waiting for load event');
        
        // Approach 2: Wait for window load
        window.addEventListener('load', function() {
            console.log('[PR Scroll] Window load event fired');
            setTimeout(scrollToAnchor, 100);
        });
        
        // Approach 3: Also try DOMContentLoaded as backup
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[PR Scroll] DOMContentLoaded event fired');
            setTimeout(scrollToAnchor, 500);
        });
    }
    
    // Approach 4: Delayed fallback
    setTimeout(function() {
        console.log('[PR Scroll] Delayed fallback executing (1 second)');
        scrollToAnchor();
    }, 1000);
})();

