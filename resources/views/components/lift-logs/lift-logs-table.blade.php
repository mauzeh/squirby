@props(['liftLogs', 'config'])

<table class="log-entries-table">
    <x-lift-logs.lift-logs-table-header :config="$config" />
    <x-lift-logs.lift-logs-table-body :liftLogs="$liftLogs" :config="$config" />
    <x-lift-logs.lift-logs-table-footer :config="$config" />
</table>

<x-bulk-selection-controls :config="$config" />