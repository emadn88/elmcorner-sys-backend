<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Family\StoreFamilyRequest;
use App\Http\Requests\Family\UpdateFamilyRequest;
use App\Models\Family;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Family::withCount('students');

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        $families = $query->orderBy('name', 'asc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $families->items(),
            'meta' => [
                'current_page' => $families->currentPage(),
                'last_page' => $families->lastPage(),
                'per_page' => $families->perPage(),
                'total' => $families->total(),
            ],
        ]);
    }

    /**
     * Search families for autocomplete
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        
        $families = Family::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'status' => 'success',
            'data' => $families,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFamilyRequest $request): JsonResponse
    {
        $family = Family::create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Family created successfully',
            'data' => $family,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $family = Family::with('students')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $family,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFamilyRequest $request, string $id): JsonResponse
    {
        $family = Family::findOrFail($id);
        $family->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Family updated successfully',
            'data' => $family->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $family = Family::findOrFail($id);

        // Check if family has students
        if ($family->students()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete family with associated students',
            ], 422);
        }

        $family->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Family deleted successfully',
        ]);
    }
}
