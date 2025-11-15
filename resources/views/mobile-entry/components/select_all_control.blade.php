{{-- Select All Control Component - For bulk selection --}}
<div class="container" style="margin-bottom: 15px;">
    <label style="display: flex; align-items: center; gap: 8px; font-size: 1em; cursor: pointer;">
        <input type="checkbox" 
               id="{{ $data['checkboxId'] }}" 
               data-checkbox-selector="{{ $data['checkboxSelector'] }}"
               class="select-all-checkbox"
               style="width: 20px; height: 20px; cursor: pointer;">
        <span>{{ $data['label'] }}</span>
    </label>
</div>
