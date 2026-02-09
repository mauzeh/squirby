<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ComponentBuilder as C;
use App\Services\ExerciseTypes\ExerciseTypeFactory;

class ExerciseFormService
{
    /**
     * Generate exercise form component for create/edit
     */
    public function generateExerciseForm(Exercise $exercise, User $user, string $action, string $method = 'POST'): array
    {
        $isEdit = $exercise->exists;
        $canCreateGlobal = $user->hasRole('Admin');
        
        $form = C::form('exercise-form', $isEdit ? 'Edit Exercise' : 'Create Exercise')
            ->formAction($action);
            
        // Add method override for PUT requests
        if ($method !== 'POST') {
            $form->hiddenField('_method', $method);
        }
        
        // Title field
        $form->textField(
            'title',
            'Exercise Name',
            old('title', $exercise->title ?? ''),
            'Enter the exercise name'
        );
        
        // Description field
        $form->textareaField(
            'description',
            'Description',
            old('description', $exercise->description ?? ''),
            'Optional description of the exercise'
        );
        
        // Exercise type field
        $exerciseTypes = $this->getExerciseTypeOptions();
        $form->selectField(
            'exercise_type',
            'Exercise Type',
            $exerciseTypes,
            old('exercise_type', $exercise->exercise_type ?? '')
        );
        
        // Show in feed checkbox
        $form->checkboxField(
            'show_in_feed',
            'Show in PR Feed',
            old('show_in_feed', $exercise->show_in_feed ?? false),
            'When enabled, PRs for this exercise will appear in the social feed'
        );
        
        // Submit button
        $form->submitButton($isEdit ? 'Update Exercise' : 'Create Exercise');
        
        // Add validation error messages
        $this->addValidationErrors($form);
        
        return $form->build();
    }
    
    /**
     * Get exercise type options for select field
     */
    private function getExerciseTypeOptions(): array
    {
        $options = [];
        $typeConfigs = config('exercise_types.types', []);
        
        // Add empty option
        $options[] = [
            'value' => '',
            'label' => 'Select Exercise Type'
        ];
        
        foreach ($typeConfigs as $key => $config) {
            // Skip deprecated types
            if (isset($config['deprecated']) && $config['deprecated']) {
                continue;
            }
            
            $options[] = [
                'value' => $key,
                'label' => $this->getExerciseTypeLabel($key)
            ];
        }
        
        return $options;
    }
    
    /**
     * Get human-readable label for exercise type
     */
    private function getExerciseTypeLabel(string $type): string
    {
        $labels = [
            'regular' => 'Regular (Weighted)',
            'bodyweight' => 'Bodyweight',
            'banded_resistance' => 'Banded Resistance',
            'banded_assistance' => 'Banded Assistance',
            'cardio' => 'Cardio'
        ];
        
        return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * Add validation error messages to the form
     */
    private function addValidationErrors($form): void
    {
        if ($errors = session('errors')) {
            if ($errors->has('title')) {
                $form->message('error', $errors->first('title'));
            }
            if ($errors->has('description')) {
                $form->message('error', $errors->first('description'));
            }
            if ($errors->has('exercise_type')) {
                $form->message('error', $errors->first('exercise_type'));
            }
            if ($errors->has('show_in_feed')) {
                $form->message('error', $errors->first('show_in_feed'));
            }
        }
    }
}