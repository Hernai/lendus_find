<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * List all users/agents.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $query = User::where('tenant_id', $tenant->id);

        // Filter by role
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->get();

        return response()->json([
            'data' => $users->map(fn($u) => $this->formatUser($u))
        ]);
    }

    /**
     * Create a new user/agent.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:ADMIN,AGENT,ANALYST,VIEWER',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate password if not provided
        $password = $request->password ?? Str::random(12);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'User created',
            'data' => $this->formatUser($user),
            'temporary_password' => $request->password ? null : $password, // Only show if auto-generated
        ], 201);
    }

    /**
     * Get a specific user.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($user->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Get additional stats
        $user->loadCount(['assignedApplications as total_assigned']);

        return response()->json([
            'data' => array_merge($this->formatUser($user), [
                'stats' => [
                    'total_assigned' => $user->total_assigned ?? 0,
                    'pending_review' => $user->assignedApplications()
                        ->whereIn('status', ['IN_REVIEW', 'DOCS_PENDING'])
                        ->count(),
                ]
            ])
        ]);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($user->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent self-demotion
        if ($user->id === $request->user()->id && $request->has('role') && $request->role !== 'ADMIN') {
            return response()->json([
                'message' => 'You cannot change your own admin role'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:ADMIN,AGENT,ANALYST,VIEWER',
            'password' => 'nullable|string|min:8',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->fill($request->only(['name', 'email', 'phone', 'role', 'is_active']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated',
            'data' => $this->formatUser($user->fresh())
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($user->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        // Check if user has assigned applications
        if ($user->assignedApplications()->exists()) {
            return response()->json([
                'message' => 'Cannot delete user with assigned applications. Reassign or deactivate instead.'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted'
        ]);
    }

    /**
     * Format user for response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => $user->is_active ?? true,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }
}
