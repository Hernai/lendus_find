<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\CheckFieldsVerifiedRequest;
use App\Http\Requests\Kyc\CheckOfacRequest;
use App\Http\Requests\Kyc\CheckPldBlacklistsRequest;
use App\Http\Requests\Kyc\GetCurpRequest;
use App\Http\Requests\Kyc\GetImssHistoryRequest;
use App\Http\Requests\Kyc\RecordVerificationsRequest;
use App\Http\Requests\Kyc\ValidateCedulaRequest;
use App\Http\Requests\Kyc\ValidateCepRequest;
use App\Http\Requests\Kyc\ValidateCurpRequest;
use App\Http\Requests\Kyc\ValidateFaceMatchRequest;
use App\Http\Requests\Kyc\ValidateIneRequest;
use App\Http\Requests\Kyc\ValidateLivenessRequest;
use App\Http\Requests\Kyc\ValidateRfcRequest;
use App\Enums\ApplicantType;
use App\Enums\KycStatus;
use App\Helpers\PhoneNormalizer;
use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\AuditLog;
use App\Models\DataVerification;
use App\Models\Document;
use App\Services\ExternalApi\NubariumService;
use App\Services\KycServiceFactory;
use App\Services\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * V2 KYC Controller for applicant identity validation services.
 *
 * This controller handles KYC validation for applicants authenticated
 * via the ApplicantAccount model (V2 authentication system).
 *
 * Key differences from V1:
 * - Uses ApplicantAccount instead of User model
 * - Gets applicant via $request->user()->applicant relationship
 * - Follows V2 response format patterns
 */
class KycController extends Controller
{
    use ApiResponses;

    protected VerificationService $verificationService;
    protected KycServiceFactory $kycFactory;

    public function __construct(
        VerificationService $verificationService,
        KycServiceFactory $kycFactory
    ) {
        $this->verificationService = $verificationService;
        $this->kycFactory = $kycFactory;
    }

    /**
     * Get the KYC service for the current tenant.
     */
    protected function getKycService(?Request $request = null): NubariumService
    {
        $service = $this->kycFactory->forCurrentTenant();

        // Set context for API logging if user is authenticated
        $user = $request?->user();
        if ($user instanceof ApplicantAccount) {
            // V2: Use Person entity for logging context
            if ($user->person) {
                $service->forEntity($user->person)
                    ->forUser($user->id);
            }
        }

        return $service;
    }

    /**
     * Get the applicant from the authenticated user (V2 pattern).
     *
     * This bridges V2 authentication (ApplicantAccount) with V1 data model (Applicant).
     * The ApplicantAccount model has a getApplicantAttribute() accessor that looks up
     * the Applicant by phone, email, or CURP from the account's identities.
     */
    protected function getApplicant(Request $request): ?Applicant
    {
        $user = $request->user();

        if (!($user instanceof ApplicantAccount)) {
            Log::warning('[V2 KycController] User is not an ApplicantAccount', [
                'user_type' => $user ? get_class($user) : 'null',
            ]);
            return null;
        }

        // The applicant accessor in ApplicantAccount looks up by phone/email/CURP
        $applicant = $user->applicant;

        if (!$applicant) {
            // Log detailed info to help debug why applicant wasn't found
            $phoneIdentity = $user->phoneIdentity;
            $emailIdentity = $user->emailIdentity;

            Log::warning('[V2 KycController] Could not find Applicant for ApplicantAccount', [
                'account_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'phone_identity' => $phoneIdentity?->identifier,
                'email_identity' => $emailIdentity?->identifier,
                'person_id' => $user->person_id,
            ]);
        }

        return $applicant;
    }

    /**
     * Get or create applicant for the authenticated user.
     *
     * If no Applicant exists for this ApplicantAccount, creates one automatically
     * using the account's identity information.
     */
    protected function getOrCreateApplicant(Request $request): ?Applicant
    {
        $user = $request->user();

        if (!($user instanceof ApplicantAccount)) {
            return null;
        }

        // First try to find existing applicant
        $applicant = $user->applicant;
        if ($applicant) {
            return $applicant;
        }

        // Auto-create applicant from account identity
        $phoneIdentity = $user->phoneIdentity;
        $emailIdentity = $user->emailIdentity;

        // Normalize phone for storage using helper
        $phone = PhoneNormalizer::normalize($phoneIdentity?->identifier);

        try {
            $applicant = Applicant::create([
                'id' => Str::uuid(),
                'tenant_id' => $user->tenant_id,
                'type' => ApplicantType::INDIVIDUAL->value,
                'phone' => $phone,
                'email' => $emailIdentity?->identifier,
                'kyc_status' => KycStatus::PENDING->value,
            ]);

            Log::info('[V2 KycController] Auto-created Applicant for ApplicantAccount', [
                'account_id' => $user->id,
                'applicant_id' => $applicant->id,
                'phone' => $phone,
            ]);

            return $applicant;
        } catch (\Exception $e) {
            Log::error('[V2 KycController] Failed to auto-create Applicant', [
                'account_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if KYC service is configured, return error response if not.
     */
    protected function ensureServiceConfigured(NubariumService $service): ?JsonResponse
    {
        if (!$service->isConfigured()) {
            return $this->serviceUnavailable('Servicio de validación no configurado');
        }

        return null;
    }

    /**
     * Get available KYC services for the current tenant.
     */
    public function services(Request $request): JsonResponse
    {
        $service = $this->getKycService($request);

        $services = [
            'nubarium' => [
                'configured' => $service->isConfigured(),
                'services' => $service->isConfigured() ? $service->getAvailableServices() : [],
            ],
        ];

        return $this->success([
            'services' => $services,
            'birth_states' => NubariumService::BIRTH_STATES,
        ]);
    }

    /**
     * Test KYC service connection and refresh token if needed.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->testConnection();

        $this->logKycAction($request, 'connection_test', [
            'success' => $result['success'],
            'message' => $result['message'],
        ]);

        if (!$result['success']) {
            return $this->badRequest('CONNECTION_FAILED', $result['message']);
        }

        return $this->success(['configured' => true], $result['message']);
    }

    /**
     * Force refresh the Nubarium JWT token.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $newToken = $service->refreshToken();

        $this->logKycAction($request, 'token_refresh', [
            'success' => $newToken !== null,
        ]);

        if ($newToken) {
            return $this->success(null, 'Token renovado exitosamente');
        }

        return $this->badRequest('TOKEN_REFRESH_FAILED', 'Error al renovar el token. Verifique las credenciales configuradas.');
    }

    /**
     * Validate CURP with RENAPO.
     */
    public function validateCurp(ValidateCurpRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateCurp($request->curp);

        $this->logKycAction($request, 'curp_validation', [
            'curp' => $this->maskCurp($request->curp),
            'success' => $result['success'] ?? false,
            'valid' => $result['valid'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->error(
                'CURP_VALIDATION_FAILED',
                $result['error'] ?? 'Error al validar CURP',
                $result['status_code'] ?? 400
            );
        }

        // Auto-register verifications if applicant exists and CURP is valid
        $applicant = $this->getApplicant($request);
        if ($result['valid'] && $applicant) {
            $data = $result['data'] ?? [];

            $this->verificationService->verify(
                $applicant,
                'curp',
                $request->curp,
                VerificationMethod::RENAPO,
                ['renapo_response' => $data]
            );

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

            $this->verificationService->updateKycStatus($applicant);
        }

        return $this->success([
            'curp_data' => $result['data'],
            'valid' => $result['valid'],
        ], 'CURP validado');
    }

    /**
     * Get CURP by personal data.
     */
    public function getCurp(GetCurpRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->getCurp($request->only([
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'fecha_nacimiento',
            'sexo',
            'entidad_nacimiento',
        ]));

        $this->logKycAction($request, 'curp_lookup', [
            'nombres' => $request->nombres,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->badRequest('CURP_LOOKUP_FAILED', $result['error'] ?? 'Error al obtener CURP');
        }

        // Auto-register verifications if applicant exists
        $applicant = $this->getApplicant($request);
        if (!empty($result['curp']) && $applicant) {
            $data = $result['data'] ?? [];

            $this->verificationService->verify(
                $applicant,
                'curp',
                $result['curp'],
                VerificationMethod::RENAPO,
                ['renapo_response' => $data]
            );

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

            $this->verificationService->updateKycStatus($applicant);
        }

        return $this->success([
            'curp_data' => $result['data'],
            'curp' => $result['curp'],
        ], 'CURP encontrado');
    }

    /**
     * Validate RFC with SAT.
     */
    public function validateRfc(ValidateRfcRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateRfc($request->rfc);

        $this->logKycAction($request, 'rfc_validation', [
            'rfc' => $this->maskRfc($request->rfc),
            'success' => $result['success'] ?? false,
            'valid' => $result['valid'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->error(
                'RFC_VALIDATION_FAILED',
                $result['error'] ?? 'Error al validar RFC',
                $result['status_code'] ?? 400
            );
        }

        // Auto-register RFC verification if applicant exists and RFC is valid
        $applicant = $this->getApplicant($request);
        if ($result['valid'] && $applicant) {
            $this->verificationService->verify(
                $applicant,
                'rfc',
                $request->rfc,
                VerificationMethod::SAT,
                ['sat_response' => $result['data'] ?? []]
            );
        }

        return $this->success([
            'rfc_data' => $result['data'],
            'valid' => $result['valid'],
        ], 'RFC validado');
    }

    /**
     * Validate INE/IFE with OCR extraction and optional list validation.
     */
    public function validateIne(ValidateIneRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateIne(
            $request->front_image,
            $request->back_image,
            $request->boolean('validate_list', true)
        );

        $this->logKycAction($request, 'ine_validation', [
            'success' => $result['success'] ?? false,
            'is_valid' => $result['is_valid'] ?? null,
        ]);

        if (!$result['success']) {
            return $this->error(
                'INE_VALIDATION_FAILED',
                $result['error'] ?? 'Error al validar INE',
                $result['status_code'] ?? 400
            );
        }

        // Auto-register INE verification if applicant exists and INE is valid
        $applicant = $this->getApplicant($request);
        if ($result['is_valid'] && $applicant) {
            $ocrData = $result['ocr_data'] ?? [];

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

            $this->updateAndApproveIneDocuments($applicant, $ocrData, $result['list_validation'] ?? null);
            $this->verificationService->updateKycStatus($applicant);
        }

        return $this->success([
            'ocr_data' => $result['ocr_data'] ?? null,
            'list_validation' => $result['list_validation'] ?? null,
            'is_valid' => $result['is_valid'] ?? null,
            'validation_code' => $result['validation_code'] ?? null,
        ], 'INE procesado');
    }

    /**
     * Validate face match between selfie and INE photo.
     */
    public function validateFaceMatch(ValidateFaceMatchRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $threshold = $request->input('threshold', 80);

        $result = $service->validateFaceMatch(
            $request->selfie_image,
            $request->ine_image,
            $threshold
        );

        $this->logKycAction($request, 'face_match', [
            'success' => $result['success'] ?? false,
            'match' => $result['match'] ?? false,
            'score' => $result['score'] ?? null,
        ]);

        if (!$result['success']) {
            return $this->badRequest('FACE_MATCH_FAILED', $result['error'] ?? 'Error en comparación facial');
        }

        // Auto-register face match verification if applicant exists
        $applicant = $this->getApplicant($request);
        if ($result['match'] && $applicant) {
            $this->verificationService->verify(
                $applicant,
                'face_match',
                $result['match'] ? 'passed' : 'failed',
                VerificationMethod::KYC_FACE_MATCH,
                [
                    'score' => $result['score'],
                    'threshold' => $result['threshold'],
                    'validation_code' => $result['validation_code'] ?? null,
                ]
            );

            $this->updateAndApproveSelfieDocument($applicant, [
                'score' => $result['score'],
                'threshold' => $result['threshold'],
                'validation_code' => $result['validation_code'] ?? null,
            ]);

            $this->verificationService->updateKycStatus($applicant);
        }

        return $this->success([
            'match' => $result['match'],
            'score' => $result['score'],
            'threshold' => $result['threshold'],
            'validation_code' => $result['validation_code'] ?? null,
        ], $result['match'] ? 'Rostros coinciden' : 'Rostros no coinciden');
    }

    /**
     * Validate liveness detection from selfie image.
     */
    public function validateLiveness(ValidateLivenessRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateLiveness($request->face_image);

        $this->logKycAction($request, 'liveness', [
            'success' => $result['success'] ?? false,
            'passed' => $result['passed'] ?? false,
            'score' => $result['score'] ?? null,
        ]);

        if (!$result['success']) {
            return $this->badRequest('LIVENESS_FAILED', $result['error'] ?? 'Error en detección de vida');
        }

        // Auto-register liveness verification if applicant exists
        $applicant = $this->getApplicant($request);
        if ($result['passed'] && $applicant) {
            $this->verificationService->verify(
                $applicant,
                'liveness',
                $result['passed'] ? 'passed' : 'failed',
                VerificationMethod::KYC_LIVENESS,
                [
                    'score' => $result['score'],
                    'validation_code' => $result['validation_code'] ?? null,
                ]
            );
        }

        return $this->success([
            'passed' => $result['passed'],
            'score' => $result['score'],
            'validation_code' => $result['validation_code'] ?? null,
        ], $result['passed'] ? 'Prueba de vida exitosa' : 'Prueba de vida fallida');
    }

    /**
     * Get biometric SDK token for frontend.
     */
    public function getBiometricToken(Request $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $transactionId = Str::uuid()->toString();

        if ($request->has('application_id')) {
            $transactionId = $request->application_id . '_' . time();
        }

        $result = $service->getBiometricToken($transactionId);

        if (!$result['success']) {
            return $this->badRequest('TOKEN_GENERATION_FAILED', $result['error'] ?? 'Error al generar token');
        }

        return $this->success([
            'token' => $result['token'],
            'expires_in' => $result['expires_in'],
            'transaction_id' => $transactionId,
        ], 'Token generado');
    }

    /**
     * Validate SPEI CEP (payment proof).
     */
    public function validateCep(ValidateCepRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateCep($request->only([
            'clave_rastreo',
            'fecha_operacion',
            'monto',
            'cuenta_beneficiario',
            'cuenta_ordenante',
        ]));

        $this->logKycAction($request, 'cep_validation', [
            'clave_rastreo' => $request->clave_rastreo,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->badRequest('CEP_VALIDATION_FAILED', $result['error'] ?? 'Error al validar CEP');
        }

        return $this->success([
            'cep_data' => $result['data'],
            'valid' => $result['valid'],
        ], 'CEP validado');
    }

    /**
     * Check OFAC & UN sanctions block lists.
     */
    public function checkOfac(CheckOfacRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $similarity = $request->input('similarity', 80);
        $result = $service->checkOfac($request->name, $similarity);

        $this->logKycAction($request, 'ofac_check', [
            'name' => $request->name,
            'found' => $result['found'] ?? false,
            'count' => $result['count'] ?? 0,
        ]);

        if (!$result['success']) {
            return $this->badRequest('OFAC_CHECK_FAILED', $result['error'] ?? 'Error al consultar OFAC');
        }

        return $this->success([
            'found' => $result['found'],
            'matches' => $result['matches'],
            'count' => $result['count'] ?? 0,
            'validation_code' => $result['validation_code'] ?? null,
            'checked_at' => $result['checked_at'],
            'warning' => $result['warning'] ?? null,
        ], 'Consulta OFAC completada');
    }

    /**
     * Check Mexican PLD (Anti-Money Laundering) blacklists.
     */
    public function checkPldBlacklists(CheckPldBlacklistsRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $similarity = $request->input('similarity', 80);
        $result = $service->checkPldBlacklists($request->name, $request->curp, $similarity);

        $this->logKycAction($request, 'pld_blacklists_check', [
            'name' => $request->name,
            'curp' => $request->curp ? $this->maskCurp($request->curp) : null,
            'found' => $result['found'] ?? false,
            'count' => $result['count'] ?? 0,
        ]);

        if (!$result['success']) {
            return $this->badRequest('PLD_CHECK_FAILED', $result['error'] ?? 'Error al consultar listas negras');
        }

        return $this->success([
            'found' => $result['found'],
            'matches' => $result['matches'],
            'count' => $result['count'] ?? 0,
            'validation_code' => $result['validation_code'] ?? null,
            'checked_at' => $result['checked_at'],
            'warning' => $result['warning'] ?? null,
        ], 'Consulta de listas negras completada');
    }

    /**
     * Get IMSS employment history.
     */
    public function getImssHistory(GetImssHistoryRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->getImssHistory($request->curp, $request->nss);

        $this->logKycAction($request, 'imss_history', [
            'curp' => $this->maskCurp($request->curp),
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->badRequest('IMSS_QUERY_FAILED', $result['error'] ?? 'Error al consultar IMSS');
        }

        return $this->success($result['data'], 'Historial IMSS obtenido');
    }

    /**
     * Validate professional license (Cédula Profesional).
     */
    public function validateCedula(ValidateCedulaRequest $request): JsonResponse
    {
        $service = $this->getKycService($request);

        if ($error = $this->ensureServiceConfigured($service)) {
            return $error;
        }

        $result = $service->validateCedulaProfesional($request->cedula);

        $this->logKycAction($request, 'cedula_validation', [
            'cedula' => $request->cedula,
            'success' => $result['success'] ?? false,
        ]);

        if (!$result['success']) {
            return $this->badRequest('CEDULA_VALIDATION_FAILED', $result['error'] ?? 'Error al validar cédula');
        }

        return $this->success([
            'cedula_data' => $result['data'],
            'valid' => $result['valid'],
        ], 'Cédula validada');
    }

    // =========================================================================
    // DATA VERIFICATION ENDPOINTS
    // =========================================================================

    /**
     * Record KYC verification results for the current applicant.
     */
    public function recordVerifications(RecordVerificationsRequest $request): JsonResponse
    {
        // Use getOrCreateApplicant to ensure applicant exists
        $applicant = $this->getOrCreateApplicant($request);

        if (!$applicant) {
            return $this->notFound('Solicitante no encontrado');
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

                    // Update document metadata when verification is recorded
                    if (in_array($field, ['face_match', 'selfie', 'selfie_document']) && $record->is_verified && $record->is_locked) {
                        $this->updateSelfieDocumentMetadata($applicant, $metadata, $method);
                    }
                    if (in_array($field, ['ine_document_front', 'ine_front']) && $record->is_verified && $record->is_locked) {
                        $this->updateIneDocumentMetadata($applicant, 'INE_FRONT', $metadata, $method);
                    }
                    if (in_array($field, ['ine_document_back', 'ine_back']) && $record->is_verified && $record->is_locked) {
                        $this->updateIneDocumentMetadata($applicant, 'INE_BACK', $metadata, $method);
                    }
                }
            }

            $this->verificationService->updateKycStatus($applicant);

            DB::commit();

            $this->logKycAction($request, 'verifications_recorded', [
                'applicant_id' => $applicant->id,
                'fields_count' => count($recorded),
            ]);

            return $this->success([
                'recorded' => $recorded,
                'total' => count($recorded),
            ], 'Verificaciones registradas correctamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->serverError('Error al registrar verificaciones: ' . $e->getMessage());
        }
    }

    /**
     * Get all verifications for the current applicant.
     */
    public function getVerifications(Request $request): JsonResponse
    {
        // Use getOrCreateApplicant to ensure applicant exists
        $applicant = $this->getOrCreateApplicant($request);

        if (!$applicant) {
            return $this->notFound('Solicitante no encontrado');
        }

        $verifications = DataVerification::getVerifiedFieldsForApplicant($applicant->id);

        $allVerifications = DataVerification::where('applicant_id', $applicant->id)
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

        return $this->success([
            'verifications' => $allVerifications,
            'verified_fields' => $verifications,
            'summary' => $summary,
            'kyc_verified' => $applicant->isKycVerified(),
            'kyc_verified_at' => $applicant->kyc_verified_at?->toIso8601String(),
        ]);
    }

    /**
     * Check if specific fields are verified for the current applicant.
     */
    public function checkFieldsVerified(CheckFieldsVerifiedRequest $request): JsonResponse
    {
        // Use getOrCreateApplicant to ensure applicant exists
        $applicant = $this->getOrCreateApplicant($request);

        if (!$applicant) {
            return $this->notFound('Solicitante no encontrado');
        }

        $results = [];
        foreach ($request->fields as $field) {
            $results[$field] = DataVerification::isFieldVerified($applicant->id, $field);
        }

        return $this->success([
            'fields' => $results,
            'all_verified' => !in_array(false, $results, true),
        ]);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Log KYC action for audit.
     */
    private function logKycAction(Request $request, string $action, array $data): void
    {
        try {
            $user = $request->user();
            $userId = null;

            if ($user instanceof ApplicantAccount) {
                $userId = $user->id;
            }

            AuditLog::create([
                'tenant_id' => app('tenant')->id,
                'user_id' => $userId,
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

    /**
     * Update SELFIE document metadata and auto-approve it when face_match validation passes.
     */
    private function updateAndApproveSelfieDocument(Applicant $applicant, array $faceMatchData): void
    {
        $selfieDoc = Document::where('applicant_id', $applicant->id)
            ->where('type', 'SELFIE')
            ->first();

        if (!$selfieDoc) {
            Log::warning('[V2 KycController] No SELFIE document found to update', [
                'applicant_id' => $applicant->id,
            ]);
            return;
        }

        $currentMetadata = $selfieDoc->metadata ?? [];
        $newMetadata = array_merge($currentMetadata, [
            'kyc_validated' => true,
            'face_match_passed' => true,
            'face_match' => true,
            'nubarium_validated' => true,
            'source' => 'kyc',
            'validation_method' => 'KYC_FACE_MATCH',
            'validated_at' => now()->toIso8601String(),
            'face_match_score' => $faceMatchData['score'] ?? null,
            'threshold' => $faceMatchData['threshold'] ?? null,
            'validation_code' => $faceMatchData['validation_code'] ?? null,
        ]);

        $selfieDoc->metadata = $newMetadata;
        $selfieDoc->status = \App\Enums\DocumentStatus::APPROVED;
        $selfieDoc->reviewed_at = now();
        $selfieDoc->save();

        $this->verificationService->verifySelfieDocument(
            $applicant,
            $selfieDoc->id,
            [
                'face_match_score' => $faceMatchData['score'] ?? null,
                'face_match_passed' => true,
            ]
        );

        Log::info('[V2 KycController] SELFIE document updated and auto-approved via KYC', [
            'applicant_id' => $applicant->id,
            'document_id' => $selfieDoc->id,
            'face_match_score' => $faceMatchData['score'] ?? null,
            'status' => 'APPROVED',
        ]);
    }

    /**
     * Update SELFIE document metadata when face_match verification is recorded.
     */
    private function updateSelfieDocumentMetadata(Applicant $applicant, ?array $verificationMetadata, string $method): void
    {
        $selfieDoc = Document::where('applicant_id', $applicant->id)
            ->where('type', 'SELFIE')
            ->first();

        if (!$selfieDoc) {
            return;
        }

        $currentMetadata = $selfieDoc->metadata ?? [];
        $newMetadata = array_merge($currentMetadata, [
            'kyc_validated' => true,
            'face_match_passed' => true,
            'face_match' => true,
            'nubarium_validated' => true,
            'source' => 'kyc',
            'validation_method' => $method,
            'validated_at' => now()->toIso8601String(),
        ]);

        if ($verificationMetadata) {
            if (isset($verificationMetadata['score'])) {
                $newMetadata['face_match_score'] = $verificationMetadata['score'];
            }
            if (isset($verificationMetadata['face_match_score'])) {
                $newMetadata['face_match_score'] = $verificationMetadata['face_match_score'];
            }
            if (isset($verificationMetadata['liveness_passed'])) {
                $newMetadata['liveness_passed'] = $verificationMetadata['liveness_passed'];
            }
            if (isset($verificationMetadata['liveness_score'])) {
                $newMetadata['liveness_score'] = $verificationMetadata['liveness_score'];
            }
        }

        if ($selfieDoc->status !== \App\Enums\DocumentStatus::APPROVED) {
            $selfieDoc->status = \App\Enums\DocumentStatus::APPROVED;
            $selfieDoc->reviewed_at = now();
        }

        $selfieDoc->metadata = $newMetadata;
        $selfieDoc->save();

        Log::info('[V2 KycController] Updated SELFIE document metadata with KYC validation', [
            'applicant_id' => $applicant->id,
            'document_id' => $selfieDoc->id,
            'face_match_score' => $newMetadata['face_match_score'] ?? null,
        ]);
    }

    /**
     * Update INE documents metadata and auto-approve them when INE validation passes.
     */
    private function updateAndApproveIneDocuments(Applicant $applicant, array $ocrData, ?array $listValidation): void
    {
        $documentTypes = ['INE_FRONT', 'INE_BACK'];

        foreach ($documentTypes as $docType) {
            $ineDoc = Document::where('applicant_id', $applicant->id)
                ->where('type', $docType)
                ->first();

            if (!$ineDoc) {
                continue;
            }

            $currentMetadata = $ineDoc->metadata ?? [];
            $newMetadata = array_merge($currentMetadata, [
                'kyc_validated' => true,
                'ine_valid' => true,
                'ine_ocr' => true,
                'nubarium_validated' => true,
                'source' => 'kyc',
                'validation_method' => 'KYC_INE_OCR',
                'validated_at' => now()->toIso8601String(),
            ]);

            if (!empty($ocrData)) {
                $newMetadata['ocr_data'] = $ocrData;
                $newMetadata['ocr_curp'] = $ocrData['curp'] ?? null;
            }
            if ($listValidation) {
                $newMetadata['list_validation'] = $listValidation;
                $newMetadata['list_valid'] = $listValidation['valid'] ?? false;
            }

            $ineDoc->metadata = $newMetadata;
            $ineDoc->status = \App\Enums\DocumentStatus::APPROVED;
            $ineDoc->reviewed_at = now();
            $ineDoc->save();

            Log::info('[V2 KycController] INE document updated and auto-approved via KYC', [
                'applicant_id' => $applicant->id,
                'document_id' => $ineDoc->id,
                'doc_type' => $docType,
                'status' => 'APPROVED',
            ]);
        }
    }

    /**
     * Update INE document metadata when INE verification is recorded.
     */
    private function updateIneDocumentMetadata(Applicant $applicant, string $docType, ?array $verificationMetadata, string $method): void
    {
        $ineDoc = Document::where('applicant_id', $applicant->id)
            ->where('type', $docType)
            ->first();

        if (!$ineDoc) {
            return;
        }

        $currentMetadata = $ineDoc->metadata ?? [];
        $newMetadata = array_merge($currentMetadata, [
            'kyc_validated' => true,
            'ine_valid' => true,
            'ine_ocr' => true,
            'nubarium_validated' => true,
            'source' => 'kyc',
            'validation_method' => $method,
            'validated_at' => now()->toIso8601String(),
        ]);

        if ($verificationMetadata) {
            if (isset($verificationMetadata['ocr_data'])) {
                $newMetadata['ocr_data'] = $verificationMetadata['ocr_data'];
            }
            if (isset($verificationMetadata['ocr_curp'])) {
                $newMetadata['ocr_curp'] = $verificationMetadata['ocr_curp'];
            }
        }

        $ineDoc->metadata = $newMetadata;
        $ineDoc->save();

        Log::info('[V2 KycController] Updated INE document metadata with KYC validation', [
            'applicant_id' => $applicant->id,
            'document_id' => $ineDoc->id,
            'doc_type' => $docType,
        ]);
    }
}
