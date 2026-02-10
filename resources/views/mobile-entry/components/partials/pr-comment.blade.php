<div class="pr-comment" data-comment-id="{{ $comment->id }}">
    <div class="pr-comment-header">
        <strong class="pr-comment-author">{{ $comment->user_id === $currentUserId ? 'You' : $comment->user->name }}</strong>
        <span class="pr-comment-time">{{ $comment->created_at->diffForHumans() }}</span>
    </div>
    <div class="pr-comment-body">{{ $comment->comment }}</div>
    @if($comment->user_id === $currentUserId)
        <form method="POST" action="{{ route('feed.delete-comment', $comment) }}" class="pr-comment-delete-form">
            @csrf
            @method('DELETE')
            <button type="submit" class="pr-comment-delete-btn" title="Delete comment">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    @endif
</div>
