<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileFormService;
use App\Services\ComponentBuilder as C;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    protected ProfileFormService $profileFormService;

    public function __construct(ProfileFormService $profileFormService)
    {
        $this->profileFormService = $profileFormService;
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        
        $components = [
            C::title('Profile', 'Manage your account settings and preferences')->build(),
        ];

        // Add session messages if any
        $sessionMessages = C::messagesFromSession();
        if ($sessionMessages) {
            $components[] = $sessionMessages;
        }

        // Add form components
        $components[] = $this->profileFormService->generateProfileInformationForm($user);
        $components[] = $this->profileFormService->generatePreferencesForm($user);
        $components[] = $this->profileFormService->generatePasswordForm();
        $components[] = $this->profileFormService->generateDeleteAccountForm();

        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components,
            ]
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('success', 'Profile information updated successfully.');
    }

    /**
     * Update the user's preferences.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $request->validate([
            'show_global_exercises' => ['nullable', 'boolean'],
            'show_extra_weight' => ['nullable', 'boolean'],
            'prefill_suggested_values' => ['nullable', 'boolean'],
            'metrics_first_logging_flow' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'show_global_exercises' => $request->boolean('show_global_exercises'),
            'show_extra_weight' => $request->boolean('show_extra_weight'),
            'prefill_suggested_values' => $request->boolean('prefill_suggested_values'),
            'metrics_first_logging_flow' => $request->boolean('metrics_first_logging_flow'),
        ]);

        return Redirect::route('profile.edit')->with('success', 'Preferences updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
