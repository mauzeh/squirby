<?php

namespace App\Services\Factories;

use App\Models\Exercise;
use App\Models\User;

class LiftLogFormFactory
{
    /**
     * Build the array of fields for a lift log form.
     *
     * @param Exercise $exercise The exercise for which to build the form.
     * @param User $user The user for whom the form is being built.
     * @param array $defaults Default values for the form fields (reps, sets, weight, etc.).
     * @param string $formId The base ID for the form, used to prefix field IDs.
     * @return array
     */
    public function buildFields(Exercise $exercise, User $user, array $defaults, string $formId): array
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
