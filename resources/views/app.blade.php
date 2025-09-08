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
            <a href="{{ route('daily-logs.index') }}" class="top-level-nav-item {{ Request::routeIs(['daily-logs.*', 'meals.*', 'ingredients.*']) ? 'active' : '' }}"><i class="fas fa-utensils"></i> Food</a>
            <a href="{{ route('workouts.index') }}" class="top-level-nav-item {{ Request::routeIs(['exercises.*', 'workouts.*']) ? 'active' : '' }}"><i class="fas fa-dumbbell"></i> Lifts</a>
            <a href="{{ route('measurement-logs.index') }}" class="top-level-nav-item {{ Request::routeIs(['measurement-logs.*', 'measurement-types.*']) ? 'active' : '' }}"><i class="fas fa-heartbeat"></i> Body</a>

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

        @if (Request::routeIs(['daily-logs.*', 'meals.*', 'ingredients.*', 'exercises.*', 'workouts.*', 'measurement-logs.*', 'measurement-types.*']))
        <div class="navbar sub-navbar">
            @if (Request::routeIs(['daily-logs.*', 'meals.*', 'ingredients.*']))
                <a href="{{ route('daily-logs.index') }}" class="{{ Request::routeIs('daily-logs.*') ? 'active' : '' }}">Daily Log</a>
                <a href="{{ route('meals.index') }}" class="{{ Request::routeIs('meals.*') ? 'active' : '' }}">Meals</a>
                <a href="{{ route('ingredients.index') }}" class="{{ Request::routeIs('ingredients.*') ? 'active' : '' }}">Ingredients</a>
            @endif

            @if (Request::routeIs(['exercises.*', 'workouts.*']))
                <a href="{{ route('workouts.index') }}" class="{{ Request::routeIs('workouts.*') ? 'active' : '' }}">Workouts</a>
                <a href="{{ route('exercises.index') }}" class="{{ Request::routeIs('exercises.*') ? 'active' : '' }}">Exercises</a>
            @endif

            @if (Request::routeIs(['measurement-logs.*', 'measurement-types.*']))
             <a href="{{ route('measurement-logs.index') }}" class="{{ Request::routeIs('measurement-logs.index') ? 'active' : '' }}">All</a>
                @foreach ($measurementTypes as $measurementType)
                    <a href="{{ route('measurement-logs.show-by-type', $measurementType) }}" class="{{ Request::is('measurement-logs/type/' . $measurementType->id) ? 'active' : '' }}">{{ $measurementType->name }}</a>
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
                @if(isset($gitLog))
                    <pre class="git-log">{{ $gitLog }}</pre>
                @endif
            </div>
        </footer>
        @endauth
    </body>
</html>
