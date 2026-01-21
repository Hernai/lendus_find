<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\StaffAccount;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff User Management Controller (v2).
 *
 * Manages staff accounts (analysts, supervisors, admins) using
 * the new StaffAccount/StaffProfile models.
 */
class UserController extends Controller
{
    use ApiResponses;
    /**
     * List all staff users.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = app('tenant.id');

        $query = StaffAccount::where('tenant_id', $tenantId)
            ->with('profile');

        // Filter by role
        if ($role = $request->input('role')) {
            $query->where('role', strtoupper($role));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search
        if ($search = $request->input('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $search = mb_substr($search, 0, 100);

            $query->where(function ($q) use ($search) {
                $q->where('email', 'ILIKE', "%{$search}%")
                    ->orWhereHas('profile', function ($pq) use ($search) {
                        $pq->where('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('phone', 'ILIKE', "%{$search}%");
                    });
            });
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success([
            'users' => $paginated->map(fn($account) => $this->formatStaffAccount($account)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    /**
     * Create a new staff user.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = app('tenant.id');
        $currentUser = $request->user();

        // Format phone before validation
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : null;

        $validator = Validator::make(array_merge($request->all(), ['phone' => $phone]), [
            'email' => [
                'required',
                'email',
                Rule::unique('staff_accounts', 'email'),
            ],
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'last_name_2' => 'nullable|string|max:50',
            'phone' => 'nullable|string|size:10',
            'role' => ['required', Rule::in(StaffAccount::ROLES)],
            'password' => 'nullable|string|min:8',
            'title' => 'nullable|string|max:100',
        ], [
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        // Generate password if not provided
        $password = $request->password ?? Str::random(12);

        try {
            DB::beginTransaction();

            // Create account
            $account = StaffAccount::create([
                'tenant_id' => $tenantId,
                'email' => $request->email,
                'password' => Hash::make($password),
                'role' => $request->role,
                'is_active' => true,
                // Only set created_by if current user is a StaffAccount
                'created_by' => $currentUser instanceof StaffAccount ? $currentUser->id : null,
            ]);

            // Create profile
            StaffProfile::create([
                'account_id' => $account->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'last_name_2' => $request->last_name_2,
                'phone' => $phone,
                'title' => $request->title,
            ]);

            DB::commit();

            $account->load('profile');

            return $this->created([
                'user' => $this->formatStaffAccount($account),
                'temporary_password' => $request->password ? null : $password,
            ], 'Usuario creado');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a staff user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenantId = app('tenant.id');
        $currentUser = $request->user();

        $account = StaffAccount::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with('profile')
            ->first();

        if (!$account) {
            return $this->notFound('Usuario no encontrado');
        }

        // Prevent self-demotion
        if ($currentUser && $account->id === $currentUser->id && $request->has('role') && $request->role !== $account->role) {
            return $this->badRequest('SELF_ROLE_CHANGE', 'No puedes cambiar tu propio rol');
        }

        // Format phone before validation
        $phone = $request->phone ? preg_replace('/\D/', '', $request->phone) : null;

        $validator = Validator::make(array_merge($request->all(), ['phone' => $phone]), [
            'email' => [
                'sometimes',
                'email',
                Rule::unique('staff_accounts', 'email')->ignore($account->id),
            ],
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'last_name_2' => 'nullable|string|max:50',
            'phone' => 'nullable|string|size:10',
            'role' => ['sometimes', Rule::in(StaffAccount::ROLES)],
            'password' => 'nullable|string|min:8',
            'is_active' => 'sometimes|boolean',
            'title' => 'nullable|string|max:100',
        ], [
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Update account
            $accountData = [];
            if ($request->has('email')) {
                $accountData['email'] = $request->email;
            }
            if ($request->has('role')) {
                $accountData['role'] = $request->role;
            }
            if ($request->has('is_active')) {
                $accountData['is_active'] = $request->boolean('is_active');
            }
            if ($request->filled('password')) {
                $accountData['password'] = Hash::make($request->password);
            }
            if (!empty($accountData)) {
                // Only set updated_by if current user is a StaffAccount
                if ($currentUser instanceof StaffAccount) {
                    $accountData['updated_by'] = $currentUser->id;
                }
                $account->update($accountData);
            }

            // Update profile
            $profileData = [];
            if ($request->has('first_name')) {
                $profileData['first_name'] = $request->first_name;
            }
            if ($request->has('last_name')) {
                $profileData['last_name'] = $request->last_name;
            }
            if ($request->has('last_name_2')) {
                $profileData['last_name_2'] = $request->last_name_2;
            }
            if ($request->has('phone')) {
                $profileData['phone'] = $phone;
            }
            if ($request->has('title')) {
                $profileData['title'] = $request->title;
            }

            if (!empty($profileData)) {
                if ($account->profile) {
                    $account->profile->update($profileData);
                } else {
                    StaffProfile::create(array_merge($profileData, [
                        'account_id' => $account->id,
                    ]));
                }
            }

            DB::commit();

            $account->refresh();
            $account->load('profile');

            return $this->success([
                'user' => $this->formatStaffAccount($account),
            ], 'Usuario actualizado');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a staff user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = app('tenant.id');
        $currentUser = $request->user();

        $account = StaffAccount::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$account) {
            return $this->notFound('Usuario no encontrado');
        }

        // Prevent self-deletion
        if ($currentUser && $account->id === $currentUser->id) {
            return $this->badRequest('SELF_DELETE', 'No puedes eliminar tu propia cuenta');
        }

        // TODO: Check if user has assigned applications once we migrate applications to V2

        $account->delete();

        return $this->success(null, 'Usuario eliminado');
    }

    /**
     * Format staff account for API response.
     */
    private function formatStaffAccount(StaffAccount $account, bool $includeStats = false): array
    {
        $profile = $account->profile;

        $data = [
            'id' => $account->id,
            'email' => $account->email,
            'role' => $account->role,
            'is_active' => $account->is_active,
            'last_login_at' => $account->last_login_at?->toIso8601String(),
            'created_at' => $account->created_at->toIso8601String(),
            'updated_at' => $account->updated_at->toIso8601String(),
            // Profile data flattened for backwards compatibility
            'name' => $profile?->full_name ?? $account->email,
            'first_name' => $profile?->first_name,
            'last_name' => $profile?->last_name,
            'last_name_2' => $profile?->last_name_2,
            'phone' => $profile?->phone,
            'title' => $profile?->title,
            'initials' => $profile?->initials ?? strtoupper(substr($account->email, 0, 2)),
        ];

        if ($includeStats) {
            // TODO: Add stats when applications are migrated to V2
            $data['stats'] = [
                'total_assigned' => 0,
                'pending_review' => 0,
            ];
            $data['permissions'] = $account->getPermissionsArray();
        }

        return $data;
    }
}
