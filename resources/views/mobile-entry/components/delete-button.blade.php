{{-- Delete Button Component --}}
<form method="POST" action="{{ $data['action'] }}" style="margin: 1.5rem 0;" data-confirm-message="{{ $data['confirmMessage'] }}">
    @csrf
    @method($data['method'])
    <button type="submit" style="width: 100%; padding: 0.75rem; font-size: 1rem; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
        {{ $data['text'] }}
    </button>
</form>
