{{-- WOD Form Component with Code Editor --}}
<section class="component-form-section form" aria-label="WOD Form" data-form-type="{{ $data['formType'] }}" data-form-id="{{ $data['id'] }}">
    <div class="component-header">
        <h2 class="component-heading">{{ $data['title'] }}</h2>
    </div>
    
    <form class="component-form-element" method="POST" action="{{ $data['formAction'] }}" data-form-type="{{ $data['formType'] }}">
        @csrf
        <input type="hidden" name="type" value="wod">
        @if(isset($data['hiddenFields']))
            @foreach($data['hiddenFields'] as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
        @endif
        
        {{-- Name Field --}}
        <div class="form-mobile-group">
            <label for="{{ $data['id'] }}-name" class="form-mobile-label">{{ $data['nameField']['label'] }}</label>
            <input 
                type="text" 
                id="{{ $data['id'] }}-name" 
                name="name" 
                class="text-input" 
                value="{{ old('name', $data['nameField']['value']) }}" 
                placeholder="{{ $data['nameField']['placeholder'] }}" 
                required
            >
        </div>
        
        {{-- Code Editor --}}
        <div class="component-code-editor" data-editor-id="{{ $data['codeEditor']['id'] }}">
            <div class="code-editor-header">
                <label for="{{ $data['codeEditor']['id'] }}-textarea" class="code-editor-label">
                    {{ $data['codeEditor']['label'] }}
                </label>
            </div>
            
            <div class="code-editor-wrapper" style="height: {{ $data['codeEditor']['height'] }};">
                {{-- Hidden textarea for form submission and no-JS fallback --}}
                <textarea 
                    id="{{ $data['codeEditor']['id'] }}-textarea" 
                    name="{{ $data['codeEditor']['name'] }}" 
                    class="code-editor-textarea"
                    placeholder="{{ $data['codeEditor']['placeholder'] }}"
                    aria-label="{{ $data['codeEditor']['label'] }}"
                    data-mode="{{ $data['codeEditor']['mode'] }}"
                    data-theme="dark"
                    data-line-numbers="{{ $data['codeEditor']['lineNumbers'] ? 'true' : 'false' }}"
                    data-line-wrapping="true"
                    data-read-only="false"
                    data-autofocus="false"
                    required
                >{{ old($data['codeEditor']['name'], $data['codeEditor']['value']) }}</textarea>
                
                {{-- CodeMirror will be mounted here --}}
                <div id="{{ $data['codeEditor']['id'] }}-editor" class="code-editor-mount"></div>
            </div>
        </div>
        
        {{-- Description Field --}}
        <div class="form-mobile-group">
            <label for="{{ $data['id'] }}-description" class="form-mobile-label">{{ $data['descriptionField']['label'] }}</label>
            <input 
                type="text" 
                id="{{ $data['id'] }}-description" 
                name="description" 
                class="text-input" 
                value="{{ old('description', $data['descriptionField']['value']) }}" 
                placeholder="{{ $data['descriptionField']['placeholder'] }}"
            >
        </div>
        
        {{-- Submit Button --}}
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">{{ $data['submitButton'] }}</button>
        </div>
    </form>
</section>
