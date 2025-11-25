<?php

namespace App\Services;

use App\Models\User;
use App\Services\ComponentBuilder as C;

class ProfileFormService
{
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
            ->checkboxField(
                'show_recommended_exercises',
                'Show recommended exercises',
                old('show_recommended_exercises', $user->show_recommended_exercises ?? true),
                'When enabled, the exercise selection list will show AI-recommended exercises at the top based on muscle balance, movement diversity, and recovery. When disabled, only recent exercises and alphabetical listing will be shown.'
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
}
