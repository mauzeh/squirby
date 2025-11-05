<?php

namespace App\Services\ExerciseTypes;

use App\Models\LiftLog;
use App\Models\User;

interface ExerciseTypeInterface
{
    /**
     * Get validation rules for lift data based on exercise type
     */
    public function getValidationRules(?User $user = null): array;
    
    /**
     * Get form fields that should be displayed for this exercise type
     */
    public function getFormFields(): array;
    
    /**
     * Process lift data according to exercise type rules
     */
    public function processLiftData(array $data): array;
    
    /**
     * Process exercise data according to exercise type rules
     */
    public function processExerciseData(array $data): array;
    
    /**
     * Check if this exercise type supports 1RM calculation
     */
    public function canCalculate1RM(): bool;
    
    /**
     * Get the chart type appropriate for this exercise type
     */
    public function getChartType(): string;
    
    /**
     * Get supported progression types for this exercise type
     */
    public function getSupportedProgressionTypes(): array;
    
    /**
     * Format weight display for lift logs
     */
    public function formatWeightDisplay(LiftLog $liftLog): string;
    
    /**
     * Format 1RM display for lift logs
     */
    public function format1RMDisplay(LiftLog $liftLog): string;
    
    /**
     * Format progression suggestion for lift logs
     */
    public function formatProgressionSuggestion(LiftLog $liftLog): ?string;
    
    /**
     * Get the type name identifier
     */
    public function getTypeName(): string;
    
    /**
     * Get the configuration for this exercise type
     */
    public function getTypeConfig(): array;
}