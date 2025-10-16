<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
        <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
        <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

        <title>Nutrition Tracker</title>

        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    </head>
    <body>
        @if(session()->has('impersonator_id'))
            <div class="impersonation-bar">
                You are currently impersonating {{ auth()->user()->name }}. <a href="{{ route('users.leave-impersonate') }}" style="color: #000;">Switch Back</a>
            </div>
        @endif
        @if(app()->environment('production') || app()->environment('staging'))
            <div class="env-bar production">
                PRODUCTION / STAGING
            </div>
        @else
            <div class="env-bar local">
                LOCAL DEV ENVIRONMENT
            </div>
        @endif
        @auth
        <div class="navbar">
            <a href="{{ route('food-logs.index') }}" class="top-level-nav-item {{ Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*']) ? 'active' : '' }}"><i class="fas fa-utensils"></i> Food</a>
            <a id="lifts-nav-link" href="{{ route('lift-logs.index') }}" class="top-level-nav-item {{ Request::routeIs(['exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'exercise-intelligence.*']) ? 'active' : '' }}"><i class="fas fa-dumbbell"></i> Lifts</a>
            <a href="{{ route('body-logs.index') }}" class="top-level-nav-item {{ Request::routeIs(['body-logs.*', 'measurement-types.*']) ? 'active' : '' }}"><i class="fas fa-heartbeat"></i> Body</a>

            <div style="margin-left: auto;">
                @if (Auth::user()->hasRole('Admin'))
                    <a href="{{ route('users.index') }}" class="{{ Request::routeIs('users.*') ? 'active' : '' }}" style="padding: 14px 8px"><i class="fas fa-cog"></i></a>
                @endif
                <a href="{{ route('profile.edit') }}" class="{{ Request::routeIs('profile.edit') ? 'active' : '' }}" style="padding: 14px 8px"><i class="fas fa-user"></i></a>
                <form method="POST" action="{{ route('logout') }}" style="display: inline-block;">
                    @csrf
                    <button type="submit" style="background: none; border: none; color: #f2f2f2; text-align: center; padding: 14px 8px; text-decoration: none; font-size: 17px; cursor: pointer;">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>

        @if (Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*', 'exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'exercise-intelligence.*', 'body-logs.*', 'measurement-types.*']))
        <div class="navbar sub-navbar">
            @if (Request::routeIs(['food-logs.*', 'meals.*', 'ingredients.*']))
                <a href="{{ route('food-logs.index') }}" class="{{ Request::routeIs('food-logs.*') ? 'active' : '' }}">Log</a>
                <a href="{{ route('meals.index') }}" class="{{ Request::routeIs('meals.*') ? 'active' : '' }}">Meals</a>
                <a href="{{ route('ingredients.index') }}" class="{{ Request::routeIs('ingredients.*') ? 'active' : '' }}">Ingredients</a>
            @endif

            @if (Request::routeIs(['exercises.*', 'lift-logs.*', 'programs.*', 'recommendations.*', 'exercise-intelligence.*']))
                <a href="{{ route('lift-logs.mobile-entry') }}" class="{{ Request::routeIs('lift-logs.mobile-entry') ? 'active' : '' }}"><i class="fas fa-mobile-alt"></i></a>
                <a href="{{ route('lift-logs.index') }}" class="{{ Request::routeIs(['lift-logs.index', 'lift-logs.edit', 'lift-logs.import-tsv', 'lift-logs.destroy-selected', 'exercises.show-logs']) ? 'active' : '' }}">Log</a>
                <a href="{{ route('programs.index') }}" class="{{ Request::routeIs('programs.*') ? 'active' : '' }}">Program</a>
                <a href="{{ route('exercises.index') }}" class="{{ Request::routeIs(['exercises.index', 'exercises.create', 'exercises.edit', 'exercises.store', 'exercises.update', 'exercises.destroy']) ? 'active' : '' }}">Exercises</a>
                <a href="{{ route('recommendations.index') }}" class="{{ Request::routeIs('recommendations.*') ? 'active' : '' }}">Recommendations</a>
                @if(auth()->user()->hasRole('Admin'))
                    <a href="{{ route('exercise-intelligence.index') }}" class="{{ Request::routeIs('exercise-intelligence.*') ? 'active' : '' }}">Intelligence</a>
                @endif
            @endif

            @if (Request::routeIs(['body-logs.*', 'measurement-types.*']))
             <a href="{{ route('body-logs.index') }}" class="{{ Request::routeIs('body-logs.index') ? 'active' : '' }}">All</a>
                @foreach ($measurementTypes as $measurementType)
                    <a href="{{ route('body-logs.show-by-type', $measurementType) }}" class="{{ Request::is('body-logs/type/' . $measurementType->id) ? 'active' : '' }}">{{ $measurementType->name }}</a>
                @endforeach
            @endif
        </div>
        @endif
        @endauth
        <div class="content">
            @yield('content')
        </div>
        @auth
        <footer>
            <div class="container">
                @if(isset($queryCount))
                    <pre class="git-log">Queries: {{ $queryCount }}</pre><br>
                @endif
                @if(isset($gitLog))
                    <pre class="git-log">{{ $gitLog }}</pre>
                @endif
            </div>
        </footer>
        @endauth

        <script>
            // This script enhances the user experience on mobile devices by redirecting the main "Lifts" navigation link.
            document.addEventListener('DOMContentLoaded', function() {
                // Select the "Lifts" navigation link using its unique ID.
                const liftsNavLink = document.getElementById('lifts-nav-link');

                // Check if the link exists and if the screen width is indicative of a mobile device (768px or less).
                if (liftsNavLink && window.innerWidth <= 768) {
                    // If on a mobile device, change the link's destination to the mobile-specific entry page.
                    liftsNavLink.href = "{{ route('lift-logs.mobile-entry') }}";
                }

                // Initialize password visibility toggles
                initializePasswordToggles();
            });

            /**
             * Toggle password field visibility between text and password types
             * @param {string} targetId - The ID of the password input field to toggle
             */
            function togglePasswordVisibility(targetId) {
                const field = document.getElementById(targetId);
                const button = document.querySelector(`[data-target="${targetId}"]`);
                
                if (!field || !button) {
                    console.warn(`Password toggle: Could not find field or button for target ID: ${targetId}`);
                    return;
                }

                const icon = button.querySelector('i');
                if (!icon) {
                    console.warn(`Password toggle: Could not find icon in button for target ID: ${targetId}`);
                    return;
                }
                
                if (field.type === 'password') {
                    // Show password
                    field.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    button.setAttribute('aria-label', 'Hide password');
                } else {
                    // Hide password
                    field.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    button.setAttribute('aria-label', 'Show password');
                }
            }

            /**
             * Initialize password visibility toggle functionality
             * Sets up event listeners for all password toggle buttons
             */
            function initializePasswordToggles() {
                const toggleButtons = document.querySelectorAll('.password-toggle');
                
                toggleButtons.forEach(button => {
                    const targetId = button.getAttribute('data-target');
                    
                    if (!targetId) {
                        console.warn('Password toggle button found without data-target attribute');
                        return;
                    }

                    // Set initial aria-label
                    if (!button.getAttribute('aria-label')) {
                        button.setAttribute('aria-label', 'Show password');
                    }

                    // Add click event listener
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        togglePasswordVisibility(targetId);
                    });

                    // Add keyboard support (Enter and Space keys)
                    button.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            togglePasswordVisibility(targetId);
                        }
                    });

                    // Ensure button is focusable
                    if (!button.hasAttribute('tabindex')) {
                        button.setAttribute('tabindex', '0');
                    }
                });
            }
        </script>
    </body>
</html>
