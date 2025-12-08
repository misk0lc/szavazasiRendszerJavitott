<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollApiController extends Controller
{
    public function index(): JsonResponse
    {
        $polls = Poll::orderByDesc('created_at')->get();
        return response()->json($polls);
    }

    public function store(Request $request): JsonResponse
    {
        // Filter empty options before validation
        if ($request->has('options') && is_array($request->options)) {
            $filteredOptions = array_values(array_filter(
                $request->options,
                fn($s) => is_string($s) && trim($s) !== ''
            ));
            $request->merge(['options' => $filteredOptions]);
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*' => ['string'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $poll = Poll::create([
            'question' => $data['question'],
            'description' => $data['description'] ?? null,
            'options' => $data['options'],
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        return response()->json($poll, 201);
    }

    public function show(Poll $poll): JsonResponse
    {
        return response()->json($poll);
    }

    public function results(Poll $poll): JsonResponse
    {
        $options = $poll->options ?? [];
        $counts = [];
        foreach ($options as $opt) {
            $counts[$opt] = $poll->votes()->where('selected_option', $opt)->count();
        }
        $total = array_sum($counts);
        return response()->json([
            'poll' => $poll,
            'counts' => $counts,
            'total' => $total,
        ]);
    }
}
