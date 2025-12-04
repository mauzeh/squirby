{{-- Markdown Component --}}
@php
    use League\CommonMark\Environment\Environment;
    use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
    use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
    use League\CommonMark\MarkdownConverter;
    
    $environment = new Environment([]);
    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new GithubFlavoredMarkdownExtension());
    $converter = new MarkdownConverter($environment);
    
    $markdown = $data['markdown'] ?? '';
    $html = $converter->convert($markdown)->getContent();
@endphp

<div class="component-markdown {{ $data['classes'] ?? '' }}">
    <div class="prose prose-sm max-w-none bg-gray-50 p-4 rounded border border-gray-200">
        {!! $html !!}
    </div>
</div>
