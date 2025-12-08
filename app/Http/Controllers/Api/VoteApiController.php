<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteApiController extends Controller
{
    public function store(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'selected_option' => ['required', 'string'],
        ]);

        $isClosed = $poll->closes_at !== null && now()->greaterThan($poll->closes_at);
        if ($isClosed) {
            return response()->json(['message' => 'Poll is closed'], 422);
        }

        $options = $poll->options ?? [];
        if (!in_array($data['selected_option'], $options, true)) {
            return response()->json(['message' => 'Invalid option'], 422);
        }

        $already = $poll->votes()->where('user_id', $user->id)->exists();
        if ($already) {
            return response()->json(['message' => 'You already voted in this poll'], 422);
        }

        $vote = Vote::create([
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => $data['selected_option'],
            'voted_at' => now(),
        ]);

        return response()->json($vote, 201);
    }
}
