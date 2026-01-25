<?php

namespace App\Services\LiftLogTableRowBuilder;

/**
 * Configuration DTO for row building
 * Provides type-safe access to row configuration options
 */
class RowConfig
{
    public function __construct(
        public readonly bool $showDateBadge = true,
        public readonly bool $showCheckbox = false,
        public readonly bool $showViewLogsAction = true,
        public readonly bool $showDeleteAction = false,
        public readonly bool $wrapActions = true,
        public readonly bool $showPRRecordsTable = false,
        public readonly ?string $redirectContext = null,
        public readonly ?string $selectedDate = null,
        public readonly bool $includeEncouragingMessage = false,
    ) {}
    
    /**
     * Create RowConfig from array
     */
    public static function fromArray(array $config): self
    {
        return new self(
            showDateBadge: $config['showDateBadge'] ?? true,
            showCheckbox: $config['showCheckbox'] ?? false,
            showViewLogsAction: $config['showViewLogsAction'] ?? true,
            showDeleteAction: $config['showDeleteAction'] ?? false,
            wrapActions: $config['wrapActions'] ?? true,
            showPRRecordsTable: $config['showPRRecordsTable'] ?? false,
            redirectContext: $config['redirectContext'] ?? null,
            selectedDate: $config['selectedDate'] ?? null,
            includeEncouragingMessage: $config['includeEncouragingMessage'] ?? false,
        );
    }
}
