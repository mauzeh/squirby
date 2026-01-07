{{-- Welcome Overlay Component for First-Time Users --}}
@if($data['show'] ?? false)
<div class="welcome-overlay" id="welcomeOverlay">
    <div class="welcome-overlay__backdrop" onclick="closeWelcomeOverlay()"></div>
    <div class="welcome-overlay__content">
        <div class="welcome-overlay__header">
            <h2 class="welcome-overlay__title">
                <span class="welcome-overlay__user-name">{{ $data['userName'] ?? 'Friend' }}<span class="welcome-overlay__comma">,</span></span><br>
                {{ $data['title'] ?? 'Let\'s Get Strong!' }}
            </h2>
            <button type="button" class="welcome-overlay__close" onclick="closeWelcomeOverlay()" aria-label="Close welcome message">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="welcome-overlay__body">
            <div class="welcome-overlay__icon">
                <i class="fas fa-dumbbell"></i>
            </div>
            
            <p class="welcome-overlay__message">
                {{ $data['message'] ?? 'Congratulations on signing up! This is where you\'ll track your workouts and watch your strength grow over time.' }}
            </p>
            
            <div class="welcome-overlay__features">
                <div class="welcome-feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Track your progress</span>
                </div>
                <div class="welcome-feature">
                    <i class="fas fa-trophy"></i>
                    <span>Celebrate PRs</span>
                </div>
                <div class="welcome-feature">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Log daily workouts</span>
                </div>
            </div>
        </div>
        
        <div class="welcome-overlay__footer">
            <button type="button" class="welcome-overlay__cta" onclick="closeWelcomeOverlay()">
                {{ $data['ctaText'] ?? 'Start Logging Now!' }}
            </button>
        </div>
    </div>
</div>
@endif