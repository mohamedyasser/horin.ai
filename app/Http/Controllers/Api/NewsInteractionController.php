<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsCommentResource;
use App\Models\AssetNew;
use App\Models\NewsBookmark;
use App\Models\NewsComment;
use App\Models\NewsRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsInteractionController extends Controller
{
    public function toggleBookmark(Request $request, AssetNew $assetNew): JsonResponse
    {
        $user = $request->user();

        $bookmark = NewsBookmark::query()
            ->where('user_id', $user->id)
            ->where('asset_new_id', $assetNew->id)
            ->first();

        if ($bookmark) {
            $bookmark->delete();

            return response()->json([
                'bookmarked' => false,
                'message' => 'Bookmark removed',
            ]);
        }

        NewsBookmark::create([
            'user_id' => $user->id,
            'asset_new_id' => $assetNew->id,
        ]);

        return response()->json([
            'bookmarked' => true,
            'message' => 'News bookmarked',
        ]);
    }

    public function rate(Request $request, AssetNew $assetNew): JsonResponse
    {
        $validated = $request->validate([
            'helpful' => 'required|boolean',
        ]);

        $user = $request->user();

        $rating = NewsRating::query()
            ->where('user_id', $user->id)
            ->where('asset_new_id', $assetNew->id)
            ->first();

        if ($rating) {
            // If same rating, remove it
            if ($rating->helpful === $validated['helpful']) {
                $rating->delete();

                return response()->json([
                    'user_rating' => null,
                    'ratings_summary' => $this->getRatingsSummary($assetNew),
                    'message' => 'Rating removed',
                ]);
            }

            // Update rating
            $rating->update(['helpful' => $validated['helpful']]);

            return response()->json([
                'user_rating' => $validated['helpful'],
                'ratings_summary' => $this->getRatingsSummary($assetNew),
                'message' => 'Rating updated',
            ]);
        }

        // Create new rating
        NewsRating::create([
            'user_id' => $user->id,
            'asset_new_id' => $assetNew->id,
            'helpful' => $validated['helpful'],
        ]);

        return response()->json([
            'user_rating' => $validated['helpful'],
            'ratings_summary' => $this->getRatingsSummary($assetNew),
            'message' => 'Thanks for your feedback',
        ]);
    }

    private function getRatingsSummary(AssetNew $assetNew): array
    {
        return [
            'helpful_count' => $assetNew->ratings()->where('helpful', true)->count(),
            'not_helpful_count' => $assetNew->ratings()->where('helpful', false)->count(),
        ];
    }

    public function getComments(AssetNew $assetNew): AnonymousResourceCollection
    {
        $comments = $assetNew->comments()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return NewsCommentResource::collection($comments);
    }

    public function storeComment(Request $request, AssetNew $assetNew): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|min:1|max:1000',
        ]);

        $comment = NewsComment::create([
            'user_id' => $request->user()->id,
            'asset_new_id' => $assetNew->id,
            'content' => $validated['content'],
        ]);

        $comment->load('user');

        return response()->json([
            'comment' => new NewsCommentResource($comment),
            'comments_count' => $assetNew->comments()->count(),
            'message' => 'Comment posted',
        ], 201);
    }

    public function deleteComment(Request $request, AssetNew $assetNew, NewsComment $comment): JsonResponse
    {
        // Ensure the comment belongs to the news article
        if ($comment->asset_new_id !== $assetNew->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        // Only allow the comment owner to delete
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json([
            'comments_count' => $assetNew->comments()->count(),
            'message' => 'Comment deleted',
        ]);
    }
}
