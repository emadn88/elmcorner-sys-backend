<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'timezone' => $user->timezone,
                'status' => $user->status,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'roles' => $user->getRoleNames()->toArray(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'whatsapp' => $validated['whatsapp'] ?? null,
            'timezone' => $validated['timezone'] ?? 'UTC',
            'status' => $validated['status'],
        ]);

        // Assign role using Spatie
        $role = Role::where('name', $validated['role'])->first();
        if ($role) {
            $user->assignRole($role);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'description' => "User {$user->name} was created with role {$validated['role']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $user = User::with('roles')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'whatsapp' => $user->whatsapp,
                'timezone' => $user->timezone,
                'status' => $user->status,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'roles' => $user->getRoleNames()->toArray(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        // Update user fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (isset($validated['whatsapp'])) {
            $user->whatsapp = $validated['whatsapp'];
        }
        if (isset($validated['timezone'])) {
            $user->timezone = $validated['timezone'];
        }
        if (isset($validated['status'])) {
            $user->status = $validated['status'];
        }

        // Update role if provided
        if (isset($validated['role'])) {
            $oldRole = $user->role;
            $user->role = $validated['role'];
            
            // Sync Spatie role
            $role = Role::where('name', $validated['role'])->first();
            if ($role) {
                $user->syncRoles([$role]);
            }
        }

        $user->save();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "User {$user->name} was updated",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $userName = $user->name;
        $user->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'description' => "User {$userName} was deleted",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Update user status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $user = User::findOrFail($id);
        $oldStatus = $user->status;
        $user->status = $request->status;
        $user->save();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "User {$user->name} status changed from {$oldStatus} to {$request->status}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User status updated successfully.',
            'data' => [
                'id' => $user->id,
                'status' => $user->status,
            ],
        ]);
    }
}
