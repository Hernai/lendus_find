<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use App\Services\ExternalApi\Nubarium\NubariumBiometricsService;
use App\Services\ExternalApi\Nubarium\NubariumComplianceService;
use App\Services\ExternalApi\Nubarium\NubariumIdentityService;
use App\Services\ExternalApi\Nubarium\NubariumServiceFacade;

/**
 * Nubarium API Service for KYC/Identity Validation.
 *
 * This class now delegates to specialized services while maintaining
 * backward compatibility with the original API.
 *
 * Specialized services:
 * - NubariumIdentityService: CURP, RFC validation
 * - NubariumBiometricsService: INE OCR, Face Match, Liveness
 * - NubariumComplianceService: OFAC, PLD, IMSS
 *
 * @see https://documenter.nubarium.com/
 * @see \App\Services\ExternalApi\Nubarium\NubariumServiceFacade
 */
class NubariumService extends BaseExternalApiService
{
    protected string $provider = 'nubarium';
    protected string $serviceType = 'kyc';

    /**
     * The facade that delegates to specialized services.
     */
    protected NubariumServiceFacade $facade;

    /**
     * Available validation services.
     */
    public const SERVICES = [
        'curp' => 'Validación de CURP',
        'rfc' => 'Validación de RFC',
        'ine' => 'Validación de INE',
        'cedula_sep' => 'Validación de Cédula Profesional SEP',
        'spei_cep' => 'Validación de CEP SPEI',
        'imss' => 'Historial IMSS',
        'issste' => 'Historial ISSSTE',
        'ofac' => 'Consulta Lista OFAC',
        'biometric_token' => 'Token para SDK Biométrico',
    ];

    /**
     * Mexican states for CURP generation.
     */
    public const BIRTH_STATES = NubariumIdentityService::BIRTH_STATES;

    public function __construct(Tenant $tenant)
    {
        parent::__construct($tenant);
        $this->facade = new NubariumServiceFacade($tenant);
    }

    /**
     * Set the application context for API logging.
     */
    public function forApplication(?string $applicationId): static
    {
        parent::forApplication($applicationId);
        $this->facade->forApplication($applicationId);
        return $this;
    }

    /**
     * Set the user context for API logging.
     */
    public function forUser(?string $userId): static
    {
        parent::forUser($userId);
        $this->facade->forUser($userId);
        return $this;
    }

    /**
     * Set the entity context for API logging (Person or Company).
     */
    public function forEntity($entity): static
    {
        parent::forEntity($entity);
        $this->facade->forEntity($entity);
        return $this;
    }

    /**
     * Test API connection.
     */
    public function testConnection(): array
    {
        $result = $this->facade->testConnection();

        // Update test result in parent
        if ($result['success']) {
            $this->updateTestResult(true, null);
        } else {
            $this->updateTestResult(false, $result['error'] ?? 'Connection failed');
        }

        return $result;
    }

    /**
     * Force refresh JWT token.
     */
    public function refreshToken(): ?string
    {
        return $this->facade->refreshToken();
    }

    /**
     * Clear cached JWT token.
     */
    public function clearTokenCache(): void
    {
        $this->facade->clearTokenCache();
    }

    // ==================== Identity Methods ====================

    /**
     * Validate a CURP and retrieve associated data from RENAPO.
     */
    public function validateCurp(string $curp): array
    {
        return $this->facade->validateCurp($curp);
    }

    /**
     * Get CURP by personal data.
     */
    public function getCurp(array $data): array
    {
        return $this->facade->getCurp($data);
    }

    /**
     * Validate RFC with SAT.
     */
    public function validateRfc(string $rfc): array
    {
        return $this->facade->validateRfc($rfc);
    }

    // ==================== Biometrics Methods ====================

    /**
     * Extract data from INE/IFE using OCR.
     */
    public function extractIneData(string $frontImage, ?string $backImage = null): array
    {
        return $this->facade->extractIneData($frontImage, $backImage);
    }

    /**
     * Validate INE/IFE against the INE nominal list.
     */
    public function validateIneAgainstList(array $ineData): array
    {
        return $this->facade->validateIneAgainstList($ineData);
    }

    /**
     * Full INE validation: Extract data via OCR and validate against nominal list.
     */
    public function validateIne(string $frontImage, ?string $backImage = null, bool $validateAgainstList = true): array
    {
        return $this->facade->validateIne($frontImage, $backImage, $validateAgainstList);
    }

    /**
     * Compare a selfie image with an INE photo to verify identity (Face Match).
     */
    public function validateFaceMatch(string $selfieImage, string $ineImage, int $threshold = 80): array
    {
        return $this->facade->validateFaceMatch($selfieImage, $ineImage, $threshold);
    }

    /**
     * Validate liveness detection from a selfie image.
     */
    public function validateLiveness(string $faceImage): array
    {
        return $this->facade->validateLiveness($faceImage);
    }

    /**
     * Get JWT token for Biometric SDK.
     */
    public function getBiometricToken(string $transactionId): array
    {
        return $this->facade->getBiometricToken($transactionId);
    }

    // ==================== Compliance Methods ====================

    /**
     * Validate SPEI CEP (Comprobante Electrónico de Pago).
     */
    public function validateCep(array $data): array
    {
        return $this->facade->validateCep($data);
    }

    /**
     * Check OFAC & UN sanctions block lists.
     */
    public function checkOfac(string $name, int $similarity = 80): array
    {
        return $this->facade->checkOfac($name, $similarity);
    }

    /**
     * Check Mexican PLD (Prevención de Lavado de Dinero) blacklists.
     */
    public function checkPldBlacklists(string $fullName, ?string $curp = null, int $similarity = 80): array
    {
        return $this->facade->checkPldBlacklists($fullName, $curp, $similarity);
    }

    /**
     * Get IMSS employment history.
     */
    public function getImssHistory(string $curp, ?string $nss = null): array
    {
        return $this->facade->getImssHistory($curp, $nss);
    }

    /**
     * Validate professional license (Cédula Profesional).
     */
    public function validateCedulaProfesional(string $cedula): array
    {
        return $this->facade->validateCedulaProfesional($cedula);
    }

    // ==================== Utility Methods ====================

    /**
     * Get available services for this provider.
     */
    public function getAvailableServices(): array
    {
        return self::SERVICES;
    }

    /**
     * Get Mexican states for CURP.
     */
    public function getBirthStates(): array
    {
        return self::BIRTH_STATES;
    }
}
