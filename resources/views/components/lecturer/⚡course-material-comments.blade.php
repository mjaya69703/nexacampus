<?php

use App\Models\Academic\CourseMaterial;
use App\Models\Academic\CourseMaterialComment;
use App\Models\Academic\MaterialLike;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    
    public int $materialId;
    public array $comments = [];
    public string $newComment = '';
    public ?int $replyingTo = null;
    public string $replyContent = '';
    public bool $isLiked = false;
    public int $likesCount = 0;
    public ?int $editingCommentId = null;
    public string $editContent = '';
    public int $perPage = 10; // Pagination
    
    public function mount(int $materialId): void
    {
        $this->materialId = $materialId;
        $this->loadMaterialLikes();
        $this->loadComments();
    }
    
    private function loadMaterialLikes(): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            return;
        }
        
        $material = CourseMaterial::find($this->materialId);
        $this->likesCount = $material->likes_count ?? 0;
        $this->isLiked = MaterialLike::where('course_material_id', $this->materialId)
            ->where('user_id', $user->id)
            ->exists();
    }
    
    private function loadComments(): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            return;
        }
        
        $comments = CourseMaterialComment::with([
            'user',
            'replies.user',
            'replies.likes' => function($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        ])
        ->where('course_material_id', $this->materialId)
        ->whereNull('parent_id')
        ->where('is_deleted', false)
        ->orderBy('created_at', 'desc')
        ->paginate($this->perPage);
        
        $this->comments = $comments->map(function($comment) use ($user) {
            return [
                'id' => $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user->name ?? 'Unknown',
                'user_role' => $comment->user->roles->pluck('name')->first() ?? 'user',
                'user_id' => $comment->user_id,
                'created_at' => $comment->created_at->diffForHumans(),
                'edited_at' => $comment->edited_at?->diffForHumans(),
                'is_edited' => $comment->is_edited,
                'likes_count' => $comment->likes_count,
                'is_liked' => $comment->likes->isNotEmpty(),
                'can_edit' => $comment->user_id == $user->id,
                'replies' => $comment->replies->map(function($reply) use ($user) {
                    return [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'user_name' => $reply->user->name ?? 'Unknown',
                        'user_role' => $reply->user->roles->pluck('name')->first() ?? 'user',
                        'user_id' => $reply->user_id,
                        'created_at' => $reply->created_at->diffForHumans(),
                        'edited_at' => $reply->edited_at?->diffForHumans(),
                        'is_edited' => $reply->is_edited,
                        'likes_count' => $reply->likes_count,
                        'is_liked' => $reply->likes->isNotEmpty(),
                        'can_edit' => $reply->user_id == $user->id,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }
    
    public function addComment(): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            session()->flash('error', 'Anda harus login.');
            return;
        }
        
        $validated = $this->validate([
            'newComment' => 'required|string|min:3|max:2000',
        ]);
        
        CourseMaterialComment::create([
            'course_material_id' => $this->materialId,
            'user_id' => $user->id,
            'content' => $this->newComment,
            'likes_count' => 0,
        ]);
        
        $this->newComment = '';
        session()->flash('success', 'Komentar berhasil ditambahkan!');
        $this->loadComments();
    }
    
    public function startReply(int $commentId): void
    {
        $this->replyingTo = $commentId;
        $this->replyContent = '';
    }
    
    public function cancelReply(): void
    {
        $this->replyingTo = null;
        $this->replyContent = '';
    }
    
    public function addReply(int $parentId): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            session()->flash('error', 'Anda harus login.');
            return;
        }
        
        $validated = $this->validate([
            'replyContent' => 'required|string|min:3|max:2000',
        ]);
        
        CourseMaterialComment::create([
            'course_material_id' => $this->materialId,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'content' => $this->replyContent,
            'likes_count' => 0,
        ]);
        
        $this->cancelReply();
        session()->flash('success', 'Balasan berhasil ditambahkan!');
        $this->loadComments();
    }
    
    public function toggleLike(int $commentId): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            session()->flash('error', 'Anda harus login.');
            return;
        }
        
        $existingLike = \App\Models\Academic\CommentLike::where('comment_id', $commentId)
            ->where('user_id', $user->id)
            ->first();
        
        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            
            // Decrement likes count
            $comment = CourseMaterialComment::find($commentId);
            if ($comment) {
                $comment->decrement('likes_count');
            }
        } else {
            // Like
            \App\Models\Academic\CommentLike::create([
                'comment_id' => $commentId,
                'user_id' => $user->id,
            ]);
            
            // Increment likes count
            $comment = CourseMaterialComment::find($commentId);
            if ($comment) {
                $comment->increment('likes_count');
            }
        }
        
        $this->loadComments();
    }
    
    // Toggle like for material (not comment)
    public function toggleMaterialLike(): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            session()->flash('error', 'Anda harus login.');
            return;
        }
        
        $existingLike = MaterialLike::where('course_material_id', $this->materialId)
            ->where('user_id', $user->id)
            ->first();
        
        if ($existingLike) {
            // Unlike
            $existingLike->delete();
            $this->isLiked = false;
            
            // Decrement likes count
            $material = CourseMaterial::find($this->materialId);
            if ($material) {
                $material->decrement('likes_count');
                $this->likesCount = $material->likes_count;
            }
        } else {
            // Like
            MaterialLike::create([
                'course_material_id' => $this->materialId,
                'user_id' => $user->id,
            ]);
            $this->isLiked = true;
            
            // Increment likes count
            $material = CourseMaterial::find($this->materialId);
            if ($material) {
                $material->increment('likes_count');
                $this->likesCount = $material->likes_count;
            }
        }
    }
    
    // Start editing comment
    public function startEditComment(int $commentId, bool $isReply = false): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            return;
        }
        
        $comment = CourseMaterialComment::find($commentId);
        
        if (!$comment || $comment->user_id != $user->id) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengedit komentar ini.');
            return;
        }
        
        $this->editingCommentId = $commentId;
        $this->editContent = $comment->content;
    }
    
    // Cancel editing
    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editContent = '';
    }
    
    // Update comment
    public function updateComment(): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            return;
        }
        
        $validated = $this->validate([
            'editContent' => 'required|string|min:3|max:2000',
        ]);
        
        $comment = CourseMaterialComment::find($this->editingCommentId);
        
        if (!$comment || $comment->user_id != $user->id) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengedit komentar ini.');
            return;
        }
        
        $comment->update([
            'content' => $this->editContent,
            'is_edited' => true,
            'edited_at' => now(),
        ]);
        
        $this->cancelEditComment();
        session()->flash('success', 'Komentar berhasil diupdate!');
        $this->loadComments();
    }
    
    // Delete comment (soft delete - mark as deleted)
    public function deleteComment(int $commentId): void
    {
        $user = auth()->user();
        
        if (!$user || !($user->hasRole('lecturer') || $user->hasRole('student'))) {
            return;
        }
        
        $comment = CourseMaterialComment::find($commentId);
        
        if (!$comment || ($comment->user_id != $user->id && !$user->hasRole('lecturer'))) {
            session()->flash('error', 'Anda tidak memiliki izin untuk menghapus komentar ini.');
            return;
        }

        // Hard delete with descendants (replies + likes) so thread is fully removed.
        $idsToDelete = $this->collectCommentTreeIds((int) $comment->id);
        \App\Models\Academic\CommentLike::whereIn('comment_id', $idsToDelete)->delete();
        CourseMaterialComment::whereIn('id', $idsToDelete)->delete();
        
        session()->flash('success', 'Komentar berhasil dihapus!');
        $this->loadComments();
    }

    private function collectCommentTreeIds(int $rootId): array
    {
        $allIds = [$rootId];
        $queue = [$rootId];

        while (! empty($queue)) {
            $childIds = CourseMaterialComment::query()
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($childIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childIds);
            $queue = $childIds;
        }

        return array_values(array_unique($allIds));
    }
    
    // Load more comments (pagination)
    public function loadMore(): void
    {
        $this->perPage += 10;
        $this->loadComments();
    }
};
?>

<div>
    {{-- Success Message --}}
    @if(session()->has('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    {{-- Error Message --}}
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="d-flex">
                <div>
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    
    {{-- Add Comment Form --}}
    <div class="card mb-4" style="border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div class="card-body">
            <h5 class="card-title mb-3" style="font-weight: 700; color: #1e293b;">
                <i class="fas fa-comment-dots me-2" style="color: #667eea;"></i>Diskusi Materi
            </h5>
            
            <form wire:submit.prevent="addComment">
                <div class="mb-3">
                    <textarea 
                        wire:model="newComment"
                        class="form-control" 
                        rows="3"
                        placeholder="Tulis pertanyaan atau komentar Anda di sini..."
                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 16px;"
                    ></textarea>
                    @error('newComment') 
                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                    @enderror
                </div>
                <div class="d-flex justify-content-end">
                    <button 
                        type="submit" 
                        class="btn btn-primary"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; padding: 10px 24px; font-weight: 600;"
                    >
                        <i class="fas fa-paper-plane me-2"></i>Kirim Komentar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    {{-- Comments List --}}
    <div class="comments-section">
        @if(count($comments) === 0)
            <div class="text-center py-5" style="color: #94a3b8;">
                <i class="fas fa-comments" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                <p style="font-size: 1.1rem;">Belum ada diskusi. Jadilah yang pertama bertanya!</p>
            </div>
        @else
            @foreach($comments as $comment)
                <div class="comment-item mb-4" style="animation: fadeIn 0.3s ease-in;">
                    {{-- Main Comment --}}
                    <div class="card" style="border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <i class="fas fa-user" style="color: white; font-size: 1.2rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong style="color: #1e293b;">{{ $comment['user_name'] }}</strong>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 0.75rem;">{{ ucfirst($comment['user_role']) }}</span>
                                    </div>
                                    <small style="color: #94a3b8;">
                                        {{ $comment['created_at'] }}
                                        @if($comment['is_edited'])
                                            <span class="ms-1" style="color: #64748b; font-weight: 600;">(edited)</span>
                                        @endif
                                    </small>
                                </div>
                            </div>

                            @if($editingCommentId === $comment['id'])
                                <form wire:submit.prevent="updateComment" class="mb-3">
                                    <textarea
                                        wire:model="editContent"
                                        class="form-control"
                                        rows="3"
                                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 10px 14px;"
                                    ></textarea>
                                    @error('editContent')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                    <div class="d-flex gap-2 mt-2">
                                        <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 8px;">
                                            Simpan
                                        </button>
                                        <button type="button" wire:click="cancelEditComment" class="btn btn-sm btn-secondary" style="border-radius: 8px;">
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="comment-content mb-3" style="color: #334155; line-height: 1.6;">
                                    {{ $comment['content'] }}
                                </div>
                            @endif
                            
                            <div class="d-flex align-items-center gap-3">
                                <button 
                                    wire:click="toggleLike({{ $comment['id'] }})"
                                    class="btn btn-sm"
                                    style="padding: 6px 12px; border-radius: 8px; {{ $comment['is_liked'] ? 'background: #fef3c7; color: #f59e0b;' : 'background: #f8fafc; color: #64748b;' }}"
                                >
                                    <i class="{{ $comment['is_liked'] ? 'fas' : 'far' }} fa-heart me-1"></i>
                                    {{ $comment['likes_count'] }}
                                </button>
                                
                                @if($replyingTo !== $comment['id'])
                                    <button 
                                        wire:click="startReply({{ $comment['id'] }})"
                                        class="btn btn-sm"
                                        style="padding: 6px 12px; border-radius: 8px; background: #f8fafc; color: #64748b;"
                                    >
                                        <i class="fas fa-reply me-1"></i>Balas
                                    </button>
                                @endif

                                @if($comment['can_edit'])
                                    <button
                                        wire:click="startEditComment({{ $comment['id'] }})"
                                        class="btn btn-sm"
                                        style="padding: 6px 12px; border-radius: 8px; background: #eef2ff; color: #4f46e5;"
                                    >
                                        <i class="fas fa-pen me-1"></i>Edit
                                    </button>
                                @endif

                                @if($comment['can_edit'] || auth()->user()?->hasRole('lecturer'))
                                    <button
                                        wire:click="deleteComment({{ $comment['id'] }})"
                                        class="btn btn-sm"
                                        style="padding: 6px 12px; border-radius: 8px; background: #fee2e2; color: #dc2626;"
                                    >
                                        <i class="fas fa-trash me-1"></i>Hapus
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    {{-- Reply Form --}}
                    @if($replyingTo === $comment['id'])
                        <div class="ms-5 mt-3 mb-3" style="animation: slideDown 0.3s ease-out;">
                            <form wire:submit.prevent="addReply({{ $comment['id'] }})">
                                <div class="mb-2">
                                    <textarea 
                                        wire:model="replyContent"
                                        class="form-control" 
                                        rows="2"
                                        placeholder="Tulis balasan Anda..."
                                        style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 10px 14px; font-size: 0.9rem;"
                                    ></textarea>
                                    @error('replyContent') 
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>
                                <div class="d-flex gap-2">
                                    <button 
                                        type="submit" 
                                        class="btn btn-sm btn-primary"
                                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; padding: 6px 16px; font-weight: 600;"
                                    >
                                        <i class="fas fa-paper-plane me-1"></i>Kirim
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="cancelReply"
                                        class="btn btn-sm btn-secondary"
                                        style="background: #f1f5f9; border: none; border-radius: 8px; padding: 6px 16px; color: #64748b;"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                    
                    {{-- Replies --}}
                    @if(count($comment['replies']) > 0)
                        <div class="ms-5 mt-3">
                            @foreach($comment['replies'] as $reply)
                                <div class="reply-item mb-3" style="border-left: 3px solid #667eea; padding-left: 16px;">
                                    <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; background: #f8fafc;">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start gap-2 mb-2">
                                                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <i class="fas fa-user" style="color: white; font-size: 0.9rem;"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong style="color: #1e293b; font-size: 0.9rem;">{{ $reply['user_name'] }}</strong>
                                                        <span class="badge" style="background: #ecfdf5; color: #10b981; font-size: 0.7rem;">{{ ucfirst($reply['user_role']) }}</span>
                                                    </div>
                                                    <small style="color: #94a3b8; font-size: 0.8rem;">
                                                        {{ $reply['created_at'] }}
                                                        @if($reply['is_edited'])
                                                            <span class="ms-1" style="color: #64748b; font-weight: 600;">(edited)</span>
                                                        @endif
                                                    </small>
                                                </div>
                                            </div>

                                            @if($editingCommentId === $reply['id'])
                                                <form wire:submit.prevent="updateComment" class="mb-2">
                                                    <textarea
                                                        wire:model="editContent"
                                                        class="form-control"
                                                        rows="2"
                                                        style="border-radius: 10px; border: 2px solid #e2e8f0; padding: 8px 12px; font-size: 0.9rem;"
                                                    ></textarea>
                                                    @error('editContent')
                                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                                    @enderror
                                                    <div class="d-flex gap-2 mt-2">
                                                        <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 8px;">Simpan</button>
                                                        <button type="button" wire:click="cancelEditComment" class="btn btn-sm btn-secondary" style="border-radius: 8px;">Batal</button>
                                                    </div>
                                                </form>
                                            @else
                                                <div class="reply-content mb-2" style="color: #334155; font-size: 0.95rem; line-height: 1.5;">
                                                    {{ $reply['content'] }}
                                                </div>
                                            @endif
                                            
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <button 
                                                    wire:click="toggleLike({{ $reply['id'] }})"
                                                    class="btn btn-sm"
                                                    style="padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; {{ $reply['is_liked'] ? 'background: #fef3c7; color: #f59e0b;' : 'background: white; color: #64748b;' }}"
                                                >
                                                    <i class="{{ $reply['is_liked'] ? 'fas' : 'far' }} fa-heart me-1"></i>
                                                    {{ $reply['likes_count'] }}
                                                </button>

                                                @if($reply['can_edit'])
                                                    <button
                                                        wire:click="startEditComment({{ $reply['id'] }}, true)"
                                                        class="btn btn-sm"
                                                        style="padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; background: #eef2ff; color: #4f46e5;"
                                                    >
                                                        <i class="fas fa-pen me-1"></i>Edit
                                                    </button>
                                                @endif

                                                @if($reply['can_edit'] || auth()->user()?->hasRole('lecturer'))
                                                    <button
                                                        wire:click="deleteComment({{ $reply['id'] }})"
                                                        class="btn btn-sm"
                                                        style="padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; background: #fee2e2; color: #dc2626;"
                                                    >
                                                        <i class="fas fa-trash me-1"></i>Hapus
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { opacity: 0; max-height: 0; }
            to { opacity: 1; max-height: 500px; }
        }
    </style>
</div>
