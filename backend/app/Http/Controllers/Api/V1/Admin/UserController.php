<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users/agents.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $query = User::where('tenant_id', $tenant->id);

        // Always exclude APPLICANT users - this endpoint is for staff management
        $query->where('type', '!=', UserType::APPLICANT);

        // Filter by type/role
        if ($type = $request->input('role')) {
            $query->where('type', $type);
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

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $paginated = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn($u) => $this->formatUser($u)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    /**
     * Create a new user/agent.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Format phone before validation (remove non-digits)
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : null;

        $validator = Validator::make(array_merge($request->all(), ['phone' => $phone]), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|size:10|unique:users,phone',
            'role' => ['required', Rule::in(UserType::staffValues())],
            'password' => 'nullable|string|min:8',
        ], [
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.unique' => 'Este número de teléfono ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate password if not provided
        $password = $request->password ?? Str::random(12);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'type' => $request->role,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Usuario creado',
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
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Get additional stats
        $user->loadCount(['assignedApplications as total_assigned']);

        return response()->json([
            'data' => array_merge($this->formatUser($user), [
                'stats' => [
                    'total_assigned' => $user->total_assigned ?? 0,
                    'pending_review' => $user->assignedApplications()
                        ->whereIn('status', [ApplicationStatus::IN_REVIEW, ApplicationStatus::DOCS_PENDING])
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
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Prevent self-demotion
        if ($user->id === $request->user()->id && $request->has('role') && $request->role !== 'ADMIN') {
            return response()->json([
                'message' => 'No puedes cambiar tu propio rol de administrador'
            ], 400);
        }

        // Format phone before validation (remove non-digits)
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : null;

        $validator = Validator::make(array_merge($request->all(), ['phone' => $phone]), [
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|size:10|unique:users,phone,' . $user->id,
            'role' => ['sometimes', Rule::in(UserType::staffValues())],
            'password' => 'nullable|string|min:8',
            'is_active' => 'sometimes|boolean',
        ], [
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.unique' => 'Este número de teléfono ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->fill($request->only(['name', 'email', 'is_active']));
        $user->phone = $phone;

        // Handle type/role separately
        if ($request->has('role')) {
            $user->type = $request->role;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado',
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
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta'
            ], 400);
        }

        // Check if user has assigned applications
        if ($user->assignedApplications()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un usuario con solicitudes asignadas. Reasigna o desactiva la cuenta.'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado'
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
            'role' => $user->type?->value ?? $user->type,
            'is_active' => $user->is_active ?? true,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }
}
