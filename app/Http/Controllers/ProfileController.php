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
        $components[] = $this->profileFormService->generateProfilePhotoForm($user);
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
     * Display the connections page.
     */
    public function connections(Request $request): View
    {
        $user = $request->user();
        
        $components = [
            C::title('Connect', 'Share your code or scan a friend\'s code to connect')->build(),
        ];

        // Add session messages if any
        $sessionMessages = C::messagesFromSession();
        if ($sessionMessages) {
            $components[] = $sessionMessages;
        }

        // Add connection form
        $components[] = $this->profileFormService->generateConnectionForm($user);

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
        ]);

        $request->user()->update([
            'show_global_exercises' => $request->boolean('show_global_exercises'),
            'show_extra_weight' => $request->boolean('show_extra_weight'),
            'prefill_suggested_values' => $request->boolean('prefill_suggested_values'),
        ]);

        return Redirect::route('profile.edit')->with('success', 'Preferences updated successfully.');
    }

    /**
     * Update the user's profile photo.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_photo' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        $user = $request->user();

        // Delete old photo if exists
        if ($user->profile_photo_path) {
            \Storage::disk('public')->delete($user->profile_photo_path);
        }

        // Store new photo
        $path = $request->file('profile_photo')->store('profile-photos', 'public');

        $user->update([
            'profile_photo_path' => $path,
        ]);

        return Redirect::route('profile.edit')->with('success', 'Profile photo updated successfully.');
    }

    /**
     * Delete the user's profile photo.
     */
    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            \Storage::disk('public')->delete($user->profile_photo_path);
            
            $user->update([
                'profile_photo_path' => null,
            ]);
        }

        return Redirect::route('profile.edit')->with('success', 'Profile photo deleted successfully.');
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

    /**
     * Generate a new connection token for the user.
     */
    public function generateConnectionToken(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->generateConnectionToken();

        return Redirect::route('connections.index');
    }

    /**
     * Connect with another user via their connection token.
     */
    public function connectViaToken(Request $request, string $token): RedirectResponse
    {
        $currentUser = $request->user();

        // Find user by token
        $targetUser = \App\Models\User::findByConnectionToken($token);

        if (!$targetUser) {
            return Redirect::route('connections.index')->with('error', 'Invalid or expired connection code.');
        }

        if ($targetUser->id === $currentUser->id) {
            return Redirect::route('connections.index')->with('error', 'You cannot connect with yourself.');
        }

        // Create mutual follow
        $currentUser->follow($targetUser);
        $targetUser->follow($currentUser);

        return Redirect::route('connections.index')->with('success', "You're now connected with {$targetUser->name}!");
    }
}

