{{-- Code Editor Component --}}
<div class="component-code-editor" data-editor-id="{{ $data['id'] }}">
    <div class="code-editor-header">
        <label for="{{ $data['id'] }}-textarea" class="code-editor-label">
            {{ $data['label'] }}
        </label>
    </div>
    
    <div class="code-editor-wrapper" style="height: {{ $data['height'] }};">
        {{-- Hidden textarea for form submission and no-JS fallback --}}
        <textarea 
            id="{{ $data['id'] }}-textarea" 
            name="{{ $data['name'] }}" 
            class="code-editor-textarea"
            placeholder="{{ $data['placeholder'] }}"
            aria-label="{{ $data['ariaLabel'] }}"
            data-mode="{{ $data['mode'] }}"
            data-theme="{{ $data['theme'] }}"
            data-line-numbers="{{ $data['lineNumbers'] ? 'true' : 'false' }}"
            data-line-wrapping="{{ $data['lineWrapping'] ? 'true' : 'false' }}"
            data-read-only="{{ $data['readOnly'] ? 'true' : 'false' }}"
            data-autofocus="{{ $data['autofocus'] ? 'true' : 'false' }}"
        >{{ old($data['name'], $data['value']) }}</textarea>
        
        {{-- CodeMirror will be mounted here --}}
        <div id="{{ $data['id'] }}-editor" class="code-editor-mount"></div>
    </div>
</div>
