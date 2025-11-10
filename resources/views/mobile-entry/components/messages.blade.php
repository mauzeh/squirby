{{-- Messages Component --}}
@if(!empty($data['messages']))
<section class="interface-messages" aria-label="Status messages">
    @foreach($data['messages'] as $message)
    <div class="interface-message interface-message--{{ $message['type'] }}">
        @if(isset($message['prefix']))
        <span class="message-prefix">{{ $message['prefix'] }}</span>
        @endif
        {{ $message['text'] }}
    </div>
    @endforeach
</section>
@endif
