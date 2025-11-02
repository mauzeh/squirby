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

        <title>Quantified Athletics</title>

        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @yield('styles')
        @yield('scripts')
    </head>
    <body>
        @if(session()->has('impersonator_id'))
            <div class="impersonation-bar">
                You are currently impersonating {{ auth()->user()->name }}. <a href="{{ route('users.leave-impersonate') }}" style="color: #000;">Switch Back</a>
            </div>
        @endif
        @if(app()->environment('staging'))
            <div class="env-bar staging">
                STAGING ENVIRONMENT
            </div>
        @elseif(app()->environment('local'))
            <div class="env-bar local">
                LOCAL DEV ENVIRONMENT
            </div>
        @endif
        @auth
        <div class="navbar">
            @foreach($mainNavigationLeft as $item)
                @if(isset($item['label']))
                    <a id="{{ $item['id'] }}" href="{{ route($item['route']) }}" class="top-level-nav-item {{ $item['isActive'] ? 'active' : '' }}" @if(isset($item['style'])) style="{{ $item['style'] }}" @endif>
                        @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif{{ $item['label'] }}
                    </a>
                @else
                    <a id="{{ $item['id'] }}" href="{{ route($item['route']) }}" class="{{ $item['isActive'] ? 'active' : '' }}" @if(isset($item['style'])) style="{{ $item['style'] }}" @endif @if(isset($item['title'])) title="{{ $item['title'] }}" @endif>
                        @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif
                    </a>
                @endif
            @endforeach

            <div style="margin-left: auto;">
                @foreach($mainNavigationRight as $item)
                    @if(isset($item['type']) && $item['type'] === 'form')
                        <form method="POST" action="{{ route($item['route']) }}" style="display: inline-block;">
                            @csrf
                            <button type="submit" style="background: none; border: none; color: #f2f2f2; text-align: center; padding: 14px 8px; text-decoration: none; font-size: 17px; cursor: pointer;">
                                @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif
                            </button>
                        </form>
                    @else
                        <a id="{{ $item['id'] }}" href="{{ route($item['route']) }}" class="{{ $item['isActive'] ? 'active' : '' }}" @if(isset($item['style'])) style="{{ $item['style'] }}" @endif @if(isset($item['title'])) title="{{ $item['title'] }}" @endif>
                            @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif{{ $item['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        @if ($shouldShowSubmenu)
        <div class="navbar sub-navbar">
            @foreach($subNavigation as $item)
                @if(isset($item['label']))
                    <a href="{{ isset($item['routeParams']) ? route($item['route'], $item['routeParams']) : route($item['route']) }}" class="{{ $item['isActive'] ? 'active' : '' }}" @if(isset($item['title'])) title="{{ $item['title'] }}" @endif>
                        @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif{{ $item['label'] }}
                    </a>
                @else
                    <a href="{{ isset($item['routeParams']) ? route($item['route'], $item['routeParams']) : route($item['route']) }}" class="{{ $item['isActive'] ? 'active' : '' }}" @if(isset($item['title'])) title="{{ $item['title'] }}" @endif>
                        @if(isset($item['icon']))<i class="{{ $item['icon'] }}"></i> @endif
                    </a>
                @endif
            @endforeach
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
            document.addEventListener('DOMContentLoaded', function() {
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