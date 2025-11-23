{{-- Calculator Grid Component - Display 1RM percentage calculator --}}
<section class="component-calculator-grid-section" aria-label="{{ $data['ariaLabel'] ?? '1-Rep Max Calculator' }}">
    @if(isset($data['title']) && !empty($data['title']))
    <h2 class="component-calculator-grid-title">{{ $data['title'] }}</h2>
    @endif
    
    @if(isset($data['note']) && !empty($data['note']))
    <p class="component-calculator-grid-note">{{ $data['note'] }}</p>
    @endif
    
    <div class="calculator-grid">
        <table class="calculator-table">
            <thead>
                <tr>
                    <th class="percentage-header">%</th>
                    @foreach($data['columns'] as $column)
                    <th class="weight-header">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data['percentages'] as $index => $percentage)
                <tr>
                    <td class="percentage-label">{{ $percentage }}%</td>
                    @if(isset($data['rows'][$index]))
                        @foreach($data['rows'][$index]['weights'] as $weight)
                        <td class="weight-value">
                            @if($weight !== null)
                                {{ $weight }}
                            @else
                                —
                            @endif
                        </td>
                        @endforeach
                    @else
                        @foreach($data['columns'] as $column)
                        <td class="weight-value">—</td>
                        @endforeach
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
