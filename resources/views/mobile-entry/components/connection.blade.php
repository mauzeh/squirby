<div class="component-form-section connection-section">
    <div class="connection-header">
        <h3>Connect with Friends</h3>
        <p class="connection-subtitle">Share your code or QR code with someone at the gym to connect</p>
    </div>

    <div class="connection-code-container">
        <div class="connection-code-display">
            <div class="connection-code-label">Your Connection Code</div>
            <div class="connection-code">{{ chunk_split($data['token'], 3, ' ') }}</div>
            <div class="connection-code-expires">Expires in {{ $data['minutesRemaining'] }} minutes</div>
        </div>

        <div class="connection-qr">
            <img src="{{ $data['qrCodeUrl'] }}" alt="QR Code" class="qr-code-img">
            <div class="qr-code-label">Scan to connect</div>
        </div>
    </div>

    <form method="POST" action="{{ $data['generateTokenRoute'] }}" class="connection-refresh-form">
        @csrf
        <button type="submit" class="btn btn-secondary">
            <i class="fas fa-sync-alt"></i>&nbsp; Generate New Code
        </button>
    </form>

    <div class="connection-divider">
        <span>OR</span>
    </div>

    <form method="POST" action="#" class="connection-input-form" id="connection-input-form">
        @csrf
        <div class="form-field">
            <label for="connection_code">Enter a friend's code</label>
            <input
                type="text"
                id="connection_code"
                name="connection_code"
                class="text-input connection-code-input"
                placeholder="000 000"
                maxlength="7"
                pattern="[0-9 ]*"
                inputmode="numeric"
            >
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-user-plus"></i>&nbsp; Connect
        </button>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("connection-input-form");
    const input = document.getElementById("connection_code");

    // Auto-format input with space after 3 digits
    input.addEventListener("input", function(e) {
        let value = e.target.value.replace(/\s/g, "");
        if (value.length > 3) {
            value = value.slice(0, 3) + " " + value.slice(3, 6);
        }
        e.target.value = value;
    });

    // Handle form submission
    form.addEventListener("submit", function(e) {
        e.preventDefault();
        const code = input.value.replace(/\s/g, "");
        if (code.length === 6) {
            // Update form action and submit
            form.action = "{{ $data['connectRoute'] }}".replace('__TOKEN__', code);
            form.submit();
        }
    });
});
</script>
