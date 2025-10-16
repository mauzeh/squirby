<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciseIntelligenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('Admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'muscle_data' => 'required|array',
            'muscle_data.muscles' => 'required|array|min:1',
            'muscle_data.muscles.*.name' => 'required|string|in:' . implode(',', $this->getValidMuscleNames()),
            'muscle_data.muscles.*.role' => 'required|string|in:primary_mover,synergist,stabilizer',
            'muscle_data.muscles.*.contraction_type' => 'required|string|in:isotonic,isometric',
            'primary_mover' => [
                'required',
                'string',
                'in:' . implode(',', $this->getValidMuscleNames()),
                function ($attribute, $value, $fail) {
                    // Validate that primary_mover exists in muscle_data with role 'primary_mover'
                    $muscles = $this->input('muscle_data.muscles', []);
                    $primaryMovers = collect($muscles)->where('role', 'primary_mover')->pluck('name')->toArray();
                    
                    if (!in_array($value, $primaryMovers)) {
                        $fail('The primary mover must be one of the muscles with role "primary_mover" in the muscle data.');
                    }
                }
            ],
            'largest_muscle' => [
                'required',
                'string',
                'in:' . implode(',', $this->getValidMuscleNames()),
                function ($attribute, $value, $fail) {
                    // Validate that largest_muscle exists in muscle_data
                    $muscles = $this->input('muscle_data.muscles', []);
                    $muscleNames = collect($muscles)->pluck('name')->toArray();
                    
                    if (!in_array($value, $muscleNames)) {
                        $fail('The largest muscle must be one of the muscles in the muscle data.');
                    }
                }
            ],
            'movement_archetype' => 'required|string|in:push,pull,squat,hinge,carry,core',
            'category' => 'required|string|in:strength,cardio,mobility,plyometric,flexibility',
            'difficulty_level' => 'required|integer|min:1|max:5',
            'recovery_hours' => 'required|integer|min:0|max:168', // Max 1 week (168 hours)
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'muscle_data.required' => 'Muscle data is required.',
            'muscle_data.muscles.required' => 'At least one muscle must be specified.',
            'muscle_data.muscles.min' => 'At least one muscle must be specified.',
            'muscle_data.muscles.*.name.required' => 'Each muscle must have a name.',
            'muscle_data.muscles.*.name.in' => 'Invalid muscle name. Please select from the predefined list.',
            'muscle_data.muscles.*.role.required' => 'Each muscle must have a role specified.',
            'muscle_data.muscles.*.role.in' => 'Muscle role must be one of: primary_mover, synergist, stabilizer.',
            'muscle_data.muscles.*.contraction_type.required' => 'Each muscle must have a contraction type specified.',
            'muscle_data.muscles.*.contraction_type.in' => 'Contraction type must be either isotonic or isometric.',
            'primary_mover.required' => 'Primary mover muscle is required.',
            'primary_mover.in' => 'Invalid primary mover muscle name.',
            'largest_muscle.required' => 'Largest muscle is required.',
            'largest_muscle.in' => 'Invalid largest muscle name.',
            'movement_archetype.required' => 'Movement archetype is required.',
            'movement_archetype.in' => 'Movement archetype must be one of: push, pull, squat, hinge, carry, core.',
            'category.required' => 'Exercise category is required.',
            'category.in' => 'Category must be one of: strength, cardio, mobility, plyometric, flexibility.',
            'difficulty_level.required' => 'Difficulty level is required.',
            'difficulty_level.integer' => 'Difficulty level must be a number.',
            'difficulty_level.min' => 'Difficulty level must be at least 1.',
            'difficulty_level.max' => 'Difficulty level must be at most 5.',
            'recovery_hours.required' => 'Recovery hours is required.',
            'recovery_hours.integer' => 'Recovery hours must be a number.',
            'recovery_hours.min' => 'Recovery hours cannot be negative.',
            'recovery_hours.max' => 'Recovery hours cannot exceed 168 (1 week).',
        ];
    }

    /**
     * Get list of valid muscle names for validation.
     */
    private function getValidMuscleNames(): array
    {
        return [
            // Upper Body - Chest
            'pectoralis_major',
            'pectoralis_minor',
            
            // Upper Body - Back
            'latissimus_dorsi',
            'rhomboids',
            'middle_trapezius',
            'lower_trapezius',
            'upper_trapezius',
            
            // Upper Body - Shoulders
            'anterior_deltoid',
            'medial_deltoid',
            'posterior_deltoid',
            
            // Upper Body - Arms
            'biceps_brachii',
            'triceps_brachii',
            'brachialis',
            'brachioradialis',
            
            // Lower Body - Quadriceps
            'rectus_femoris',
            'vastus_lateralis',
            'vastus_medialis',
            'vastus_intermedius',
            
            // Lower Body - Hamstrings
            'biceps_femoris',
            'semitendinosus',
            'semimembranosus',
            
            // Lower Body - Glutes
            'gluteus_maximus',
            'gluteus_medius',
            'gluteus_minimus',
            
            // Lower Body - Calves
            'gastrocnemius',
            'soleus',
            
            // Core - Abdominals
            'rectus_abdominis',
            'external_obliques',
            'internal_obliques',
            'transverse_abdominis',
            
            // Core - Lower Back
            'erector_spinae',
            'multifidus',
        ];
    }
}