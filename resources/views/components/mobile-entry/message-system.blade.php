{{--
Mobile Entry Message System Component

Displays error, success, and validation messages with auto-hide functionality.
Consolidates identical message markup from lift-logs and food-logs templates.

@param Collection|null $errors - Laravel validation errors collection
@param string|null $success - Success message from session
@param bool $showValidation - Whether to show client-side validation container (default: true)
--}}

@props([
    'errors' => null,
    'success' => null,
    'showValidation' => true
])

{{-- Server-side error messages --}}
@if(session('error'))
    <div class="message-container message-error" id="error-message">
        <div class="message-content">
            <span class="message-text">{{ session('error') }}</span>
            <button type="button" class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
        </div>
    </div>
@endif

{{-- Server-side success messages --}}
@if(session('success') || $success)
    <div class="message-container message-success" id="success-message">
        <div class="message-content">
            <span class="message-text">{{ session('success') ?? $success }}</span>
            <button type="button" class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
        </div>
    </div>
@endif

{{-- Laravel validation errors --}}
@if($errors && $errors->any())
    <div class="message-container message-error" id="validation-error-message">
        <div class="message-content">
            <span class="message-text">
                @if($errors->count() === 1)
                    {{ $errors->first() }}
                @else
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </span>
            <button type="button" class="message-close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
        </div>
    </div>
@endif

{{-- Client-side validation error display --}}
@if($showValidation)
    <div id="validation-errors" class="message-container message-validation hidden">
        <div class="message-content">
            <span class="message-text"></span>
            <button type="button" class="message-close" onclick="document.getElementById('validation-errors').classList.add('hidden')">&times;</button>
        </div>
    </div>
@endif

{{-- Auto-hide functionality script --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide success/error messages after 5 seconds
    setTimeout(function() {
        const errorMessage = document.getElementById('error-message');
        const successMessage = document.getElementById('success-message');
        const validationErrorMessage = document.getElementById('validation-error-message');
        
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
        if (successMessage) {
            successMessage.style.display = 'none';
        }
        if (validationErrorMessage) {
            validationErrorMessage.style.display = 'none';
        }
    }, 5000);
});

// Global functions for client-side validation messages
window.showValidationError = function(message) {
    const errorContainer = document.getElementById('validation-errors');
    if (errorContainer) {
        const errorText = errorContainer.querySelector('.message-text');
        
        errorText.textContent = message;
        errorContainer.classList.remove('hidden');
        
        // Scroll to error message
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

window.hideValidationError = function() {
    const errorContainer = document.getElementById('validation-errors');
    if (errorContainer) {
        errorContainer.classList.add('hidden');
    }
};
</script>