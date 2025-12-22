<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Services\ComponentBuilder as C;

class UserFormService
{
    /**
     * Generate user information form component
     */
    public function generateUserInformationForm(User $user, $roles): array
    {
        // Get selected role IDs
        $selectedRoleIds = $user->roles->pluck('id')->toArray();

        $form = C::form('user-information', 'User Information')
            ->formAction(route('users.update', $user->id))
            ->hiddenField('_method', 'PUT')
            ->textField('name', 'Name', old('name', $user->name))
            ->textField('email', 'Email', old('email', $user->email));

        // Add a checkbox for each role
        foreach ($roles as $role) {
            $isSelected = in_array($role->id, old('roles', $selectedRoleIds));
            $form->checkboxArrayField(
                'roles[]',
                $role->name,
                $role->id,
                $isSelected
            );
        }

        $form->submitButton('Update User');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->has('name')) {
                $form->message('error', $errors->first('name'));
            }
            if ($errors->has('email')) {
                $form->message('error', $errors->first('email'));
            }
            if ($errors->has('roles')) {
                $form->message('error', $errors->first('roles'));
            }
        }

        return $form->build();
    }

    /**
     * Generate password update form component
     */
    public function generatePasswordForm(User $user): array
    {
        $form = C::form('update-password', 'Update Password')
            ->formAction(route('users.update', $user->id))
            ->hiddenField('_method', 'PUT')
            ->passwordField('password', 'New Password')
            ->passwordField('password_confirmation', 'Confirm Password')
            ->submitButton('Update Password');

        // Add error messages if validation failed
        if ($errors = session('errors')) {
            if ($errors->has('password')) {
                $form->message('error', $errors->first('password'));
            }
            if ($errors->has('password_confirmation')) {
                $form->message('error', $errors->first('password_confirmation'));
            }
        }

        return $form->build();
    }

    /**
     * Generate delete user form component
     */
    public function generateDeleteUserForm(User $user): array
    {
        $form = C::form('delete-user', 'Delete User')
            ->formAction(route('users.destroy', $user->id))
            ->hiddenField('_method', 'DELETE')
            ->message('warning', 'This will deactivate the user account and hide all their data. An admin can restore the account if needed.')
            ->submitButton('Delete User')
            ->submitButtonClass('btn-danger')
            ->confirmMessage('Are you sure you want to delete this user account?');

        return $form->build();
    }
}