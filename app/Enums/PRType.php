<?php

namespace App\Enums;

enum PRType: int
{
    case NONE = 0;
    case ONE_RM = 1;
    case REP_SPECIFIC = 2;
    case VOLUME = 4;
    case DENSITY = 8;
    case TIME = 16;
    case ENDURANCE = 32;

    /**
     * Check if a flags value contains this PR type
     */
    public function isIn(int $flags): bool
    {
        return ($flags & $this->value) === $this->value;
    }

    /**
     * Get a human-readable label for display
     */
    public function getLabel(): string
    {
        return match($this) {
            self::ONE_RM => '🎉 NEW PR!',
            self::REP_SPECIFIC => '🏆 Rep PR!',
            self::VOLUME => '💪 Volume PR!',
            self::DENSITY => '⚡ Density PR!',
            self::TIME => '⏱️ Time PR!',
            self::ENDURANCE => '🔥 Endurance PR!',
            self::NONE => '',
        };
    }

    /**
     * Get the best label from a flags value (prioritized)
     * Returns the highest priority PR type label
     */
    public static function getBestLabel(int $flags): string
    {
        if ($flags === 0) {
            return '';
        }

        // Priority order for display (most impressive first)
        $priority = [
            self::ONE_RM,
            self::REP_SPECIFIC,
            self::VOLUME,
            self::DENSITY,
            self::TIME,
            self::ENDURANCE,
        ];

        foreach ($priority as $type) {
            if ($type->isIn($flags)) {
                return $type->getLabel();
            }
        }

        return '🎉 NEW PR!';
    }

    /**
     * Get all PR types from a flags value
     */
    public static function getTypes(int $flags): array
    {
        if ($flags === 0) {
            return [];
        }

        $types = [];
        foreach (self::cases() as $case) {
            if ($case !== self::NONE && $case->isIn($flags)) {
                $types[] = $case;
            }
        }

        return $types;
    }

    /**
     * Check if flags value represents any PR
     * This allows integer flags to be used in boolean context
     */
    public static function isPR(int $flags): bool
    {
        return $flags > 0;
    }

    /**
     * Combine multiple PR types into flags
     */
    public static function combine(PRType ...$types): int
    {
        $flags = 0;
        foreach ($types as $type) {
            $flags |= $type->value;
        }
        return $flags;
    }

    /**
     * Helper to check if a specific type exists in flags
     */
    public static function hasType(int $flags, PRType $type): bool
    {
        return $type->isIn($flags);
    }
}
