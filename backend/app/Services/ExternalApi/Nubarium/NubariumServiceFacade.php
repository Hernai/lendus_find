<?php

namespace App\Services\ExternalApi\Nubarium;

use App\Models\Tenant;

/**
 * Facade for Nubarium services.
 *
 * Provides backward compatibility with the original NubariumService
 * by delegating to specialized services while maintaining the same API.
 *
 * This facade orchestrates:
 * - NubariumIdentityService (CURP, RFC)
 * - NubariumBiometricsService (INE, Face Match, Liveness)
 * - NubariumComplianceService (OFAC, PLD, IMSS)
 */
class NubariumServiceFacade
{
    protected Tenant $tenant;
    protected ?NubariumIdentityService $identityService = null;
    protected ?NubariumBiometricsService $biometricsService = null;
    protected ?NubariumComplianceService $complianceService = null;

    /**
     * Context for API logging.
     */
    protected ?string $applicantId = null;
    protected ?string $applicationId = null;
    protected ?string $userId = null;
    protected ?string $entityType = null;
    protected ?string $entityId = null;

    /**
     * Servicios de validación disponibles (key => etiqueta). Refleja lo que
     * está realmente implementado contra la doc de Nubarium. Los servicios
     * asíncronos por webhook (IMSS/ISSSTE) van marcados como "próximamente"
     * porque requieren un receptor de callback que aún no existe.
     */
    public const SERVICES = [
        // Identidad (RENAPO / SAT)
        'curp' => 'Validación de CURP',
        'rfc' => 'Validación de RFC',
        // INE + biometría
        'ine' => 'Validación de INE',
        'face_match' => 'Comparación facial (selfie vs INE)',
        'liveness' => 'Prueba de vida (liveness)',
        'biometric_token' => 'Token para SDK Biométrico',
        // México (síncronos)
        'cedula_sep' => 'Validación de Cédula Profesional (SEP)',
        'spei_cep' => 'Validación de CEP SPEI (Banxico)',
        // Cumplimiento / listas
        'ofac' => 'Listas OFAC y ONU',
        'pld' => 'Listas negras PLD / PEP',
        // Asíncronos por webhook — pendientes de receptor de callback.
        'imss' => 'Historial IMSS (webhook — próximamente)',
        'issste' => 'Historial ISSSTE (webhook — próximamente)',
    ];

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Set the applicant context for API logging.
     */
    public function forApplicant(?string $applicantId): static
    {
        $this->applicantId = $applicantId;
        return $this;
    }

    /**
     * Set the application context for API logging.
     */
    public function forApplication(?string $applicationId): static
    {
        $this->applicationId = $applicationId;
        return $this;
    }

    /**
     * Set the user context for API logging.
     */
    public function forUser(?string $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set the entity context for API logging (Person or Company).
     */
    public function forEntity($entity): static
    {
        if ($entity) {
            $this->entityType = get_class($entity);
            $this->entityId = $entity->id;
        }
        return $this;
    }

    /**
     * Get the identity service instance.
     */
    protected function identity(): NubariumIdentityService
    {
        if ($this->identityService === null) {
            $this->identityService = new NubariumIdentityService($this->tenant);
        }

        $service = $this->identityService
            ->forApplication($this->applicationId)
            ->forUser($this->userId);

        // Set entity context (preferred over legacy applicantId)
        if ($this->entityType && $this->entityId) {
            $service->setEntityContext($this->entityType, $this->entityId);
        }

        return $service;
    }

    /**
     * Get the biometrics service instance.
     */
    protected function biometrics(): NubariumBiometricsService
    {
        if ($this->biometricsService === null) {
            $this->biometricsService = new NubariumBiometricsService($this->tenant);
        }

        $service = $this->biometricsService
            ->forApplication($this->applicationId)
            ->forUser($this->userId);

        // Set entity context (preferred over legacy applicantId)
        if ($this->entityType && $this->entityId) {
            $service->setEntityContext($this->entityType, $this->entityId);
        }

        return $service;
    }

    /**
     * Get the compliance service instance.
     */
    protected function compliance(): NubariumComplianceService
    {
        if ($this->complianceService === null) {
            $this->complianceService = new NubariumComplianceService($this->tenant);
        }

        $service = $this->complianceService
            ->forApplication($this->applicationId)
            ->forUser($this->userId);

        // Set entity context (preferred over legacy applicantId)
        if ($this->entityType && $this->entityId) {
            $service->setEntityContext($this->entityType, $this->entityId);
        }

        return $service;
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return $this->identity()->isConfigured();
    }

    /**
     * Test API connection.
     */
    public function testConnection(): array
    {
        return $this->identity()->testConnection();
    }

    /**
     * Refresh JWT token.
     */
    public function refreshToken(): ?string
    {
        return $this->identity()->refreshToken();
    }

    /**
     * Clear token cache.
     */
    public function clearTokenCache(): void
    {
        $this->identity()->clearTokenCache();
    }

    // ==================== Identity Methods ====================

    /**
     * Validate a CURP.
     */
    public function validateCurp(string $curp): array
    {
        return $this->identity()->validateCurp($curp);
    }

    /**
     * Get CURP by personal data.
     */
    public function getCurp(array $data): array
    {
        return $this->identity()->getCurp($data);
    }

    /**
     * Validate RFC with SAT.
     */
    public function validateRfc(string $rfc): array
    {
        return $this->identity()->validateRfc($rfc);
    }

    /**
     * Get Mexican states for CURP.
     */
    public function getBirthStates(): array
    {
        return NubariumIdentityService::BIRTH_STATES;
    }

    // ==================== Biometrics Methods ====================

    /**
     * Extract data from INE using OCR.
     */
    public function extractIneData(string $frontImage, ?string $backImage = null): array
    {
        return $this->biometrics()->extractIneData($frontImage, $backImage);
    }

    /**
     * Validate INE against nominal list.
     */
    public function validateIneAgainstList(array $ineData): array
    {
        return $this->biometrics()->validateIneAgainstList($ineData);
    }

    /**
     * Full INE validation: OCR + list.
     */
    public function validateIne(string $frontImage, ?string $backImage = null, bool $validateAgainstList = true): array
    {
        return $this->biometrics()->validateIne($frontImage, $backImage, $validateAgainstList);
    }

    /**
     * Compare selfie with INE photo (Face Match).
     */
    public function validateFaceMatch(string $selfieImage, string $ineImage, int $threshold = 80): array
    {
        return $this->biometrics()->validateFaceMatch($selfieImage, $ineImage, $threshold);
    }

    /**
     * Validate liveness detection.
     */
    public function validateLiveness(string $faceImage): array
    {
        return $this->biometrics()->validateLiveness($faceImage);
    }

    /**
     * Get JWT token for Biometric SDK.
     */
    public function getBiometricToken(string $transactionId): array
    {
        return $this->biometrics()->getBiometricToken($transactionId);
    }

    // ==================== Compliance Methods ====================

    /**
     * Check OFAC & UN sanctions.
     */
    public function checkOfac(string $name, int $similarity = 80): array
    {
        return $this->compliance()->checkOfac($name, $similarity);
    }

    /**
     * Check Mexican PLD blacklists.
     */
    public function checkPldBlacklists(string $fullName, ?string $curp = null, int $similarity = 80): array
    {
        return $this->compliance()->checkPldBlacklists($fullName, $curp, $similarity);
    }

    /**
     * Validate SPEI CEP.
     */
    public function validateCep(array $data): array
    {
        return $this->compliance()->validateCep($data);
    }

    /**
     * Validate professional license.
     */
    public function validateCedulaProfesional(string $cedula): array
    {
        return $this->compliance()->validateCedulaProfesional($cedula);
    }

    /**
     * Validar CLABE contra el banco (Nubarium API Plus, asíncrono por webhook).
     *
     * @param  mixed  $entity
     */
    public function validateClabe(string $name, string $clabe, $entity = null): array
    {
        return $this->compliance()->validateClabe($name, $clabe, $entity);
    }

    /**
     * Validar tarjeta de débito vía CLABE (asíncrono por webhook).
     *
     * @param  mixed  $entity
     */
    public function validateDebitCard(string $name, string $clabe, $entity = null): array
    {
        return $this->compliance()->validateDebitCard($name, $clabe, $entity);
    }

    // ==================== Utility Methods ====================

    /**
     * Get available services.
     */
    public function getAvailableServices(): array
    {
        return self::SERVICES;
    }
}
