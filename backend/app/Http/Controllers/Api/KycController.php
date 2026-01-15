<?php

namespace App\Http\Controllers\Api;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\DataVerification;
use App\Services\ExternalApi\NubariumService;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * KYC Controller for identity validation services.
 *
 * Exposes endpoints for CURP, RFC, INE validation and more.
 * Uses the tenant's configured KYC provider (Nubarium, Mati, etc.)
 */
class KycController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Get available KYC services for the current tenant.
     */
    public function services(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        // Check which providers are configured
        $nubariumService = new NubariumService($tenant);

        $services = [
            'nubarium' => [
                'configured' => $nubariumService->isConfigured(),
                'services' => $nubariumService->isConfigured() ? $nubariumService->getAvailableServices() : [],
            ],
        ];

        return response()->json([
            'data' => $services,
            'birth_states' => NubariumService::BIRTH_STATES,
        ]);
    }

    /**
     * Test KYC service connection and refresh token if needed.
     * This endpoint forces a token refresh and tests the connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio de validación no configurado',
                'configured' => false,
            ], 503);
        }

        // Clear token cache and test connection
        $result = $service->testConnection();

        // Audit log
        $this->logKycAction($request, 'connection_test', [
            'success' => $result['success'],
            'message' => $result['message'],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'configured' => true,
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Force refresh the Nubarium JWT token.
     * Useful when the token has expired or is about to expire.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        // Force refresh the token
        $newToken = $service->refreshToken();

        // Audit log
        $this->logKycAction($request, 'token_refresh', [
            'success' => $newToken !== null,
        ]);

        if ($newToken) {
            return response()->json([
                'success' => true,
                'message' => 'Token renovado exitosamente',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al renovar el token. Verifique las credenciales configuradas.',
        ], 400);
    }

    /**
     * Validate CURP with RENAPO.
     */
    public function validateCurp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'curp' => 'required|string|size:18|alpha_num',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->validateCurp($request->curp);

        // Audit log
        $this->logKycAction($request, 'curp_validation', [
            'curp' => $this->maskCurp($request->curp),
            'success' => $result['success'] ?? false,
            'valid' => $result['valid'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al validar CURP',
                'provider_error' => $result['provider_error'] ?? null,
            ], $result['status_code'] ?? 400);
        }

        // Auto-register verifications if user has an applicant and CURP is valid
        if ($result['valid'] && $request->user()?->applicant) {
            $applicant = $request->user()->applicant;
            $data = $result['data'] ?? [];

            // Register CURP verification
            $this->verificationService->verify(
                $applicant,
                'curp',
                $request->curp,
                VerificationMethod::RENAPO,
                ['renapo_response' => $data]
            );

            // Register personal data from RENAPO response
            if (!empty($data['nombres'])) {
                $this->verificationService->verify($applicant, 'first_name', $data['nombres'], VerificationMethod::RENAPO);
            }
            if (!empty($data['apellido_paterno'])) {
                $this->verificationService->verify($applicant, 'last_name_1', $data['apellido_paterno'], VerificationMethod::RENAPO);
            }
            if (!empty($data['apellido_materno'])) {
                $this->verificationService->verify($applicant, 'last_name_2', $data['apellido_materno'], VerificationMethod::RENAPO);
            }
            if (!empty($data['fecha_nacimiento'])) {
                $this->verificationService->verify($applicant, 'birth_date', $data['fecha_nacimiento'], VerificationMethod::RENAPO);
            }

            // Update KYC status if all critical fields verified
            $this->verificationService->updateKycStatus($applicant);
        }

        return response()->json([
            'message' => 'CURP validado',
            'data' => $result['data'],
            'valid' => $result['valid'],
        ]);
    }

    /**
     * Get CURP by personal data.
     */
    public function getCurp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:50',
            'apellido_materno' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'required|date',
            'sexo' => 'required|string|in:H,M,HOMBRE,MUJER,MASCULINO,FEMENINO',
            'entidad_nacimiento' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->getCurp($request->only([
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'fecha_nacimiento',
            'sexo',
            'entidad_nacimiento',
        ]));

        // Audit log
        $this->logKycAction($request, 'curp_lookup', [
            'nombres' => $request->nombres,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al obtener CURP',
            ], 400);
        }

        // Auto-register verifications if user has an applicant
        if (!empty($result['curp']) && $request->user()?->applicant) {
            $applicant = $request->user()->applicant;
            $data = $result['data'] ?? [];

            // Register CURP verification
            $this->verificationService->verify(
                $applicant,
                'curp',
                $result['curp'],
                VerificationMethod::RENAPO,
                ['renapo_response' => $data]
            );

            // Register personal data from RENAPO response
            if (!empty($data['nombres'])) {
                $this->verificationService->verify($applicant, 'first_name', $data['nombres'], VerificationMethod::RENAPO);
            }
            if (!empty($data['apellido_paterno'])) {
                $this->verificationService->verify($applicant, 'last_name_1', $data['apellido_paterno'], VerificationMethod::RENAPO);
            }
            if (!empty($data['apellido_materno'])) {
                $this->verificationService->verify($applicant, 'last_name_2', $data['apellido_materno'], VerificationMethod::RENAPO);
            }
            if (!empty($data['fecha_nacimiento'])) {
                $this->verificationService->verify($applicant, 'birth_date', $data['fecha_nacimiento'], VerificationMethod::RENAPO);
            }

            // Update KYC status if all critical fields verified
            $this->verificationService->updateKycStatus($applicant);
        }

        return response()->json([
            'message' => 'CURP encontrado',
            'data' => $result['data'],
            'curp' => $result['curp'],
        ]);
    }

    /**
     * Validate RFC with SAT.
     */
    public function validateRfc(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rfc' => 'required|string|min:12|max:13|alpha_num',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->validateRfc($request->rfc);

        // Audit log
        $this->logKycAction($request, 'rfc_validation', [
            'rfc' => $this->maskRfc($request->rfc),
            'success' => $result['success'] ?? false,
            'valid' => $result['valid'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al validar RFC',
                'provider_error' => $result['provider_error'] ?? null,
            ], $result['status_code'] ?? 400);
        }

        // Auto-register RFC verification if user has an applicant and RFC is valid
        if ($result['valid'] && $request->user()?->applicant) {
            $applicant = $request->user()->applicant;

            $this->verificationService->verify(
                $applicant,
                'rfc',
                $request->rfc,
                VerificationMethod::SAT,
                ['sat_response' => $result['data'] ?? []]
            );
        }

        return response()->json([
            'message' => 'RFC validado',
            'data' => $result['data'],
            'valid' => $result['valid'],
        ]);
    }

    /**
     * Validate INE/IFE with OCR extraction and optional list validation.
     */
    public function validateIne(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'front_image' => 'required|string', // Base64 encoded image
            'back_image' => 'nullable|string',  // Base64 encoded image (recommended)
            'validate_list' => 'nullable|boolean', // Whether to validate against INE list
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->validateIne(
            $request->front_image,
            $request->back_image,
            $request->boolean('validate_list', true)
        );

        // Audit log (don't log images)
        $this->logKycAction($request, 'ine_validation', [
            'success' => $result['success'] ?? false,
            'is_valid' => $result['is_valid'] ?? null,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al validar INE',
                'provider_error' => $result['provider_error'] ?? null,
            ], $result['status_code'] ?? 400);
        }

        // Auto-register INE verification if user has an applicant and INE is valid
        if ($result['is_valid'] && $request->user()?->applicant) {
            $applicant = $request->user()->applicant;
            $ocrData = $result['ocr_data'] ?? [];

            // Verify INE document
            $this->verificationService->verifyIneDocument(
                $applicant,
                'front',
                'ine_ocr_' . now()->timestamp,
                [
                    'curp' => $ocrData['curp'] ?? null,
                    'first_name' => $ocrData['nombres'] ?? null,
                    'last_name_1' => $ocrData['apellido_paterno'] ?? null,
                    'last_name_2' => $ocrData['apellido_materno'] ?? null,
                    'birth_date' => $ocrData['fecha_nacimiento'] ?? null,
                ]
            );

            // Update KYC status if all critical fields verified
            $this->verificationService->updateKycStatus($applicant);
        }

        return response()->json([
            'message' => 'INE procesado',
            'ocr_data' => $result['ocr_data'] ?? null,
            'list_validation' => $result['list_validation'] ?? null,
            'is_valid' => $result['is_valid'] ?? null,
            'validation_code' => $result['validation_code'] ?? null,
        ]);
    }

    /**
     * Get biometric SDK token for frontend.
     */
    public function getBiometricToken(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        // Generate unique transaction ID
        $transactionId = Str::uuid()->toString();

        // If there's an application context, use that ID
        if ($request->has('application_id')) {
            $transactionId = $request->application_id . '_' . time();
        }

        $result = $service->getBiometricToken($transactionId);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al generar token',
            ], 400);
        }

        return response()->json([
            'message' => 'Token generado',
            'data' => [
                'token' => $result['token'],
                'expires_in' => $result['expires_in'],
                'transaction_id' => $transactionId,
            ],
        ]);
    }

    /**
     * Validate SPEI CEP (payment proof).
     */
    public function validateCep(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clave_rastreo' => 'required|string|max:50',
            'fecha_operacion' => 'required|date',
            'monto' => 'required|numeric|min:0.01',
            'cuenta_beneficiario' => 'nullable|string|max:20',
            'cuenta_ordenante' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->validateCep($request->only([
            'clave_rastreo',
            'fecha_operacion',
            'monto',
            'cuenta_beneficiario',
            'cuenta_ordenante',
        ]));

        // Audit log
        $this->logKycAction($request, 'cep_validation', [
            'clave_rastreo' => $request->clave_rastreo,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al validar CEP',
            ], 400);
        }

        return response()->json([
            'message' => 'CEP validado',
            'data' => $result['data'],
            'valid' => $result['valid'],
        ]);
    }

    /**
     * Check OFAC & UN sanctions block lists.
     */
    public function checkOfac(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'similarity' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $similarity = $request->input('similarity', 80);
        $result = $service->checkOfac($request->name, $similarity);

        // Audit log
        $this->logKycAction($request, 'ofac_check', [
            'name' => $request->name,
            'found' => $result['found'] ?? false,
            'count' => $result['count'] ?? 0,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al consultar OFAC',
            ], 400);
        }

        return response()->json([
            'message' => 'Consulta OFAC completada',
            'data' => [
                'found' => $result['found'],
                'matches' => $result['matches'],
                'count' => $result['count'] ?? 0,
                'validation_code' => $result['validation_code'] ?? null,
                'checked_at' => $result['checked_at'],
                'warning' => $result['warning'] ?? null,
            ],
        ]);
    }

    /**
     * Check Mexican PLD (Anti-Money Laundering) blacklists.
     * Includes PGR, PGJ, PEPs, SAT 69/69B, Interpol, DEA, FBI, etc.
     */
    public function checkPldBlacklists(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'curp' => 'nullable|string|size:18',
            'similarity' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $similarity = $request->input('similarity', 80);
        $result = $service->checkPldBlacklists($request->name, $request->curp, $similarity);

        // Audit log
        $this->logKycAction($request, 'pld_blacklists_check', [
            'name' => $request->name,
            'curp' => $request->curp ? $this->maskCurp($request->curp) : null,
            'found' => $result['found'] ?? false,
            'count' => $result['count'] ?? 0,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al consultar listas negras',
            ], 400);
        }

        return response()->json([
            'message' => 'Consulta de listas negras completada',
            'data' => [
                'found' => $result['found'],
                'matches' => $result['matches'],
                'count' => $result['count'] ?? 0,
                'validation_code' => $result['validation_code'] ?? null,
                'checked_at' => $result['checked_at'],
                'warning' => $result['warning'] ?? null,
            ],
        ]);
    }

    /**
     * Get IMSS employment history.
     */
    public function getImssHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'curp' => 'required|string|size:18|alpha_num',
            'nss' => 'nullable|string|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->getImssHistory($request->curp, $request->nss);

        // Audit log
        $this->logKycAction($request, 'imss_history', [
            'curp' => $this->maskCurp($request->curp),
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al consultar IMSS',
            ], 400);
        }

        return response()->json([
            'message' => 'Historial IMSS obtenido',
            'data' => $result['data'],
        ]);
    }

    /**
     * Validate professional license (Cédula Profesional).
     */
    public function validateCedula(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenant = app('tenant');
        $service = new NubariumService($tenant);

        if (!$service->isConfigured()) {
            return response()->json([
                'message' => 'Servicio de validación no configurado',
            ], 503);
        }

        $result = $service->validateCedulaProfesional($request->cedula);

        // Audit log
        $this->logKycAction($request, 'cedula_validation', [
            'cedula' => $request->cedula,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'] ?? 'Error al validar cédula',
            ], 400);
        }

        return response()->json([
            'message' => 'Cédula validada',
            'data' => $result['data'],
            'valid' => $result['valid'],
        ]);
    }

    /**
     * Log KYC action for audit.
     */
    private function logKycAction(Request $request, string $action, array $data): void
    {
        try {
            AuditLog::create([
                'tenant_id' => app('tenant')->id,
                'user_id' => $request->user()?->id,
                'action' => 'kyc.' . $action,
                'entity_type' => 'kyc_validation',
                'entity_id' => null,
                'old_values' => null,
                'new_values' => $data,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the request if audit logging fails
        }
    }

    /**
     * Mask CURP for logging.
     */
    private function maskCurp(string $curp): string
    {
        if (strlen($curp) !== 18) {
            return '****';
        }

        return substr($curp, 0, 4) . '**********' . substr($curp, -4);
    }

    /**
     * Mask RFC for logging.
     */
    private function maskRfc(string $rfc): string
    {
        $length = strlen($rfc);
        if ($length < 12) {
            return '****';
        }

        return substr($rfc, 0, 4) . '****' . substr($rfc, -4);
    }

    // =========================================================================
    // DATA VERIFICATION ENDPOINTS
    // =========================================================================

    /**
     * Record KYC verification results for an applicant.
     *
     * This endpoint should be called after completing KYC verification
     * to persist the verified data in the database.
     */
    public function recordVerifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicant_id' => 'required|uuid',
            'verifications' => 'required|array',
            'verifications.*.field' => 'required|string',
            'verifications.*.value' => 'nullable',
            'verifications.*.method' => 'required|string',
            'verifications.*.verified' => 'boolean',
            'verifications.*.metadata' => 'nullable|array',
            'verifications.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find applicant (with tenant scope)
        $applicant = Applicant::find($request->applicant_id);

        if (!$applicant) {
            return response()->json([
                'message' => 'Solicitante no encontrado',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $recorded = [];

            foreach ($request->verifications as $verification) {
                $field = $verification['field'];
                $value = $verification['value'] ?? null;
                $method = $verification['method'];
                $metadata = $verification['metadata'] ?? null;
                $notes = $verification['notes'] ?? null;

                // Use VerificationService which handles locked fields gracefully
                $record = $this->verificationService->verify(
                    $applicant,
                    $field,
                    $value,
                    $method,
                    $metadata,
                    $notes
                );

                if ($record) {
                    $recorded[] = [
                        'field' => $field,
                        'verified' => $record->is_verified,
                        'locked' => $record->is_locked,
                        'method' => $record->method?->value ?? $record->method,
                    ];
                }
            }

            // Update applicant's kyc_verified_at if all critical fields are verified
            $this->verificationService->updateKycStatus($applicant);

            DB::commit();

            // Audit log
            $this->logKycAction($request, 'verifications_recorded', [
                'applicant_id' => $applicant->id,
                'fields_count' => count($recorded),
            ]);

            return response()->json([
                'message' => 'Verificaciones registradas correctamente',
                'data' => [
                    'recorded' => $recorded,
                    'total' => count($recorded),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al registrar verificaciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all verifications for an applicant.
     */
    public function getVerifications(Request $request, string $applicantId): JsonResponse
    {
        $applicant = Applicant::find($applicantId);

        if (!$applicant) {
            return response()->json([
                'message' => 'Solicitante no encontrado',
            ], 404);
        }

        $verifications = DataVerification::getVerifiedFieldsForApplicant($applicantId);

        // Get all verifications (including pending/rejected)
        $allVerifications = DataVerification::where('applicant_id', $applicantId)
            ->orderBy('field_name')
            ->get()
            ->map(function ($v) {
                return [
                    'field' => $v->field_name,
                    'field_label' => DataVerification::getFieldLabel($v->field_name),
                    'value' => $v->field_value,
                    'method' => $v->method?->value ?? $v->method,
                    'method_label' => $v->method?->label() ?? DataVerification::getMethodLabel($v->method ?? ''),
                    'is_verified' => $v->is_verified,
                    'is_locked' => $v->is_locked ?? false,
                    'status' => $v->status?->value ?? $v->status,
                    'verified_at' => $v->updated_at?->toIso8601String(),
                    'metadata' => $v->metadata,
                    'notes' => $v->notes,
                ];
            });

        // Summary of verified fields by category
        $summary = [
            'personal_data' => [],
            'contact' => [],
            'address' => [],
            'kyc' => [],
        ];

        foreach ($verifications as $field => $data) {
            $fieldEnum = VerifiableField::tryFrom($field);
            if ($fieldEnum) {
                if ($fieldEnum->isPersonalData()) {
                    $summary['personal_data'][$field] = $data;
                } elseif ($fieldEnum->isContactInfo()) {
                    $summary['contact'][$field] = $data;
                } elseif ($fieldEnum->isAddressField()) {
                    $summary['address'][$field] = $data;
                } elseif ($fieldEnum->isKycField()) {
                    $summary['kyc'][$field] = $data;
                }
            }
        }

        return response()->json([
            'data' => [
                'verifications' => $allVerifications,
                'verified_fields' => $verifications,
                'summary' => $summary,
                'kyc_verified' => $applicant->isKycVerified(),
                'kyc_verified_at' => $applicant->kyc_verified_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Check if specific fields are verified for an applicant.
     */
    public function checkFieldsVerified(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicant_id' => 'required|uuid',
            'fields' => 'required|array',
            'fields.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $applicant = Applicant::find($request->applicant_id);

        if (!$applicant) {
            return response()->json([
                'message' => 'Solicitante no encontrado',
            ], 404);
        }

        $results = [];
        foreach ($request->fields as $field) {
            $results[$field] = DataVerification::isFieldVerified($request->applicant_id, $field);
        }

        return response()->json([
            'data' => [
                'fields' => $results,
                'all_verified' => !in_array(false, $results, true),
            ],
        ]);
    }

}
