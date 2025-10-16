@props(['intelligence'])

@if($intelligence)
<div class="intelligence-summary" style="background-color: #2a2a2a; border-radius: 5px; padding: 15px; margin: 15px 0;">
    <h4 style="margin-top: 0; color: #4CAF50; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-brain"></i> Exercise Intelligence
    </h4>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <!-- Movement Info -->
        <div>
            <h5 style="margin: 0 0 8px 0; color: #f2f2f2;">Movement Pattern</h5>
            <p style="margin: 0; color: #aaa;">
                <strong>{{ ucfirst(str_replace('_', ' ', $intelligence->movement_archetype)) }}</strong>
                <br><small>{{ ucfirst($intelligence->category) }} exercise</small>
            </p>
        </div>

        <!-- Difficulty & Recovery -->
        <div>
            <h5 style="margin: 0 0 8px 0; color: #f2f2f2;">Training Info</h5>
            <p style="margin: 0; color: #aaa;">
                <strong>Level {{ $intelligence->difficulty_level }}/5</strong>
                <br><small>{{ $intelligence->recovery_hours }}h recovery time</small>
            </p>
        </div>

        <!-- Primary Muscles -->
        <div>
            <h5 style="margin: 0 0 8px 0; color: #f2f2f2;">Key Muscles</h5>
            <p style="margin: 0; color: #aaa;">
                <strong>{{ ucfirst(str_replace('_', ' ', $intelligence->primary_mover)) }}</strong>
                <br><small>Largest: {{ ucfirst(str_replace('_', ' ', $intelligence->largest_muscle)) }}</small>
            </p>
        </div>
    </div>

    <!-- Detailed Muscle Breakdown -->
    @if(isset($intelligence->muscle_data['muscles']) && count($intelligence->muscle_data['muscles']) > 0)
    <div style="margin-top: 15px;">
        <h5 style="margin: 0 0 10px 0; color: #f2f2f2;">Muscle Involvement</h5>
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            @php
                $musclesByRole = collect($intelligence->muscle_data['muscles'])->groupBy('role');
            @endphp
            
            @foreach(['primary_mover', 'synergist', 'stabilizer'] as $role)
                @if($musclesByRole->has($role))
                <div style="flex: 1; min-width: 150px;">
                    <h6 style="margin: 0 0 5px 0; color: #ccc; font-size: 0.9em;">
                        {{ ucfirst(str_replace('_', ' ', $role)) }}s
                    </h6>
                    @foreach($musclesByRole[$role] as $muscle)
                    <div style="background-color: #1a1a1a; padding: 4px 8px; border-radius: 3px; margin-bottom: 3px; font-size: 0.85em;">
                        {{ ucfirst(str_replace('_', ' ', $muscle['name'])) }}
                        <small style="color: #888;">({{ $muscle['contraction_type'] }})</small>
                    </div>
                    @endforeach
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif