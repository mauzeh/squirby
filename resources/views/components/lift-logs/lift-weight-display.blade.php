@props(['liftLog'])

<span style="font-weight: bold; font-size: 1.2em;">
    {{ $liftLog->exercise->getTypeStrategy()->formatWeightDisplay($liftLog) }}
</span>
