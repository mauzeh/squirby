<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            Exercise Preferences
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            Configure your exercise visibility and other preferences.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update-preferences') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="form-group-checkbox">
            <!-- Hidden input to ensure a value is always sent -->
            <input type="hidden" name="show_global_exercises" value="0" />
            <input 
                id="show_global_exercises" 
                name="show_global_exercises" 
                type="checkbox" 
                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                value="1"
                {{ old('show_global_exercises', $user->show_global_exercises ?? true) ? 'checked' : '' }}
            />
            <label for="show_global_exercises" class="font-medium text-gray-700">
                Show global exercises in mobile entry
            </label>
            @error('show_global_exercises')
                <div class="error-message">{{ $message }}</div>
            @enderror
        </div>
        <div style="margin-left: 140px; margin-top: -10px; margin-bottom: 20px;">
            <p class="text-gray-500 text-sm">
                When enabled, you'll see both your personal exercises and global exercises in the mobile lift entry interface. When disabled, only your personal exercises will be shown.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="button">Save Preferences</button>

            @if (session('status') === 'preferences-updated')
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="success-message-box mt-2"
                >Preferences saved.</div>
            @endif
        </div>
    </form>
</section>