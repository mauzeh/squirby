/**
 * PR Celebration Script
 * 
 * Handles confetti animation and celebration when a personal record is logged.
 * Uses canvas-confetti library loaded from CDN.
 */

(function() {
    'use strict';
    
    /**
     * Load confetti library from CDN
     */
    function loadConfetti() {
        return new Promise((resolve, reject) => {
            // Check if confetti is already loaded
            if (window.confetti) {
                resolve(window.confetti);
                return;
            }
            
            // Load from CDN
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
            script.onload = () => resolve(window.confetti);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Trigger confetti celebration
     */
    function celebrateWithConfetti() {
        loadConfetti()
            .then(confetti => {
                // Main burst from center
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff']
                });
                
                // Left side burst
                setTimeout(() => {
                    confetti({
                        particleCount: 50,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0, y: 0.6 }
                    });
                }, 250);
                
                // Right side burst
                setTimeout(() => {
                    confetti({
                        particleCount: 50,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1, y: 0.6 }
                    });
                }, 400);
            })
            .catch(err => {
                console.warn('Failed to load confetti library:', err);
            });
    }
    
    /**
     * Check for PR flag and trigger celebration
     */
    function checkForPR() {
        // Check if this page load is after a PR was logged
        const isPR = sessionStorage.getItem('is_pr');
        
        if (isPR === 'true') {
            // Clear the flag so it doesn't trigger again
            sessionStorage.removeItem('is_pr');
            
            // Trigger celebration
            celebrateWithConfetti();
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkForPR);
    } else {
        checkForPR();
    }
})();
