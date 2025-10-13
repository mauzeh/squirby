<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class TransformationConfig
{
    public int $durationWeeks = 12;
    public float $startingWeight = 180.0;
    public float $targetWeight = 165.0;
    public float $startingWaist = 36.0;
    public string $programType = 'strength'; // strength, powerlifting, bodybuilding
    public ?User $user = null;
    public Carbon $startDate;
    public bool $includeVariations = true;
    public float $missedWorkoutRate = 0.05;

    public function __construct()
    {
        // Calculate start date so that the transformation ends 7 days from today
        // This ensures the data window always includes today and spans 7 days into the future
        $endDate = Carbon::now()->addDays(7);
        $this->startDate = $endDate->copy()->subWeeks($this->durationWeeks);
    }

    /**
     * Set the transformation duration in weeks
     */
    public function setDuration(int $weeks): self
    {
        $this->durationWeeks = $weeks;
        return $this;
    }

    /**
     * Set starting body metrics
     */
    public function setStartingMetrics(float $weight, float $waist): self
    {
        $this->startingWeight = $weight;
        $this->startingWaist = $waist;
        return $this;
    }

    /**
     * Set target weight
     */
    public function setTargetWeight(float $weight): self
    {
        $this->targetWeight = $weight;
        return $this;
    }

    /**
     * Set program type
     */
    public function setProgramType(string $type): self
    {
        $validTypes = ['strength', 'powerlifting', 'bodybuilding'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Program type must be one of: " . implode(', ', $validTypes));
        }
        $this->programType = $type;
        return $this;
    }

    /**
     * Set the user for this transformation
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set the start date for the transformation
     */
    public function setStartDate(Carbon $date): self
    {
        $this->startDate = $date;
        return $this;
    }

    /**
     * Enable or disable realistic variations
     */
    public function setIncludeVariations(bool $include): self
    {
        $this->includeVariations = $include;
        return $this;
    }

    /**
     * Set the rate of missed workouts (0.0 to 1.0)
     */
    public function setMissedWorkoutRate(float $rate): self
    {
        if ($rate < 0.0 || $rate > 1.0) {
            throw new \InvalidArgumentException("Missed workout rate must be between 0.0 and 1.0");
        }
        $this->missedWorkoutRate = $rate;
        return $this;
    }

    /**
     * Get the end date of the transformation
     */
    public function getEndDate(): Carbon
    {
        return $this->startDate->copy()->addWeeks($this->durationWeeks);
    }

    /**
     * Get total transformation days
     */
    public function getTotalDays(): int
    {
        return $this->durationWeeks * 7;
    }

    /**
     * Get expected weight loss per week
     */
    public function getWeeklyWeightLoss(): float
    {
        return ($this->startingWeight - $this->targetWeight) / $this->durationWeeks;
    }

    /**
     * Validate the configuration
     */
    public function validate(): bool
    {
        if ($this->durationWeeks <= 0) {
            throw new \InvalidArgumentException("Duration must be greater than 0 weeks");
        }

        if ($this->startingWeight <= $this->targetWeight) {
            throw new \InvalidArgumentException("Starting weight must be greater than target weight for weight loss");
        }

        if ($this->startingWaist <= 0) {
            throw new \InvalidArgumentException("Starting waist measurement must be positive");
        }

        return true;
    }
}