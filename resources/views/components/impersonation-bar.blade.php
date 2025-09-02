@if(app('impersonate')->isImpersonating())
    <div class="impersonation-bar">
        You are currently impersonating {{ auth()->user()->name }}.
        <a href="{{ route('impersonate.leave') }}">Leave Impersonation</a>
    </div>
@endif
