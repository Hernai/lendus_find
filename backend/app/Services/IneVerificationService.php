<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\MexicanState;
use App\Enums\VerificationMethod;
use App\Models\Application;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonIdentification;
use App\Services\ExternalApi\NubariumService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verificación completa del INE (lógica de negocio compartida).
 *
 * Encapsula la orquestación que antes vivía inline en el KycController del
 * applicant, para que se pueda reutilizar desde:
 *   - el onboarding del solicitante (Applicant/KycController::verifyIne)
 *   - el panel de staff (re-ejecutar la verificación si Nubarium estaba caído)
 *
 * Pasos:
 *   1) OCR + validación INE (lista nominal)
 *   2) validación de la CURP extraída con RENAPO
 *   3) persistencia (identificaciones, documento, verificaciones)
 *   4) diferencias OCR vs RENAPO (para revisión del admin)
 */
class IneVerificationService
{
    public function __construct(
        private VerificationService $verificationService,
        private ApplicationEventService $eventService,
    ) {
    }

    /**
     * Ejecuta la verificación completa del INE.
     *
     * @param  NubariumService  $service   Servicio KYC ya resuelto para el tenant.
     * @param  Person|null      $applicant Persona a la que se le persiste (null = solo OCR).
     * @return array{success:bool, error?:string, status?:int, data?:array}
     */
    public function verify(
        NubariumService $service,
        ?Person $applicant,
        string $frontImage,
        ?string $backImage = null,
        bool $validateList = true,
        ?string $userId = null,
        ?Request $request = null,
    ): array {
        // 1) OCR + validación INE (lista nominal)
        $ine = $service->validateIne($frontImage, $backImage, $validateList);

        if (!($ine['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $ine['error'] ?? 'Error al validar INE',
                'status' => $ine['status_code'] ?? 400,
            ];
        }

        $ocr = $ine['ocr_data'] ?? [];
        $ineValid = $ine['is_valid'] ?? null;

        // 2) Persistir el resultado del INE (identificaciones + documento).
        if ($ineValid && $applicant) {
            // Pasamos también los identificadores del INE (ocr/cic/clave) para que
            // verifyIneDocument los marque como verificados (si no, ine_ocr/ine_folio
            // quedan como "completado" azul en vez de "verificado" verde).
            $this->verificationService->verifyIneDocument(
                $applicant,
                'front',
                'ine_ocr_' . now()->timestamp,
                [
                    'curp' => $ocr['curp'] ?? null,
                    'first_name' => $ocr['nombres'] ?? null,
                    'last_name_1' => $ocr['apellido_paterno'] ?? null,
                    'last_name_2' => $ocr['apellido_materno'] ?? null,
                    'birth_date' => $ocr['fecha_nacimiento'] ?? null,
                    'clave_elector' => $ocr['clave_elector'] ?? null,
                    'ocr' => $ocr['ocr'] ?? null,
                    'cic' => $ocr['cic'] ?? null,
                    'identificador_ciudadano' => $ocr['identificador_ciudadano'] ?? null,
                ]
            );
            // El sexo lo trae el OCR del INE; lo marcamos verificado (KYC_INE_OCR).
            if (!empty($ocr['sexo'])) {
                $gender = strtoupper((string) $ocr['sexo']) === 'H' ? 'M' : 'F';
                $this->verificationService->verify($applicant, 'gender', $gender, VerificationMethod::KYC_INE_OCR);
            }
            $this->saveIdentificationsFromIne($applicant, $ocr);
            $this->updateAndApproveIneDocuments($applicant, $ocr, $ine['list_validation'] ?? null);
        }

        // 3) Validación de la CURP extraída con RENAPO.
        $curp = isset($ocr['curp']) ? preg_replace('/\s+/', '', strtoupper((string) $ocr['curp'])) : null;
        $curpValid = null;
        $renapo = null;
        $renapoFull = null;
        if ($curp) {
            $curpRes = $service->validateCurp($curp);
            if ($curpRes['success'] ?? false) {
                $curpValid = $curpRes['valid'] ?? false;
                $renapoData = $curpRes['data'] ?? [];
                $renapoFull = !empty($renapoData) ? $renapoData : null;

                if ($curpValid && $applicant) {
                    $this->verificationService->verify($applicant, 'curp', $curp, VerificationMethod::RENAPO, ['renapo_response' => $renapoData]);
                    if (!empty($renapoData['nombres'])) {
                        $this->verificationService->verify($applicant, 'first_name', $renapoData['nombres'], VerificationMethod::RENAPO);
                    }
                    if (!empty($renapoData['apellido_paterno'])) {
                        $this->verificationService->verify($applicant, 'last_name_1', $renapoData['apellido_paterno'], VerificationMethod::RENAPO);
                    }
                    if (!empty($renapoData['apellido_materno'])) {
                        $this->verificationService->verify($applicant, 'last_name_2', $renapoData['apellido_materno'], VerificationMethod::RENAPO);
                    }
                    if (!empty($renapoData['fecha_nacimiento'])) {
                        $this->verificationService->verify($applicant, 'birth_date', $renapoData['fecha_nacimiento'], VerificationMethod::RENAPO);
                    }
                    // Entidad de nacimiento derivada de la CURP (validada por RENAPO).
                    $birthState = MexicanState::fromCurp($curp);
                    if ($birthState) {
                        $this->verificationService->verify($applicant, 'birth_state', $birthState->value, VerificationMethod::RENAPO);
                        if (empty($applicant->birth_state)) {
                            $applicant->birth_state = $birthState->value;
                            $applicant->save();
                        }
                    }
                    $this->updateCurpIdentificationStatus($applicant, $curp, $renapoData);
                }

                if (!empty($renapoData)) {
                    $renapo = [
                        'nombres' => strtoupper(trim((string) ($renapoData['nombres'] ?? ''))),
                        'apellido_paterno' => strtoupper(trim((string) ($renapoData['apellido_paterno'] ?? ''))),
                        'apellido_materno' => strtoupper(trim((string) ($renapoData['apellido_materno'] ?? ''))),
                    ];
                }
            }
        }

        // 4) Actualizar KYC status + evento en el timeline.
        if ($ineValid && $applicant) {
            $this->verificationService->updateKycStatus($applicant);
            $application = $this->getCurrentApplication($applicant);
            if ($application) {
                $this->eventService->recordKycIneValidated($application, true, $userId, $ocr, $request);
            }
        }

        // 5) Campos extraídos (base de la confirmación) + diferencias OCR vs RENAPO.
        $fields = [
            'nombres' => strtoupper(trim((string) ($ocr['nombres'] ?? ''))),
            'apellido_paterno' => strtoupper(trim((string) ($ocr['apellido_paterno'] ?? ''))),
            'apellido_materno' => strtoupper(trim((string) ($ocr['apellido_materno'] ?? ''))),
            'curp' => $curp ?? '',
        ];
        $diffs = [];
        if ($renapo) {
            foreach (['nombres', 'apellido_paterno', 'apellido_materno'] as $k) {
                if (($renapo[$k] ?? '') !== ($fields[$k] ?? '')) {
                    $diffs[$k] = ['ocr' => $fields[$k], 'renapo' => $renapo[$k]];
                }
            }
        }

        // Persistir la verificación en la persona para que el admin la revise.
        if ($applicant) {
            $kyc = $applicant->kyc_data ?? [];
            $kyc['ine_verification'] = [
                'ocr' => $fields,
                'renapo' => $renapo,
                'ine_valid' => $ineValid,
                'curp_valid' => $curpValid,
                'diffs' => $diffs,
                'verified_at' => now()->toIso8601String(),
            ];
            $applicant->kyc_data = $kyc;
            $applicant->save();
        }

        return [
            'success' => true,
            'data' => [
                'fields' => $fields,
                'ocr_data' => $ocr ?: null,
                'ine_valid' => $ineValid,
                'curp_valid' => $curpValid,
                'renapo' => $renapo,
                'renapo_data' => $renapoFull ?? null,
                'diffs' => $diffs,
                'list_validation' => $ine['list_validation'] ?? null,
                'validation_code' => $ine['validation_code'] ?? null,
            ],
        ];
    }

    /**
     * Get the current (active) application for the person.
     */
    private function getCurrentApplication(?Person $person): ?Application
    {
        if (!$person) {
            return null;
        }

        return Application::where('person_id', $person->id)
            ->active()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Save CURP, RFC and INE clave to person_identifications table.
     */
    public function saveIdentificationsFromIne(Person $person, array $ocrData): void
    {
        $tenantId = $person->tenant_id;

        // Save CURP
        if (!empty($ocrData['curp'])) {
            $person->identifications()->updateOrCreate(
                ['type' => 'CURP'],
                [
                    'tenant_id' => $tenantId,
                    'identifier_value' => strtoupper($ocrData['curp']),
                    'is_current' => true,
                    'status' => 'VERIFIED',
                    'verified_at' => now(),
                    'verification_method' => 'INE_OCR',
                ]
            );
            Log::info('[IneVerificationService] Saved CURP from INE OCR', [
                'person_id' => $person->id,
                'curp' => substr($ocrData['curp'], 0, 4) . '****',
            ]);
        }

        // Calculate RFC from CURP if not provided
        $rfc = null;
        if (!empty($ocrData['curp']) && strlen($ocrData['curp']) === 18) {
            $rfc = substr($ocrData['curp'], 0, 10);
        }

        if ($rfc) {
            $person->identifications()->updateOrCreate(
                ['type' => 'RFC'],
                [
                    'tenant_id' => $tenantId,
                    'identifier_value' => strtoupper($rfc),
                    'is_current' => true,
                    'status' => 'PENDING', // RFC needs SAT validation to be verified
                ]
            );
            Log::info('[IneVerificationService] Saved RFC derived from CURP', [
                'person_id' => $person->id,
            ]);
        }

        // Save INE clave de elector
        if (!empty($ocrData['clave_elector'])) {
            $ineData = [
                'ocr' => $ocrData['ocr'] ?? null,
                'cic' => $ocrData['cic'] ?? null,
                'seccion' => $ocrData['seccion'] ?? null,
                'emision' => $ocrData['emision'] ?? null,
                'vigencia' => $ocrData['vigencia'] ?? null,
            ];

            $person->identifications()->updateOrCreate(
                ['type' => 'INE'],
                [
                    'tenant_id' => $tenantId,
                    'identifier_value' => strtoupper($ocrData['clave_elector']),
                    'document_data' => $ineData,
                    'is_current' => true,
                    'status' => 'VERIFIED',
                    'verified_at' => now(),
                    'verification_method' => 'INE_OCR',
                ]
            );
            Log::info('[IneVerificationService] Saved INE clave from OCR', [
                'person_id' => $person->id,
            ]);
        }

        // Also update Person's personal data from OCR
        $updateData = [];
        if (!empty($ocrData['nombres']) && empty($person->first_name)) {
            $updateData['first_name'] = $ocrData['nombres'];
        }
        if (!empty($ocrData['apellido_paterno']) && empty($person->last_name_1)) {
            $updateData['last_name_1'] = $ocrData['apellido_paterno'];
        }
        if (!empty($ocrData['apellido_materno']) && empty($person->last_name_2)) {
            $updateData['last_name_2'] = $ocrData['apellido_materno'];
        }
        if (!empty($ocrData['fecha_nacimiento']) && empty($person->birth_date)) {
            // Parse date from format "22/10/1987" to Y-m-d
            $parts = explode('/', $ocrData['fecha_nacimiento']);
            if (count($parts) === 3) {
                $updateData['birth_date'] = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }
        if (!empty($ocrData['sexo']) && empty($person->gender)) {
            $updateData['gender'] = $ocrData['sexo'] === 'H' ? 'M' : 'F';
        }

        if (!empty($updateData)) {
            $person->update($updateData);
            Log::info('[IneVerificationService] Updated Person data from INE OCR', [
                'person_id' => $person->id,
                'fields' => array_keys($updateData),
            ]);
        }
    }

    /**
     * Update INE documents metadata and auto-approve them when INE validation passes.
     */
    public function updateAndApproveIneDocuments(Person $person, array $ocrData, ?array $listValidation): void
    {
        $documentTypes = ['INE_FRONT', 'INE_BACK'];

        foreach ($documentTypes as $docType) {
            // Solo la versión vigente: si el usuario re-subió el INE, no aprobar una
            // versión superseded/inactiva. Tomamos la activa más reciente.
            $ineDoc = Document::where('documentable_type', Person::class)
                ->where('documentable_id', $person->id)
                ->where('type', $docType)
                ->where('is_active', true)
                ->orderByDesc('created_at')
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
            // La columna `status` es string (Document no castea a enum, y le faltan
            // EXPIRED/SUPERSEDED); usamos el value para no dejar un enum en memoria.
            $ineDoc->status = DocumentStatus::APPROVED->value;
            $ineDoc->reviewed_at = now();
            $ineDoc->notes = ($ineDoc->notes ? $ineDoc->notes . "\n" : '') . 'Auto-aprobado por validación KYC de INE.';
            $ineDoc->save();

            Log::info('[IneVerificationService] INE document updated and auto-approved via KYC', [
                'person_id' => $person->id,
                'document_id' => $ineDoc->id,
                'doc_type' => $docType,
                'status' => 'APPROVED',
            ]);
        }
    }

    /**
     * Create/Update CURP PersonIdentification after RENAPO validation.
     */
    public function updateCurpIdentificationStatus(Person $person, string $curp, array $renapoData): void
    {
        $curpUpper = strtoupper($curp);

        $identification = $person->identifications()
            ->where('type', PersonIdentification::TYPE_CURP)
            ->first();

        if ($identification) {
            if ($identification->status !== PersonIdentification::STATUS_VERIFIED) {
                $identification->markAsVerified(
                    'RENAPO',
                    null, // verified_by - automated
                    ['renapo_response' => $renapoData],
                    1.0 // confidence
                );
                Log::info('[IneVerificationService] Updated CURP identification to VERIFIED via RENAPO', [
                    'person_id' => $person->id,
                    'identification_id' => $identification->id,
                ]);
            }
        } else {
            $person->identifications()->create([
                'tenant_id' => $person->tenant_id,
                'type' => PersonIdentification::TYPE_CURP,
                'identifier_value' => $curpUpper,
                'is_current' => true,
                'status' => PersonIdentification::STATUS_VERIFIED,
                'verified_at' => now(),
                'verification_method' => 'RENAPO',
                'verification_data' => ['renapo_response' => $renapoData],
                'verification_confidence' => 1.0,
            ]);
            Log::info('[IneVerificationService] Created CURP identification as VERIFIED via RENAPO', [
                'person_id' => $person->id,
            ]);
        }
    }
}
