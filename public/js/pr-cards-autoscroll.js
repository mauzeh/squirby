/**
 * PR Cards Auto-Scroll - Casino-style animation to bring recent PR into view
 * 
 * This module provides smooth, casino-style scrolling animation to automatically
 * bring the most recent PR card into view when it's outside the visible area.
 * Uses Intersection Observer to detect when PR card containers become visible.
 */

class PRCardsAutoScroll {
    constructor() {
        this.isScrolling = false;
        this.processedContainers = new WeakSet(); // Track containers we've already processed
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        // Set up intersection observer to watch for PR card containers becoming visible
        this.setupIntersectionObserver();
        
        // Check any containers that are already visible
        this.checkExistingContainers();
        
        // Watch for new containers being added to the DOM
        this.setupMutationObserver();
    }

    setupIntersectionObserver() {
        // Create intersection observer to detect when PR card containers become visible
        this.intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && entry.intersectionRatio > 0) {
                    // Container is now visible, check if it needs auto-scroll
                    this.handleContainerVisible(entry.target);
                }
            });
        }, {
            // Trigger when any part of the container becomes visible
            threshold: 0.1,
            // Add some margin to detect visibility slightly before it's fully in view
            rootMargin: '50px'
        });
    }

    setupMutationObserver() {
        // Watch for new PR card containers being added to the DOM
        this.mutationObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if the added node is a PR cards container
                            if (node.classList?.contains('pr-cards-horizontal')) {
                                this.observeContainer(node);
                            }
                            
                            // Check if the added node contains PR cards containers
                            const containers = node.querySelectorAll?.('.pr-cards-horizontal');
                            if (containers) {
                                containers.forEach(container => this.observeContainer(container));
                            }
                        }
                    });
                }
            });
        });

        // Start observing the entire document for changes
        this.mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    checkExistingContainers() {
        // Find and observe any PR card containers that already exist
        const containers = document.querySelectorAll('.pr-cards-horizontal');
        containers.forEach(container => {
            this.observeContainer(container);
            
            // If the container is already visible, handle it immediately
            const rect = container.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0 && rect.top < window.innerHeight && rect.bottom > 0) {
                this.handleContainerVisible(container);
            }
        });
    }

    observeContainer(container) {
        // Start observing this container for visibility changes
        if (this.intersectionObserver) {
            this.intersectionObserver.observe(container);
        }
    }

    handleContainerVisible(container) {
        // Avoid processing the same container multiple times
        if (this.processedContainers.has(container)) {
            return;
        }

        // Find the recent PR card in this container
        const recentCard = container.querySelector('.pr-card--recent');
        if (!recentCard) {
            return; // No recent PR card found
        }

        // Mark this container as processed
        this.processedContainers.add(container);

        // Check if the recent card needs to be scrolled into view
        this.checkAndScrollToRecentCard(container, recentCard);
    }

    checkAndScrollToRecentCard(container, recentCard) {
        if (this.isScrolling) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const cardRect = recentCard.getBoundingClientRect();

        // Check if card is fully visible within the container
        const isCardVisible = (
            cardRect.left >= containerRect.left &&
            cardRect.right <= containerRect.right
        );

        if (!isCardVisible) {
            // Use a shorter delay for more responsive feel
            setTimeout(() => {
                this.performCasinoScroll(container, recentCard);
            }, 300);
        }
    }

    performCasinoScroll(container, recentCard) {
        if (this.isScrolling) {
            return;
        }

        // Verify elements are still valid
        if (!container || !recentCard || !document.contains(container) || !document.contains(recentCard)) {
            return;
        }

        this.isScrolling = true;

        // Calculate target scroll position (center the recent card)
        const containerRect = container.getBoundingClientRect();
        const cardOffsetLeft = recentCard.offsetLeft;
        const cardWidth = recentCard.offsetWidth;
        const containerWidth = container.clientWidth;
        
        // Target position to center the card
        const targetScrollLeft = cardOffsetLeft - (containerWidth / 2) + (cardWidth / 2);
        
        // Ensure we don't scroll past the boundaries
        const maxScrollLeft = container.scrollWidth - container.clientWidth;
        const finalTargetScrollLeft = Math.max(0, Math.min(targetScrollLeft, maxScrollLeft));

        // Disable scroll-snap during animation for smoother scrolling
        const originalScrollSnapType = container.style.scrollSnapType;
        container.style.scrollSnapType = 'none';

        // Casino-style animation: overshoot then settle
        this.animateCasinoScroll(container, recentCard, finalTargetScrollLeft, () => {
            // Re-enable scroll-snap after animation
            container.style.scrollSnapType = originalScrollSnapType;
        });
    }

    animateCasinoScroll(container, recentCard, targetScrollLeft, onComplete = null) {
        const startScrollLeft = container.scrollLeft;
        const distance = targetScrollLeft - startScrollLeft;
        
        // If already at target, no need to scroll
        if (Math.abs(distance) < 5) {
            this.isScrolling = false;
            if (onComplete) onComplete();
            return;
        }

        // Casino-style animation parameters
        const duration = 900; // Optimized duration
        const overshootFactor = 0.1; // Reduced overshoot for smoother feel
        
        // Calculate overshoot position
        const overshootDistance = distance * (1 + overshootFactor);
        const overshootTarget = startScrollLeft + overshootDistance;
        
        // Ensure overshoot doesn't go out of bounds
        const maxScrollLeft = container.scrollWidth - container.clientWidth;
        const clampedOvershootTarget = Math.max(0, Math.min(overshootTarget, maxScrollLeft));
        
        const startTime = performance.now();
        let lastFrameTime = startTime;

        const animateStep = (currentTime) => {
            // Throttle to 60fps for consistent performance
            if (currentTime - lastFrameTime < 16.67) {
                requestAnimationFrame(animateStep);
                return;
            }
            lastFrameTime = currentTime;

            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            let currentScrollLeft;

            if (progress < 0.65) {
                // First 65% of animation: accelerate to overshoot with smooth easing
                const overshootProgress = progress / 0.65;
                const easedProgress = this.easeOutQuart(overshootProgress);
                currentScrollLeft = startScrollLeft + (clampedOvershootTarget - startScrollLeft) * easedProgress;
            } else {
                // Last 35% of animation: settle back to target with elastic ease
                const settleProgress = (progress - 0.65) / 0.35;
                const elasticProgress = this.easeOutElastic(settleProgress);
                currentScrollLeft = clampedOvershootTarget + (targetScrollLeft - clampedOvershootTarget) * elasticProgress;
            }

            // Round to prevent sub-pixel rendering issues
            container.scrollLeft = Math.round(currentScrollLeft);

            if (progress < 1) {
                requestAnimationFrame(animateStep);
            } else {
                // Ensure we end exactly at target
                container.scrollLeft = targetScrollLeft;
                this.isScrolling = false;
                
                // Execute completion callback
                if (onComplete) onComplete();
                
                // Add a subtle flash effect to the recent card
                this.flashRecentCard(recentCard);
            }
        };

        requestAnimationFrame(animateStep);
    }

    flashRecentCard(recentCard) {
        if (!recentCard || !document.contains(recentCard)) return;

        // Add a temporary flash class
        recentCard.classList.add('pr-card--flash');
        
        // Remove the flash class after animation
        setTimeout(() => {
            if (recentCard && document.contains(recentCard)) {
                recentCard.classList.remove('pr-card--flash');
            }
        }, 600);
    }

    // Easing functions for smooth animation
    easeOutQuart(t) {
        return 1 - Math.pow(1 - t, 4);
    }

    easeOutElastic(t) {
        const c4 = (2 * Math.PI) / 3;
        
        return t === 0
            ? 0
            : t === 1
            ? 1
            : Math.pow(2, -10 * t) * Math.sin((t * 10 - 0.75) * c4) + 1;
    }

    easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    easeOutBounce(t) {
        const n1 = 7.5625;
        const d1 = 2.75;

        if (t < 1 / d1) {
            return n1 * t * t;
        } else if (t < 2 / d1) {
            return n1 * (t -= 1.5 / d1) * t + 0.75;
        } else if (t < 2.5 / d1) {
            return n1 * (t -= 2.25 / d1) * t + 0.9375;
        } else {
            return n1 * (t -= 2.625 / d1) * t + 0.984375;
        }
    }

    // Public method to manually trigger scroll (for testing or manual use)
    scrollToRecentCard() {
        // Find all visible PR card containers and process them
        const containers = document.querySelectorAll('.pr-cards-horizontal');
        containers.forEach(container => {
            const rect = container.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                this.handleContainerVisible(container);
            }
        });
    }

    // Cleanup method
    destroy() {
        if (this.intersectionObserver) {
            this.intersectionObserver.disconnect();
        }
        if (this.mutationObserver) {
            this.mutationObserver.disconnect();
        }
    }
}

// Initialize the auto-scroll when the script loads
const prCardsAutoScroll = new PRCardsAutoScroll();

// Export for potential manual use
window.PRCardsAutoScroll = prCardsAutoScroll;