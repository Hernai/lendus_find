#!/usr/bin/env php
<?php

/*
 * Script para sincronizar verificaciones KYC faltantes
 *
 * Este script busca applicants que tienen datos validados por KYC
 * (CURP, RFC, INE clave, etc.) pero NO tienen registros en data_verifications.
 *
 * Uso: php backend/sync-kyc-verifications.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Applicant;
use App\Models\DataVerification;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;

echo "========================================\n";
echo "Sincronización de Verificaciones KYC\n";
echo "========================================\n\n";

// Get all applicants with KYC data but missing verifications
$applicants = Applicant::whereNotNull('curp')
    ->orWhereNotNull('rfc')
    ->orWhereNotNull('ine_clave')
    ->with('dataVerifications')
    ->get();

echo "Total applicants con datos KYC: " . $applicants->count() . "\n\n";

$synced = 0;
$skipped = 0;

foreach ($applicants as $applicant) {
    $verificationsToCreate = [];

    // Check existing verifications
    $existingFields = $applicant->dataVerifications->pluck('field_name')->toArray();

    echo "Applicant: {$applicant->first_name} {$applicant->last_name_1} (ID: {$applicant->id})\n";
    echo "  Existing verifications: " . count($existingFields) . "\n";

    // CURP verification
    if ($applicant->curp && !in_array('curp', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'curp',
            'field_value' => $applicant->curp,
            'method' => VerificationMethod::KYC_CURP_RENAPO,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: CURP\n";
    }

    // RFC verification
    if ($applicant->rfc && !in_array('rfc', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'rfc',
            'field_value' => $applicant->rfc,
            'method' => VerificationMethod::KYC_RFC_SAT,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: RFC\n";
    }

    // INE Clave verification
    if ($applicant->ine_clave && !in_array('ine_clave', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'ine_clave',
            'field_value' => $applicant->ine_clave,
            'method' => VerificationMethod::KYC_INE_LIST,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: INE Clave\n";
    }

    // First name (from INE OCR)
    if ($applicant->first_name && !in_array('first_name', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'first_name',
            'field_value' => $applicant->first_name,
            'method' => VerificationMethod::KYC_INE_OCR,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script', 'ine_ocr' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: first_name\n";
    }

    // Last names (from INE OCR)
    if ($applicant->last_name_1 && !in_array('last_name_1', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'last_name_1',
            'field_value' => $applicant->last_name_1,
            'method' => VerificationMethod::KYC_INE_OCR,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script', 'ine_ocr' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: last_name_1\n";
    }

    // Birth date (from INE OCR)
    if ($applicant->birth_date && !in_array('birth_date', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'birth_date',
            'field_value' => $applicant->birth_date->format('Y-m-d'),
            'method' => VerificationMethod::KYC_INE_OCR,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script', 'ine_ocr' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: birth_date\n";
    }

    // Birth state (from CURP)
    if ($applicant->birth_state && !in_array('birth_state', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'birth_state',
            'field_value' => $applicant->birth_state,
            'method' => VerificationMethod::KYC_CURP_RENAPO,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script', 'curp_extraction' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: birth_state\n";
    }

    // Gender (from INE/CURP)
    if ($applicant->gender && !in_array('gender', $existingFields)) {
        $verificationsToCreate[] = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'field_name' => 'gender',
            'field_value' => $applicant->gender->value,
            'method' => VerificationMethod::KYC_INE_OCR,
            'is_verified' => true,
            'is_locked' => true,
            'status' => VerificationStatus::VERIFIED,
            'notes' => 'Sincronizado automáticamente (KYC previo)',
            'metadata' => json_encode(['source' => 'sync_script']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        echo "  ✅ Agregando verificación: gender\n";
    }

    if (count($verificationsToCreate) > 0) {
        // Insert all verifications at once
        DataVerification::insert($verificationsToCreate);

        // Mark identity as verified if not already
        if (!$applicant->identity_verified_at) {
            $applicant->update(['identity_verified_at' => now()]);
            echo "  ✅ Marcado identity_verified_at\n";
        }

        $synced++;
        echo "  ✅ Sincronizado: " . count($verificationsToCreate) . " verificaciones\n\n";
    } else {
        $skipped++;
        echo "  ⏭️  Sin cambios (ya tenía todas las verificaciones)\n\n";
    }
}

echo "\n========================================\n";
echo "Resumen:\n";
echo "- Total applicants revisados: " . $applicants->count() . "\n";
echo "- Applicants sincronizados: $synced\n";
echo "- Applicants sin cambios: $skipped\n";
echo "========================================\n";
