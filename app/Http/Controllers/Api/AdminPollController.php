<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPollController extends Controller
{
    /**
     * Update an existing poll (admin only)
     */
    public function update(Request $request, Poll $poll): JsonResponse
    {
        $data = $request->validate([
            'question' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'options' => ['sometimes', 'required', 'array', 'min:2'],
            'options.*' => ['string'],
            'closes_at' => ['nullable', 'date'],
        ]);

        if (isset($data['options'])) {
            $data['options'] = array_values(array_filter($data['options'], fn($s) => trim($s) !== ''));
        }

        $poll->update($data);

        return response()->json([
            'message' => 'Poll updated successfully',
            'poll' => $poll->fresh()
        ]);
    }

    /**
     * Soft delete a poll (admin only)
     */
    public function destroy(Poll $poll): JsonResponse
    {
        $poll->delete(); // Soft delete

        return response()->json([
            'message' => 'Poll soft deleted successfully'
        ]);
    }

    /**
     * Close a poll immediately (admin only)
     */
    public function close(Poll $poll): JsonResponse
    {
        $poll->update([
            'closes_at' => now()
        ]);

        return response()->json([
            'message' => 'Poll closed successfully',
            'poll' => $poll->fresh()
        ]);
    }

    /**
     * Reopen or extend a poll by setting a new closing date (admin only)
     */
    public function extend(Request $request, Poll $poll): JsonResponse
    {
        $data = $request->validate([
            'closes_at' => ['required', 'date', 'after:now']
        ]);

        $poll->update([
            'closes_at' => $data['closes_at']
        ]);

        return response()->json([
            'message' => 'Poll deadline extended successfully',
            'poll' => $poll->fresh()
        ]);
    }

    /**
     * Open a poll by removing the closing date (admin only)
     */
    public function open(Poll $poll): JsonResponse
    {
        $poll->update([
            'closes_at' => null
        ]);

        return response()->json([
            'message' => 'Poll opened (no closing date)',
            'poll' => $poll->fresh()
        ]);
    }

    /**
     * Restore a soft deleted poll (admin only)
     */
    public function restore(int $id): JsonResponse
    {
        $poll = Poll::withTrashed()->findOrFail($id);
        
        if (!$poll->trashed()) {
            return response()->json([
                'message' => 'Poll is not deleted'
            ], 400);
        }

        $poll->restore();

        return response()->json([
            'message' => 'Poll restored successfully',
            'poll' => $poll->fresh()
        ]);
    }

    /**
     * Permanently delete a poll (admin only)
     */
    public function forceDestroy(int $id): JsonResponse
    {
        $poll = Poll::withTrashed()->findOrFail($id);
        
        // Delete all related votes permanently
        $poll->votes()->delete();
        
        // Force delete the poll
        $poll->forceDelete();

        return response()->json([
            'message' => 'Poll permanently deleted'
        ]);
    }

    /**
     * List all soft deleted polls (admin only)
     */
    public function trashed(): JsonResponse
    {
        $polls = Poll::onlyTrashed()->orderByDesc('deleted_at')->get();
        
        return response()->json([
            'deleted_polls' => $polls
        ]);
    }
}
