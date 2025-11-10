@extends('app')

@section('content')
<div class="container">
    <h1>Select Exercise</h1>
    
    <div style="margin-bottom: 20px;">
        <a href="{{ $returnUrl }}" class="button">
            <i class="fa-solid fa-arrow-left"></i> Back to Template
        </a>
    </div>
    
    {{-- Exercise Selection using the same UI as mobile lift forms --}}
    <section class="item-selection-section">
        {{-- Filter and Create Form --}}
        <div class="item-filter-container" style="margin-bottom: 20px;">
            <form method="GET" action="{{ route('workout-templates.show-exercise-selection') }}" style="margin-bottom: 10px;">
                @foreach(request()->except('filter') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" 
                           name="filter" 
                           class="form-control" 
                           placeholder="{{ $exerciseSelectionList['filterPlaceholder'] }}" 
                           value="{{ request('filter') }}"
                           autocomplete="off" 
                           autocorrect="off" 
                           autocapitalize="off" 
                           spellcheck="false"
                           style="flex: 1;">
                    <button type="submit" class="button">Filter</button>
                    @if(request('filter'))
                        <a href="{{ route('workout-templates.show-exercise-selection') }}?{{ http_build_query(request()->except('filter')) }}" class="button">Clear</a>
                    @endif
                </div>
            </form>
            
            @if(isset($exerciseSelectionList['createForm']))
            <form method="{{ $exerciseSelectionList['createForm']['method'] }}" 
                  action="{{ $exerciseSelectionList['createForm']['action'] }}" 
                  style="display: flex; gap: 10px; align-items: center;">
                @csrf
                <input type="text" 
                       name="{{ $exerciseSelectionList['createForm']['inputName'] }}" 
                       class="form-control" 
                       placeholder="New exercise name..."
                       value="{{ request('filter') }}"
                       required
                       style="flex: 1;">
                @if(isset($exerciseSelectionList['createForm']['hiddenFields']))
                    @foreach($exerciseSelectionList['createForm']['hiddenFields'] as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                @endif
                <button type="submit" class="button create">
                    {{ $exerciseSelectionList['createForm']['submitText'] }}
                </button>
            </form>
            @endif
        </div>
        
        {{-- Exercise List --}}
        @php
            $filterValue = strtolower(request('filter', ''));
            $filteredItems = collect($exerciseSelectionList['items'])->filter(function($item) use ($filterValue) {
                return empty($filterValue) || str_contains(strtolower($item['name']), $filterValue);
            });
        @endphp
        
        @if($filteredItems->count() > 0)
            <div class="exercise-selection-list">
                @foreach($filteredItems as $item)
                    <div class="item-selection-card item-selection-card--{{ $item['type']['cssClass'] }}">
                        <div style="flex: 1;">
                            <span class="item-name">{{ $item['name'] }}</span>
                            <span class="item-type">{!! $item['type']['label'] !!}</span>
                        </div>
                        <form method="POST" action="{{ route('workout-templates.add-exercise') }}" style="display: inline;">
                            @csrf
                            <input type="hidden" name="exercise_id" value="{{ str_replace('exercise-', '', $item['id']) }}">
                            <input type="hidden" name="exercises" value="{{ json_encode($currentExercises) }}">
                            <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            @if($returnTo === 'edit')
                                <input type="hidden" name="template_id" value="{{ $templateId }}">
                            @endif
                            <input type="hidden" name="name" value="{{ $templateName }}">
                            <input type="hidden" name="description" value="{{ $templateDescription }}">
                            <button type="submit" class="button create">
                                <i class="fa-solid fa-plus"></i> Add
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <div class="item-selection-card item-selection-card--no-results">
                <span class="item-name">{{ $exerciseSelectionList['noResultsMessage'] }}</span>
                <span class="item-type">No matches</span>
            </div>
        @endif
    </section>
</div>

<style>
.item-selection-section {
    margin-top: 20px;
}

.item-filter-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.exercise-selection-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.item-selection-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    transition: all 0.2s;
}

.item-selection-card:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.item-selection-card--in-program {
    background: #d4edda;
    border-color: #c3e6cb;
}

.item-selection-card--recent {
    background: #d1ecf1;
    border-color: #bee5eb;
}

.item-selection-card--custom {
    background: #fff3cd;
    border-color: #ffeaa7;
}

.item-selection-card--no-results {
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.item-name {
    font-weight: 500;
    display: block;
    margin-bottom: 4px;
}

.item-type {
    font-size: 0.85em;
    color: #6c757d;
    display: block;
}

@media (max-width: 768px) {
    .item-selection-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .item-selection-card form {
        width: 100%;
    }
    
    .item-selection-card form button {
        width: 100%;
    }
}
</style>
@endsection
