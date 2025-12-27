{{-- Tabs Component --}}
<section class="component-tabs-section" aria-label="{{ $data['ariaLabels']['section'] }}" data-tabs-id="{{ $data['id'] }}">
    {{-- Tab Navigation --}}
    <div class="tabs-nav" role="tablist" aria-label="{{ $data['ariaLabels']['tabList'] }}">
        @foreach($data['tabs'] as $tab)
            <button 
                class="tab-button{{ $tab['active'] ? ' active' : '' }}" 
                role="tab" 
                aria-selected="{{ $tab['active'] ? 'true' : 'false' }}"
                aria-controls="tab-panel-{{ $data['id'] }}-{{ $tab['id'] }}"
                id="tab-{{ $data['id'] }}-{{ $tab['id'] }}"
                data-tab="{{ $tab['id'] }}"
                type="button"
            >
                @if($tab['icon'])
                    <i class="fas {{ $tab['icon'] }}"></i>
                @endif
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>
    
    {{-- Tab Panels --}}
    <div class="tabs-content">
        @foreach($data['tabs'] as $tab)
            <div 
                class="tab-panel{{ $tab['active'] ? ' active' : '' }}" 
                role="tabpanel"
                aria-labelledby="tab-{{ $data['id'] }}-{{ $tab['id'] }}"
                id="tab-panel-{{ $data['id'] }}-{{ $tab['id'] }}"
                data-tab="{{ $tab['id'] }}"
                {{ !$tab['active'] ? 'hidden' : '' }}
            >
                {{-- Render components for this tab --}}
                @foreach($tab['components'] as $component)
                    @include("mobile-entry.components.{$component['type']}", ['data' => $component['data']])
                @endforeach
            </div>
        @endforeach
    </div>
</section>