<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncPermissionsRequest;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            $userCount = User::where('role', $role->name)->count();
            
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $userCount,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'create',
            'description' => "Role {$role->name} was created",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => [],
                'permissions_count' => 0,
                'users_count' => 0,
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);
        $userCount = User::where('role', $role->name)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permissions_count' => $role->permissions->count(),
                'users_count' => $userCount,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $id],
        ]);

        $role = Role::findOrFail($id);
        $oldName = $role->name;
        $role->name = $request->name;
        $role->save();

        // Update user role field if role name changed
        if ($oldName !== $request->name) {
            User::where('role', $oldName)->update(['role' => $request->name]);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "Role {$oldName} was renamed to {$request->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permissions_count' => $role->permissions->count(),
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        
        // Check if role has users assigned
        $userCount = User::where('role', $role->name)->count();
        if ($userCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot delete role. {$userCount} user(s) are assigned to this role.",
            ], 422);
        }

        $roleName = $role->name;
        $role->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'delete',
            'description' => "Role {$roleName} was deleted",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * Sync permissions for a role.
     */
    public function syncPermissions(SyncPermissionsRequest $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::whereIn('name', $request->permissions)->get();
        
        $role->syncPermissions($permissions);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'update',
            'description' => "Permissions updated for role {$role->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions updated successfully.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permissions_count' => $role->permissions->count(),
            ],
        ]);
    }

    /**
     * Get all available permissions grouped by module.
     */
    public function getPermissions(): JsonResponse
    {
        $permissionsConfig = config('permissions');
        $allPermissions = Permission::all()->pluck('name')->toArray();

        $groupedPermissions = [];
        foreach ($permissionsConfig as $module => $modulePermissions) {
            $groupedPermissions[$module] = array_filter($modulePermissions, function ($perm) use ($allPermissions) {
                return in_array($perm, $allPermissions);
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'grouped' => $groupedPermissions,
                'all' => $allPermissions,
            ],
        ]);
    }

    /**
     * Get page to permission mapping.
     */
    public function getPagePermissions(): JsonResponse
    {
        $pagePermissions = config('page-permissions', []);

        return response()->json([
            'status' => 'success',
            'data' => $pagePermissions,
        ]);
    }
}
