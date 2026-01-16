<?php

namespace App\Contracts;

/**
 * Interface for KYC (Know Your Customer) service providers.
 *
 * Allows swapping between Nubarium or other KYC providers
 * while maintaining consistent API for identity verification.
 */
interface KycServiceInterface
{
    /**
     * Validate a CURP against government records.
     *
     * @param string $curp The CURP to validate
     * @return array{success: bool, valid: bool, data?: array, error?: string}
     */
    public function validateCurp(string $curp): array;

    /**
     * Validate an RFC against SAT records.
     *
     * @param string $rfc The RFC to validate
     * @return array{success: bool, valid: bool, data?: array, error?: string}
     */
    public function validateRfc(string $rfc): array;

    /**
     * Extract data from INE document using OCR.
     *
     * @param string $imagePath Path to the INE image (front or back)
     * @param string $side 'front' or 'back'
     * @return array{success: bool, data?: array, error?: string}
     */
    public function extractIneData(string $imagePath, string $side = 'front'): array;

    /**
     * Validate INE against official registries.
     *
     * @param array $ineData INE data to validate (cic, ocr, claveElector, etc.)
     * @return array{success: bool, valid: bool, data?: array, error?: string}
     */
    public function validateIne(array $ineData): array;

    /**
     * Perform face matching between two images.
     *
     * @param string $selfieImagePath Path to the selfie image
     * @param string $documentImagePath Path to the document image (INE front)
     * @return array{success: bool, match: bool, score?: float, error?: string}
     */
    public function matchFaces(string $selfieImagePath, string $documentImagePath): array;

    /**
     * Check if the service is properly configured and authenticated.
     */
    public function isConfigured(): bool;

    /**
     * Get a list of available services/capabilities.
     *
     * @return array<string> List of available service names
     */
    public function getAvailableServices(): array;
}
