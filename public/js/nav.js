document.addEventListener('DOMContentLoaded', function() {
    let lastScrollTop = 0;
    const navbar = document.querySelector('.auto-hiding-navbar');
    const content = document.querySelector('.content');
    let isMouseOverNav = false;
    const scrollThreshold = 50; // Only show nav if scrolled up by this amount
    let scrollTimeout;

    navbar.addEventListener('mouseenter', () => {
        isMouseOverNav = true;
    });

    navbar.addEventListener('mouseleave', () => {
        isMouseOverNav = false;
    });

    content.addEventListener('click', () => {
        navbar.classList.add('is-hidden');
    });

    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (scrollTop > lastScrollTop) {
            // Downscroll
            if (!isMouseOverNav) {
                navbar.classList.add('is-hidden');
            }
            clearTimeout(scrollTimeout);
        } else {
            // Upscroll
            if (lastScrollTop - scrollTop > scrollThreshold) {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    navbar.classList.remove('is-hidden');
                }, 200); // Wait 200ms before showing the nav
            }
        }

        // Always show navbar if at the very top
        if (scrollTop === 0) {
            navbar.classList.remove('is-hidden');
            clearTimeout(scrollTimeout); // Clear any pending hide timeouts
        }

        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop; // For Mobile or negative scrolling
    });
});