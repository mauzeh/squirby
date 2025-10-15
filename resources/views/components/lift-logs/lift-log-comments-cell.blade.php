@props(['liftLog'])

<td class="hide-on-mobile comments-column" title="{{ $liftLog['full_comments'] }}">
    {{ $liftLog['truncated_comments'] }}
</td>