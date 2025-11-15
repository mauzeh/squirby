{{-- Bulk Action Form Component - For submitting multiple selected items --}}
<section class="component-button-section" style="margin-top: 20px; margin-bottom: 20px;">
    <div class="container">
        <form action="{{ $data['action'] }}" 
              method="POST" 
              id="{{ $data['formId'] }}"
              data-checkbox-selector="{{ $data['checkboxSelector'] }}"
              data-input-name="{{ $data['inputName'] }}"
              data-empty-message="{{ $data['emptyMessage'] }}"
              @if($data['confirmMessage'])
              data-confirm-message="{{ $data['confirmMessage'] }}"
              @endif
              class="bulk-action-form">
            @csrf
            @if($data['method'] !== 'POST')
                @method($data['method'])
            @endif
            <button type="submit" 
                    class="btn-primary {{ $data['buttonClass'] }}"
                    @if($data['ariaLabel'])
                    aria-label="{{ $data['ariaLabel'] }}"
                    @endif>
                <i class="fa-solid {{ $data['icon'] }}"></i> {{ $data['buttonText'] }}
            </button>
        </form>
    </div>
</section>
