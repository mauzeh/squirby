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
     * Trigger confetti celebration with 3 randomized waves
     */
    function celebrateWithConfetti() {
        loadConfetti()
            .then(confetti => {
                // Helper to get random value in range
                const random = (min, max) => Math.random() * (max - min) + min;
                
                // Helper to get random colors
                const getRandomColors = () => {
                    const allColors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ff6b35', '#f7931e', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c', '#e91e63'];
                    const count = Math.floor(random(4, 8));
                    const shuffled = allColors.sort(() => 0.5 - Math.random());
                    return shuffled.slice(0, count);
                };
                
                // Helper to create a random confetti burst
                const randomBurst = () => {
                    const burstType = Math.random();
                    
                    if (burstType < 0.4) {
                        // Center burst
                        return {
                            particleCount: Math.floor(random(60, 100)),
                            spread: random(60, 100),
                            origin: { x: random(0.3, 0.7), y: random(0.4, 0.7) },
                            colors: getRandomColors(),
                            startVelocity: random(25, 40)
                        };
                    } else if (burstType < 0.7) {
                        // Side burst
                        const fromLeft = Math.random() > 0.5;
                        return {
                            particleCount: Math.floor(random(40, 80)),
                            angle: fromLeft ? random(45, 75) : random(105, 135),
                            spread: random(40, 70),
                            origin: { x: fromLeft ? random(0, 0.15) : random(0.85, 1), y: random(0.4, 0.7) },
                            colors: getRandomColors(),
                            startVelocity: random(20, 35)
                        };
                    } else {
                        // Top burst (raining down)
                        return {
                            particleCount: Math.floor(random(50, 90)),
                            spread: random(80, 120),
                            origin: { x: random(0.2, 0.8), y: random(0.1, 0.3) },
                            colors: getRandomColors(),
                            startVelocity: random(15, 25),
                            gravity: random(0.8, 1.2)
                        };
                    }
                };
                
                // Create 3 waves with randomized timing
                const waves = [];
                let cumulativeDelay = 0;
                
                for (let i = 0; i < 3; i++) {
                    // Random delay between waves (400ms to 800ms)
                    const delay = i === 0 ? 0 : random(400, 800);
                    cumulativeDelay += delay;
                    
                    waves.push({
                        delay: cumulativeDelay,
                        config: randomBurst()
                    });
                }
                
                // Execute all waves
                waves.forEach(wave => {
                    setTimeout(() => {
                        confetti(wave.config);
                    }, wave.delay);
                });
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
        
        // Check if all logged items on this page are PRs
        const allPRs = window.mobileEntryConfig && window.mobileEntryConfig.allPRs;
        
        if (isPR === 'true') {
            // Clear the flag so it doesn't trigger again
            sessionStorage.removeItem('is_pr');
            
            // Trigger celebration
            celebrateWithConfetti();
        } else if (allPRs) {
            // All items on the page are PRs - celebrate!
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
