<?php

namespace App\Services;

use App\Models\User;
use App\Services\ComponentBuilder as C;

class ProfileFormService
{
    /**
     * Generate profile photo upload form component
     */
    public function generateProfilePhotoForm(User $user): array
    {
        // Show current photo or placeholder
        $photoHtml = '';
        if ($user->profile_photo_url) {
            $photoHtml = '<div class="profile-photo-preview"><img src="' . e($user->profile_photo_url) . '" alt="Profile photo" class="profile-photo-img"></div>';
        } else {
            $photoHtml = '<div class="profile-photo-preview"><i class="fas fa-user-circle profile-photo-placeholder"></i></div>';
        }
        
        // Upload form
        $uploadForm = C::form('profile-photo-upload', 'Profile Photo')
            ->formAction(route('profile.update-photo'))
            ->hiddenField('_method', 'POST')
            ->message('info', 'Upload a profile photo (JPG, PNG, max 2MB)')
            ->fileField('profile_photo', 'Choose Photo')
            ->submitButton('Upload Photo');
        
        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->has('profile_photo')) {
                $uploadForm->message('error', $errors->first('profile_photo'));
            }
        }
        
        $uploadFormHtml = view('mobile-entry.components.form', ['data' => $uploadForm->build()['data']])->render();
        
        // Delete photo button if photo exists
        $deleteFormHtml = '';
        if ($user->profile_photo_path) {
            $deleteForm = C::form('profile-photo-delete')
                ->formAction(route('profile.delete-photo'))
                ->hiddenField('_method', 'DELETE')
                ->submitButton('Remove Photo')
                ->submitButtonClass('btn-secondary')
                ->confirmMessage('Are you sure you want to remove your profile photo?');
            
            $deleteFormHtml = view('mobile-entry.components.form', ['data' => $deleteForm->build()['data']])->render();
        }
        
        return [
            'type' => 'raw_html',
            'data' => [
                'html' => '<div class="profile-photo-section">' . 
                    $photoHtml . 
                    $uploadFormHtml . 
                    $deleteFormHtml . 
                    '</div>'
            ]
        ];
    }

    /**
     * Generate profile information form component
     */
    public function generateProfileInformationForm(User $user): array
    {
        $form = C::form('profile-information', 'Profile Information')
            ->formAction(route('profile.update'))
            ->hiddenField('_method', 'PATCH')
            ->textField('name', 'Name', old('name', $user->name))
            ->textField('email', 'Email', old('email', $user->email))
            ->submitButton('Save');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->has('name')) {
                $form->message('error', $errors->first('name'));
            }
            if ($errors->has('email')) {
                $form->message('error', $errors->first('email'));
            }
        }

        return $form->build();
    }

    /**
     * Generate preferences form component
     */
    public function generatePreferencesForm(User $user): array
    {
        $form = C::form('preferences', 'Exercise Preferences')
            ->formAction(route('profile.update-preferences'))
            ->hiddenField('_method', 'PATCH')
            ->checkboxField(
                'show_global_exercises',
                'Show global exercises',
                old('show_global_exercises', $user->show_global_exercises ?? true),
                "When enabled, you'll see both your personal exercises and global exercises throughout the app. When disabled, only your personal exercises will be shown (except for global exercises you've already logged, which remain visible)."
            )
            ->checkboxField(
                'show_extra_weight',
                'Show extra weight field for bodyweight exercises',
                old('show_extra_weight', $user->show_extra_weight ?? false),
                'When enabled, bodyweight exercises will show the "Add additional weight" button and extra weight field in the mobile lift entry interface. When disabled, only reps and sets will be shown.'
            )
            ->checkboxField(
                'prefill_suggested_values',
                'Prefill suggested progression values',
                old('prefill_suggested_values', $user->prefill_suggested_values ?? true),
                'When enabled, the lift log form will prefill with AI-suggested weight, reps, and sets based on your training progression. When disabled, the form will prefill with values from your last workout only.'
            )
            ->submitButton('Save Preferences');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->default) {
                foreach ($errors->default->all() as $error) {
                    $form->message('error', $error);
                }
            }
        }

        return $form->build();
    }

    /**
     * Generate password update form component
     */
    public function generatePasswordForm(): array
    {
        $form = C::form('update-password', 'Update Password')
            ->formAction(route('password.update'))
            ->hiddenField('_method', 'PUT')
            ->passwordField('current_password', 'Current Password')
            ->passwordField('password', 'New Password')
            ->passwordField('password_confirmation', 'Confirm Password')
            ->submitButton('Save');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->updatePassword) {
                foreach ($errors->updatePassword->all() as $error) {
                    $form->message('error', $error);
                }
            }
        }

        return $form->build();
    }

    /**
     * Generate delete account form component
     */
    public function generateDeleteAccountForm(): array
    {
        $form = C::form('delete-account', 'Delete Account')
            ->formAction(route('profile.destroy'))
            ->hiddenField('_method', 'DELETE')
            ->message('warning', 'Once your account is deleted, all of its resources and data will be permanently deleted.')
            ->passwordField('password', 'Confirm Password')
            ->submitButton('Delete Account')
            ->submitButtonClass('btn-danger')
            ->confirmMessage('Are you sure you want to delete your account? This action cannot be undone.');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->userDeletion) {
                foreach ($errors->userDeletion->all() as $error) {
                    $form->message('error', $error);
                }
            }
        }

        return $form->build();
    }

    /**
     * Generate connection form component with QR code and 6-digit code
     */
    public function generateConnectionForm(User $user): array
        {
            // Get or generate a valid connection token
            $token = $user->getValidConnectionToken();
            $expiresAt = $user->connection_token_expires_at;

            // Generate QR code URL (using a simple QR code API)
            $connectUrl = route('profile.connect-via-token', ['token' => $token]);
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($connectUrl);

            // Calculate time remaining
            $minutesRemaining = $expiresAt ? max(0, (int) ceil(now()->diffInMinutes($expiresAt, false))) : 0;

            return C::connection($token, $expiresAt, $minutesRemaining)
                ->qrCodeUrl($qrCodeUrl)
                ->generateTokenRoute(route('profile.generate-connection-token'))
                ->connectRoute('/connect/__TOKEN__')
                ->build();
        }

}
