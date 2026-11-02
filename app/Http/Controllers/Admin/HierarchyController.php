<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\Division;
use App\Models\Section;
use App\Models\Subdivision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HierarchyController extends Controller
{
    /**
     * Circle options scoped to the selected DISCOM.
     */
    public function circles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'discom_id' => ['required', 'integer', 'exists:discoms,id'],
        ]);

        return $this->options(
            Circle::query()->where('discom_id', $validated['discom_id'])
        );
    }

    /**
     * Division options scoped to the selected Circle.
     */
    public function divisions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circle_id' => ['required', 'integer', 'exists:circles,id'],
        ]);

        return $this->options(
            Division::query()->where('circle_id', $validated['circle_id'])
        );
    }

    /**
     * Subdivision options scoped to the selected Division.
     */
    public function subdivisions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
        ]);

        return $this->options(
            Subdivision::query()->where('division_id', $validated['division_id'])
        );
    }

    /**
     * Section options scoped to the selected Subdivision.
     */
    public function sections(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subdivision_id' => ['required', 'integer', 'exists:subdivisions,id'],
        ]);

        return $this->options(
            Section::query()->where('subdivision_id', $validated['subdivision_id'])
        );
    }

    /**
     * Active options of one hierarchy level, ordered by name.
     */
    private function options(Builder $query): JsonResponse
    {
        return response()->json([
            'data' => $query->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
