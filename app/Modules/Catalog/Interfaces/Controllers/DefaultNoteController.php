<?php

namespace Modules\Catalog\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Domain\Entities\DefaultNote;
use Illuminate\Support\Str;

class DefaultNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        $notes = DefaultNote::where('company_id', $companyId)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'data' => $notes->map(function ($note) {
                return [
                    'uuid' => $note->uuid,
                    'text_translations' => $note->text_translations,
                    'sort_order' => $note->sort_order,
                    'is_active' => $note->is_active,
                ];
            }),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'text_translations' => 'required|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ]);

        $note = DefaultNote::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'text_translations' => $validated['text_translations'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $note], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $note = DefaultNote::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'text_translations' => 'sometimes|array',
            'sort_order' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $note->update($validated);

        return response()->json(['data' => $note]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $user = request()->user();
        $note = DefaultNote::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $note->delete();

        return response()->json(['message' => 'Nota eliminada']);
    }
}
