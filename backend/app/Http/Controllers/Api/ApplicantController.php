<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Applicant;
use App\Models\BankAccount;
use App\Models\EmploymentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApplicantController extends Controller
{
    /**
     * Get the current user's applicant profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'Applicant profile not found',
                'data' => null
            ], 404);
        }

        $applicant->load(['addresses', 'currentEmployment', 'primaryBankAccount']);

        return response()->json([
            'data' => $this->formatApplicant($applicant)
        ]);
    }

    /**
     * Create a new applicant profile for the current user.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->applicant) {
            return response()->json([
                'message' => 'Applicant profile already exists'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:PERSONA_FISICA,PERSONA_MORAL',
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $applicant = Applicant::create([
            'id' => Str::uuid(),
            'tenant_id' => $request->attributes->get('tenant')->id,
            'user_id' => $user->id,
            'type' => $request->input('type', 'PERSONA_FISICA'),
            'phone' => $user->phone,
            'email' => $user->email,
            'first_name' => $request->input('first_name'),
            'last_name_1' => $request->input('last_name_1'),
            'last_name_2' => $request->input('last_name_2'),
            'kyc_status' => 'PENDING',
        ]);

        return response()->json([
            'message' => 'Applicant profile created',
            'data' => $this->formatApplicant($applicant)
        ], 201);
    }

    /**
     * Update the applicant profile.
     */
    public function update(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'Applicant profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'curp' => 'sometimes|string|size:18',
            'rfc' => 'sometimes|string|min:12|max:13',
            'ine_clave' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'birth_date' => 'sometimes|date',
            'gender' => 'sometimes|in:M,F,O',
            'marital_status' => 'sometimes|string|max:20',
            'phone' => 'sometimes|string|max:15',
            'phone_secondary' => 'nullable|string|max:15',
            'email' => 'sometimes|email',
            'education_level' => 'nullable|string|max:50',
            'dependents_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $applicant->fill($request->only([
            'curp', 'rfc', 'ine_clave',
            'first_name', 'last_name_1', 'last_name_2',
            'birth_date', 'gender', 'marital_status',
            'phone', 'phone_secondary', 'email',
            'education_level', 'dependents_count'
        ]));

        $applicant->save();

        return response()->json([
            'message' => 'Applicant profile updated',
            'data' => $this->formatApplicant($applicant->fresh())
        ]);
    }

    /**
     * Update personal data step (Step 1 & 2).
     */
    public function updatePersonalData(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        // All fields optional to support partial updates (step 1 = personal, step 2 = identification)
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'nullable|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            // Legacy field mapping support
            'last_name' => 'nullable|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'birth_date' => 'sometimes|date|before:-18 years',
            'gender' => 'sometimes|in:M,F,O',
            'marital_status' => 'sometimes|string|max:20',
            'nationality' => 'sometimes|string|max:50',
            'birth_state' => 'nullable|string|max:50',
            'curp' => 'nullable|string|size:18',
            'rfc' => 'nullable|string|min:12|max:13',
            // INE fields
            'ine_clave' => 'nullable|string|max:20',
            'ine_ocr' => 'nullable|string|max:15',
            'ine_folio' => 'nullable|string|max:25',
            // Passport fields
            'passport_number' => 'nullable|string|max:15',
            'passport_issue_date' => 'nullable|date',
            'passport_expiry_date' => 'nullable|date|after:today',
            'email' => 'nullable|email',
            'education_level' => 'nullable|string|max:50',
            'dependents_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Only update fields that are provided (supports partial updates)
        if ($request->has('first_name')) {
            $applicant->first_name = $request->first_name;
        }
        // Support both field naming conventions
        if ($request->has('last_name_1') || $request->has('last_name')) {
            $applicant->last_name_1 = $request->last_name_1 ?? $request->last_name;
        }
        if ($request->has('last_name_2') || $request->has('second_last_name')) {
            $applicant->last_name_2 = $request->last_name_2 ?? $request->second_last_name;
        }
        if ($request->has('birth_date')) {
            $applicant->birth_date = $request->birth_date;
        }
        if ($request->has('gender')) {
            $applicant->gender = $request->gender;
        }
        if ($request->has('marital_status')) {
            $applicant->marital_status = $request->marital_status;
        }
        if ($request->has('nationality')) {
            $applicant->nationality = $request->nationality;
        }
        if ($request->has('birth_state')) {
            $applicant->birth_state = $request->birth_state;
        }
        if ($request->filled('curp')) {
            $applicant->curp = strtoupper($request->curp);
        }
        if ($request->filled('rfc')) {
            $applicant->rfc = strtoupper($request->rfc);
        }
        if ($request->has('ine_clave')) {
            $applicant->ine_clave = $request->ine_clave;
        }
        if ($request->has('ine_ocr')) {
            $applicant->ine_ocr = $request->ine_ocr;
        }
        if ($request->has('ine_folio')) {
            $applicant->ine_folio = $request->ine_folio;
        }
        if ($request->has('passport_number')) {
            $applicant->passport_number = $request->passport_number;
        }
        if ($request->has('passport_issue_date')) {
            $applicant->passport_issue_date = $request->passport_issue_date;
        }
        if ($request->has('passport_expiry_date')) {
            $applicant->passport_expiry_date = $request->passport_expiry_date;
        }
        if ($request->filled('email')) {
            $applicant->email = $request->email;
        }
        if ($request->has('education_level')) {
            $applicant->education_level = $request->education_level;
        }
        if ($request->has('dependents_count')) {
            $applicant->dependents_count = $request->dependents_count;
        }

        // Generate full name if name fields exist
        if ($applicant->first_name) {
            $applicant->full_name = trim("{$applicant->first_name} {$applicant->last_name_1} {$applicant->last_name_2}");
        }

        $applicant->save();

        // Sync data to users table
        $user = $request->user();
        $userUpdates = [];

        if ($applicant->first_name) {
            $userUpdates['first_name'] = $applicant->first_name;
        }
        if ($applicant->last_name_1 || $applicant->last_name_2) {
            $userUpdates['last_name'] = trim("{$applicant->last_name_1} {$applicant->last_name_2}");
        }
        if ($applicant->full_name) {
            $userUpdates['name'] = $applicant->full_name;
        }
        if ($applicant->email && $applicant->email !== $user->email) {
            $userUpdates['email'] = $applicant->email;
        }

        if (!empty($userUpdates)) {
            $user->update($userUpdates);
        }

        return response()->json([
            'message' => 'Personal data updated',
            'data' => $this->formatApplicant($applicant->fresh())
        ]);
    }

    /**
     * Update address step (Step 3).
     */
    public function updateAddress(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'street' => 'required|string|max:200',
            'ext_number' => 'required|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => 'required|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'required|integer|min:0',
            'months_at_address' => 'required|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update or create primary home address
        $address = $applicant->addresses()->where('type', 'HOME')->first();

        $addressData = [
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => 'HOME',
            'is_primary' => true,
            'street' => strtoupper($request->street),
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => strtoupper($request->neighborhood),
            'municipality' => $request->municipality ? strtoupper($request->municipality) : null,
            'postal_code' => $request->postal_code,
            'city' => strtoupper($request->city),
            'state' => strtoupper($request->state),
            'country' => strtoupper($request->input('country', 'MEXICO')),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->housing_type === 'RENTADA' ? $request->monthly_rent : null,
            'years_at_address' => $request->years_at_address,
            'months_at_address' => $request->months_at_address,
        ];

        if ($address) {
            $address->update($addressData);
        } else {
            $addressData['id'] = Str::uuid();
            Address::create($addressData);
        }

        $applicant->load('addresses');

        return response()->json([
            'message' => 'Address updated',
            'data' => $this->formatApplicant($applicant)
        ]);
    }

    /**
     * Update employment info step (Step 4).
     */
    public function updateEmployment(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'employment_type' => 'required|in:EMPLEADO,INDEPENDIENTE,EMPRESARIO,PENSIONADO,ESTUDIANTE,HOGAR,DESEMPLEADO,OTRO',
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'contract_type' => 'nullable|in:INDEFINIDO,TEMPORAL,POR_OBRA,HONORARIOS,COMISION,OTRO',
            'start_date' => 'nullable|date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'sometimes|in:SEMANAL,QUINCENAL,MENSUAL',
            'income_type' => 'nullable|in:NOMINA,HONORARIOS,MIXTO,COMISIONES,NEGOCIO_PROPIO,PENSION,OTRO',
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
            'work_phone' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Deactivate any existing current employment
        $applicant->employmentRecords()->where('is_current', true)->update(['is_current' => false]);

        // Create new current employment
        EmploymentRecord::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'is_current' => true,
            'employment_type' => $request->employment_type,
            'company_name' => $request->company_name ? strtoupper($request->company_name) : null,
            'company_rfc' => $request->company_rfc ? strtoupper($request->company_rfc) : null,
            'company_industry' => $request->company_industry,
            'position' => $request->position ? strtoupper($request->position) : null,
            'department' => $request->department,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', 'MENSUAL'),
            'income_type' => $request->income_type,
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
            'work_phone' => $request->work_phone,
        ]);

        $applicant->load('currentEmployment');

        return response()->json([
            'message' => 'Employment info updated',
            'data' => $this->formatApplicant($applicant)
        ]);
    }

    /**
     * Update bank information.
     */
    public function updateBankInfo(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'account_type' => 'sometimes|in:DEBITO,NOMINA,AHORRO,CHEQUES,INVERSION,OTRO',
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate CLABE
        if (!BankAccount::validateClabe($request->clabe)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['clabe' => ['CLABE inválida']]
            ], 422);
        }

        // Deactivate any existing primary bank account
        $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);

        // Create new primary bank account
        BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => 'BOTH',
            'is_primary' => true,
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', 'DEBITO'),
            'holder_name' => strtoupper($request->holder_name),
            'holder_rfc' => $request->holder_rfc ? strtoupper($request->holder_rfc) : null,
            'is_own_account' => true,
            'is_active' => true,
        ]);

        $applicant->load('primaryBankAccount');

        return response()->json([
            'message' => 'Bank info updated',
            'data' => $this->formatApplicant($applicant)
        ]);
    }

    /**
     * Save applicant signature (Step 8).
     */
    public function saveSignature(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'Applicant profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'signature' => 'required|string', // Base64 PNG
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $applicant->signature_base64 = $request->signature;
        $applicant->signature_date = now();
        $applicant->signature_ip = $request->ip();
        $applicant->save();

        return response()->json([
            'message' => 'Signature saved',
            'data' => [
                'signed_at' => $applicant->signature_date->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get or create applicant profile.
     */
    private function getOrCreateApplicant(Request $request): Applicant
    {
        $user = $request->user();

        if (!$user->applicant) {
            return Applicant::create([
                'id' => Str::uuid(),
                'tenant_id' => app('tenant.id'),
                'user_id' => $user->id,
                'type' => 'PERSONA_FISICA',
                'phone' => $user->phone,
                'email' => $user->email,
                'kyc_status' => 'PENDING',
            ]);
        }

        return $user->applicant;
    }

    /**
     * Alias for updateBankInfo (route compatibility).
     */
    public function updateBankAccount(Request $request): JsonResponse
    {
        return $this->updateBankInfo($request);
    }

    // =========================================================================
    // Addresses Management
    // =========================================================================

    /**
     * List all addresses for the applicant.
     */
    public function listAddresses(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $applicant->addresses->map(fn($a) => $this->formatAddress($a))
        ]);
    }

    /**
     * Add a new address.
     */
    public function storeAddress(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:HOME,WORK,FISCAL,CORRESPONDENCE',
            'is_primary' => 'sometimes|boolean',
            'street' => 'required|string|max:200',
            'ext_number' => 'required|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => 'nullable|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as primary, unset other primaries of same type
        if ($request->input('is_primary', false)) {
            $applicant->addresses()
                ->where('type', $request->type)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $address = Address::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => $request->type,
            'is_primary' => $request->input('is_primary', false),
            'street' => strtoupper($request->street),
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => strtoupper($request->neighborhood),
            'municipality' => $request->municipality ? strtoupper($request->municipality) : null,
            'postal_code' => $request->postal_code,
            'city' => strtoupper($request->city),
            'state' => strtoupper($request->state),
            'country' => strtoupper($request->input('country', 'MEXICO')),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->monthly_rent,
            'years_at_address' => $request->years_at_address,
            'months_at_address' => $request->months_at_address,
        ]);

        return response()->json([
            'message' => 'Address added',
            'data' => $this->formatAddress($address)
        ], 201);
    }

    /**
     * Update an address by ID.
     */
    public function updateAddressById(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:HOME,WORK,FISCAL,CORRESPONDENCE',
            'is_primary' => 'sometimes|boolean',
            'street' => 'sometimes|string|max:200',
            'ext_number' => 'sometimes|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'sometimes|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'sometimes|string|size:5',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => 'nullable|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $address->fill($request->only([
            'type', 'is_primary', 'street', 'ext_number', 'int_number',
            'neighborhood', 'municipality', 'postal_code', 'city', 'state', 'country',
            'housing_type', 'monthly_rent', 'years_at_address', 'months_at_address'
        ]));
        $address->save();

        return response()->json([
            'message' => 'Address updated',
            'data' => $this->formatAddress($address)
        ]);
    }

    /**
     * Delete an address.
     */
    public function destroyAddress(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted']);
    }

    // =========================================================================
    // Employment Records Management
    // =========================================================================

    /**
     * List all employment records for the applicant.
     */
    public function listEmploymentRecords(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $applicant->employmentRecords->map(fn($e) => $this->formatEmployment($e))
        ]);
    }

    /**
     * Add a new employment record.
     */
    public function storeEmploymentRecord(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'is_current' => 'sometimes|boolean',
            'employment_type' => 'required|in:EMPLEADO,INDEPENDIENTE,EMPRESARIO,PENSIONADO,ESTUDIANTE,HOGAR,DESEMPLEADO,OTRO',
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'contract_type' => 'nullable|in:INDEFINIDO,TEMPORAL,POR_OBRA,HONORARIOS,COMISION,OTRO',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'sometimes|in:SEMANAL,QUINCENAL,MENSUAL',
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // If setting as current, unset other current records
        if ($request->input('is_current', false)) {
            $applicant->employmentRecords()->where('is_current', true)->update(['is_current' => false]);
        }

        $employment = EmploymentRecord::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'is_current' => $request->input('is_current', false),
            'employment_type' => $request->employment_type,
            'company_name' => $request->company_name ? strtoupper($request->company_name) : null,
            'company_rfc' => $request->company_rfc ? strtoupper($request->company_rfc) : null,
            'company_industry' => $request->company_industry,
            'position' => $request->position ? strtoupper($request->position) : null,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', 'MENSUAL'),
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
        ]);

        return response()->json([
            'message' => 'Employment record added',
            'data' => $this->formatEmployment($employment)
        ], 201);
    }

    // =========================================================================
    // Bank Accounts Management
    // =========================================================================

    /**
     * List all bank accounts for the applicant.
     */
    public function listBankAccounts(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $applicant->bankAccounts->where('is_active', true)->map(fn($b) => $this->formatBankAccount($b))
        ]);
    }

    /**
     * Add a new bank account.
     */
    public function storeBankAccount(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:DISBURSEMENT,PAYMENT,BOTH',
            'is_primary' => 'sometimes|boolean',
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'account_type' => 'sometimes|in:DEBITO,NOMINA,AHORRO,CHEQUES,INVERSION,OTRO',
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
            'is_own_account' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate CLABE
        if (!BankAccount::validateClabe($request->clabe)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['clabe' => ['CLABE inválida']]
            ], 422);
        }

        // If setting as primary, unset other primaries
        if ($request->input('is_primary', false)) {
            $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $bankAccount = BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => $request->input('type', 'BOTH'),
            'is_primary' => $request->input('is_primary', false),
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', 'DEBITO'),
            'holder_name' => strtoupper($request->holder_name),
            'holder_rfc' => $request->holder_rfc ? strtoupper($request->holder_rfc) : null,
            'is_own_account' => $request->input('is_own_account', true),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Bank account added',
            'data' => $this->formatBankAccount($bankAccount)
        ], 201);
    }

    // =========================================================================
    // Utilities
    // =========================================================================

    /**
     * Validate a CLABE number.
     */
    public function validateClabe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clabe' => 'required|string|size:18',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'data' => ['valid' => false]
            ]);
        }

        $valid = BankAccount::validateClabe($request->clabe);
        $bankName = null;

        if ($valid) {
            $bankCode = substr($request->clabe, 0, 3);
            $bankName = BankAccount::MEXICAN_BANKS[$bankCode] ?? null;
        }

        return response()->json([
            'data' => [
                'valid' => $valid,
                'bank_name' => $bankName,
            ]
        ]);
    }

    // =========================================================================
    // Format Helpers
    // =========================================================================

    /**
     * Format address for response.
     */
    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'is_primary' => $address->is_primary,
            'street' => $address->street,
            'ext_number' => $address->ext_number,
            'int_number' => $address->int_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'state' => $address->state,
            'country' => $address->country,
            'full_address' => $address->full_address,
            'housing_type' => $address->housing_type,
            'housing_type_label' => $address->housing_type_label,
            'monthly_rent' => $address->monthly_rent,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'is_verified' => $address->is_verified,
            'created_at' => $address->created_at->toIso8601String(),
            'updated_at' => $address->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format employment for response.
     */
    private function formatEmployment(EmploymentRecord $employment): array
    {
        return [
            'id' => $employment->id,
            'is_current' => $employment->is_current,
            'employment_type' => $employment->employment_type,
            'employment_type_label' => $employment->employment_type_label,
            'company_name' => $employment->company_name,
            'company_rfc' => $employment->company_rfc,
            'company_industry' => $employment->company_industry,
            'position' => $employment->position,
            'department' => $employment->department,
            'contract_type' => $employment->contract_type,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'end_date' => $employment->end_date?->format('Y-m-d'),
            'seniority_months' => $employment->seniority_months,
            'monthly_income' => $employment->monthly_income,
            'monthly_net_income' => $employment->monthly_net_income,
            'payment_frequency' => $employment->payment_frequency,
            'income_type' => $employment->income_type,
            'other_income' => $employment->other_income,
            'other_income_source' => $employment->other_income_source,
            'total_monthly_income' => $employment->total_monthly_income,
            'is_verified' => $employment->is_verified,
            'created_at' => $employment->created_at->toIso8601String(),
            'updated_at' => $employment->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format bank account for response.
     */
    private function formatBankAccount(BankAccount $bankAccount): array
    {
        return [
            'id' => $bankAccount->id,
            'type' => $bankAccount->type,
            'is_primary' => $bankAccount->is_primary,
            'bank_name' => $bankAccount->bank_name,
            'bank_code' => $bankAccount->bank_code,
            'clabe' => $bankAccount->masked_clabe,
            'clabe_last4' => substr($bankAccount->clabe, -4),
            'account_number' => $bankAccount->account_number,
            'account_type' => $bankAccount->account_type,
            'account_type_label' => $bankAccount->account_type_label,
            'holder_name' => $bankAccount->holder_name,
            'holder_rfc' => $bankAccount->holder_rfc,
            'is_own_account' => $bankAccount->is_own_account,
            'is_verified' => $bankAccount->is_verified,
            'is_active' => $bankAccount->is_active,
            'created_at' => $bankAccount->created_at->toIso8601String(),
            'updated_at' => $bankAccount->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format applicant for response.
     */
    private function formatApplicant(Applicant $applicant): array
    {
        $address = $applicant->addresses->where('type', 'HOME')->first();
        $employment = $applicant->currentEmployment ?? $applicant->employmentRecords->where('is_current', true)->first();
        $bankAccount = $applicant->primaryBankAccount ?? $applicant->bankAccounts->where('is_primary', true)->first();

        return [
            'id' => $applicant->id,
            'type' => $applicant->type,
            'curp' => $applicant->curp,
            'rfc' => $applicant->rfc,
            'ine_clave' => $applicant->ine_clave,
            'ine_ocr' => $applicant->ine_ocr,
            'ine_folio' => $applicant->ine_folio,
            'passport_number' => $applicant->passport_number,
            'passport_issue_date' => $applicant->passport_issue_date?->format('Y-m-d'),
            'passport_expiry_date' => $applicant->passport_expiry_date?->format('Y-m-d'),
            'full_name' => $applicant->full_name,
            'first_name' => $applicant->first_name,
            'last_name_1' => $applicant->last_name_1,
            'last_name_2' => $applicant->last_name_2,
            'birth_date' => $applicant->birth_date?->format('Y-m-d'),
            'age' => $applicant->age,
            'gender' => $applicant->gender,
            'gender_label' => $applicant->gender_label,
            'marital_status' => $applicant->marital_status,
            'marital_status_label' => $applicant->marital_status_label,
            'nationality' => $applicant->nationality,
            'birth_state' => $applicant->birth_state,
            'phone' => $applicant->phone,
            'phone_secondary' => $applicant->phone_secondary,
            'email' => $applicant->email,
            'education_level' => $applicant->education_level,
            'education_level_label' => $applicant->education_level_label,
            'dependents_count' => $applicant->dependents_count,
            'address' => $address ? [
                'id' => $address->id,
                'street' => $address->street,
                'ext_number' => $address->ext_number,
                'int_number' => $address->int_number,
                'neighborhood' => $address->neighborhood,
                'municipality' => $address->municipality,
                'postal_code' => $address->postal_code,
                'city' => $address->city,
                'state' => $address->state,
                'country' => $address->country,
                'full_address' => $address->full_address,
                'housing_type' => $address->housing_type,
                'housing_type_label' => $address->housing_type_label,
                'monthly_rent' => $address->monthly_rent,
                'years_at_address' => $address->years_at_address,
                'months_at_address' => $address->months_at_address,
                'is_verified' => $address->is_verified,
            ] : null,
            'employment' => $employment ? [
                'id' => $employment->id,
                'employment_type' => $employment->employment_type,
                'employment_type_label' => $employment->employment_type_label,
                'company_name' => $employment->company_name,
                'company_rfc' => $employment->company_rfc,
                'company_industry' => $employment->company_industry,
                'position' => $employment->position,
                'department' => $employment->department,
                'contract_type' => $employment->contract_type,
                'start_date' => $employment->start_date?->format('Y-m-d'),
                'seniority_months' => $employment->seniority_months,
                'monthly_income' => $employment->monthly_income,
                'monthly_net_income' => $employment->monthly_net_income,
                'payment_frequency' => $employment->payment_frequency,
                'income_type' => $employment->income_type,
                'other_income' => $employment->other_income,
                'other_income_source' => $employment->other_income_source,
                'total_monthly_income' => $employment->total_monthly_income,
                'work_phone' => $employment->work_phone,
                'is_verified' => $employment->is_verified,
            ] : null,
            'bank_account' => $bankAccount ? [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'clabe_masked' => $bankAccount->masked_clabe,
                'account_type' => $bankAccount->account_type,
                'account_type_label' => $bankAccount->account_type_label,
                'holder_name' => $bankAccount->holder_name,
                'is_verified' => $bankAccount->is_verified,
            ] : null,
            'kyc_status' => $applicant->kyc_status,
            'has_signed' => $applicant->hasSigned(),
            'signed_at' => $applicant->signature_date?->toIso8601String(),
            'completeness_percent' => $applicant->completeness_percent,
            'completeness_details' => $applicant->completeness_details,
            'created_at' => $applicant->created_at->toIso8601String(),
            'updated_at' => $applicant->updated_at->toIso8601String(),
        ];
    }
}
