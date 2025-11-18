<?php

namespace App\Services\Factories;

use App\Models\Exercise;
use App\Models\User;
use App\Models\MobileLiftForm;
use Carbon\Carbon;
use App\Services\ComponentBuilder as C;

class LiftLogFormFactory
{
    /**
     * Build a complete lift log form component.
     *
     * @param MobileLiftForm $mobileLiftForm The mobile lift form record
     * @param Exercise $exercise The exercise for which to build the form
     * @param User $user The user for whom the form is being built
     * @param array $defaults Default values for the form fields (reps, sets, weight, etc.)
     * @param array $messages Messages to display in the form
     * @param Carbon $selectedDate The date for the form
     * @param array $redirectParams Optional redirect parameters
     * @return array Complete form component data
     */
    public function buildForm(
        MobileLiftForm $mobileLiftForm,
        Exercise $exercise,
        User $user,
        array $defaults,
        array $messages,
        Carbon $selectedDate,
        array $redirectParams = []
    ): array {
        $formId = 'lift-' . $mobileLiftForm->id;
        
        // Build the fields
        $numericFields = $this->buildFields($exercise, $user, $defaults, $formId);
        
        // Build form using ComponentBuilder
        $formBuilder = C::form($formId, $exercise->title)
            ->type('primary')
            ->formAction(route('lift-logs.store'))
            ->deleteAction(route('mobile-entry.remove-form', ['id' => $formId]))
            ->hiddenField('exercise_id', $exercise->id)
            ->hiddenField('date', $selectedDate->format('Y-m-d'))
            ->hiddenField('logged_at', now()->format('H:i'));

        foreach ($redirectParams as $name => $value) {
            $formBuilder->hiddenField($name, $value);
        }
        
        // Add messages
        foreach ($messages as $message) {
            $formBuilder->message($message['type'], $message['text'], $message['prefix'] ?? null);
        }
        
        // Build the form data
        $formData = $formBuilder->build();
        $formData['data']['numericFields'] = $numericFields;
        $formData['data']['buttons'] = [
            'decrement' => '-',
            'increment' => '+',
            'submit' => 'Log ' . $exercise->title
        ];
        $formData['data']['ariaLabels'] = [
            'section' => $exercise->title . ' entry',
            'deleteForm' => 'Remove this exercise form'
        ];
        
        // Build hidden fields with redirect params if provided
        $hiddenFields = [
            'exercise_id' => $exercise->id,
            'date' => $selectedDate->toDateString(),
            'mobile_lift_form_id' => $mobileLiftForm->id
        ];
        
        // Add redirect parameters if they exist, otherwise default to mobile-entry-lifts
        if (!empty($redirectParams['redirect_to'])) {
            $hiddenFields['redirect_to'] = $redirectParams['redirect_to'];
            
            // Add template_id if it exists
            if (!empty($redirectParams['template_id'])) {
                $hiddenFields['template_id'] = $redirectParams['template_id'];
            }
            
            // Add workout_id if it exists
            if (!empty($redirectParams['workout_id'])) {
                $hiddenFields['workout_id'] = $redirectParams['workout_id'];
            }
        } else {
            $hiddenFields['redirect_to'] = 'mobile-entry-lifts';
        }
        
        $formData['data']['hiddenFields'] = $hiddenFields;
        $formData['data']['deleteParams'] = [
            'date' => $selectedDate->toDateString()
        ];
        
        return $formData;
    }

    /**
     * Build the array of fields for a lift log form.
     *
     * @param Exercise $exercise The exercise for which to build the form.
     * @param User $user The user for whom the form is being built.
     * @param array $defaults Default values for the form fields (reps, sets, weight, etc.).
     * @param string $formId The base ID for the form, used to prefix field IDs.
     * @return array
     */
    private function buildFields(Exercise $exercise, User $user, array $defaults, string $formId): array
    {
        $strategy = $exercise->getTypeStrategy();
        $fieldDefinitions = $strategy->getFormFieldDefinitions($defaults, $user);

        $fields = [];

        // Convert strategy field definitions into the flexible form field format
        foreach ($fieldDefinitions as $definition) {
            $field = [
                'id' => $formId . '-' . $definition['name'],
                'name' => $definition['name'],
                'label' => $definition['label'],
                'type' => $definition['type'],
                'defaultValue' => $definition['defaultValue'],
            ];

            if ($definition['type'] === 'numeric') {
                $field['increment'] = $definition['increment'];
                $field['min'] = $definition['min'];
                $field['max'] = $definition['max'] ?? 1000;
                $fieldNameForAria = $definition['name'] === 'reps' && $strategy->getTypeName() === 'cardio' ? 'distance' : $definition['name'];
                $field['ariaLabels'] = [
                    'decrease' => 'Decrease ' . $fieldNameForAria,
                    'increase' => 'Increase ' . $fieldNameForAria,
                ];
            } elseif ($definition['type'] === 'select') {
                $field['options'] = $definition['options'];
                $field['ariaLabels'] = [
                    'field' => 'Select ' . strtolower(trim($definition['label'], ':'))
                ];
            }
            
            $fields[] = $field;
        }

        // Manually add the fields not covered by the strategy
        $labels = $strategy->getFieldLabels();
        $setsLabel = $labels['sets'] ?? 'Sets:';
        $fields[] = [
            'id' => $formId . '-rounds',
            'name' => 'rounds',
            'label' => $setsLabel,
            'type' => 'numeric',
            'defaultValue' => $defaults['sets'] ?? 3,
            'increment' => 1,
            'min' => 1,
            'ariaLabels' => [
                'decrease' => 'Decrease ' . strtolower(trim($setsLabel, ':')),
                'increase' => 'Increase ' . strtolower(trim($setsLabel, ':')),
            ],
        ];

                $fields[] = [
                    'id' => $formId . '-comments',
                    'name' => 'comments',
                    'label' => 'Notes:',
                    'type' => 'textarea',
                    'defaultValue' => $defaults['comments'] ?? '',
                    'placeholder' => 'Add any notes about your workout...', 
                    'ariaLabels' => [
                        'field' => 'Notes'
                    ]
                ];
        return $fields;
    }
}
