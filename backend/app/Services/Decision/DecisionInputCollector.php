<?php

namespace App\Services\Decision;

use App\Enums\KycStatus;
use App\Enums\SalaryRange;
use App\Models\Application;
use App\Models\NubariumAsyncValidation;
use App\Models\RiskAssessment;

/**
 * Recolecta los insumos que el motor de decisión evalúa sobre una solicitud:
 * riesgo telefónico, KYC, validación de CLABE, variables declarativas y
 * geolocalización. Reporta también qué insumos siguen pendientes (asíncronos
 * por naturaleza) para que el job reintente con backoff antes de decidir.
 */
class DecisionInputCollector
{
    /**
     * @return array{inputs: array<string, mixed>, missing: string[]}
     */
    public function collect(Application $application): array
    {
        $person = $application->person;
        $account = $person?->account;
        $missing = [];

        // --- Riesgo telefónico (RunContactRiskJob corre async post-OTP) ---
        $phoneRisk = null;
        if ($account || $person) {
            $phoneRisk = RiskAssessment::withoutGlobalScopes()
                ->where('tenant_id', $application->tenant_id)
                ->where('type', RiskAssessment::TYPE_PHONE_RISK)
                ->where(function ($q) use ($account, $person) {
                    if ($account) {
                        $q->orWhere('account_id', $account->id);
                    }
                    if ($person) {
                        $q->orWhere('person_id', $person->id);
                    }
                })
                ->orderByDesc('created_at')
                ->first();
        }

        // --- KYC (se completa durante el onboarding; PENDING = aún corriendo) ---
        $kycStatus = $person?->kyc_status;
        if (in_array($kycStatus, [KycStatus::PENDING->value, KycStatus::IN_PROGRESS->value], true)) {
            $missing[] = 'kyc';
        }

        // --- CLABE (resultado llega por webhook de Nubarium) ---
        $bankAccounts = $person?->bankAccounts ?? collect();
        $bank = [
            'has_account' => $bankAccounts->isNotEmpty(),
            'is_verified' => (bool) $bankAccounts->first()?->is_verified,
            'clabe_result' => $bankAccounts->first()?->verification_data['nubarium_clabe'] ?? null,
        ];
        if ($bankAccounts->isNotEmpty()) {
            $pendingClabe = NubariumAsyncValidation::withoutGlobalScopes()
                ->where('tenant_id', $application->tenant_id)
                ->where('type', NubariumAsyncValidation::TYPE_CLABE)
                ->whereIn('entity_id', $bankAccounts->pluck('id'))
                ->where('status', NubariumAsyncValidation::STATUS_PENDING)
                ->exists();
            if ($pendingClabe) {
                $missing[] = 'clabe_validation';
            }
        }

        // --- Variables declarativas y verificables para el scoring ---
        $employment = $person?->currentEmployment;
        $address = $person?->currentHomeAddress;
        $variables = [
            'education_level' => $person?->education_level,
            'marital_status' => $person?->marital_status,
            'employment_type' => $employment?->employment_type,
            'salary_range' => SalaryRange::fromIncome($employment?->monthly_income)?->value,
            'online_loans_count' => $this->normalizeLoansCount(
                $application->metadata['online_loans_count']
                    ?? $application->metadata['credit_history']
                    ?? null
            ),
            'phone_risk_level' => $phoneRisk?->level,
            'state' => $address?->state,
            'city' => $address?->city,
        ];

        // Bandera de inconsistencia de identidad detectada en el KYC (si el
        // flujo la registró en kyc_data); ausencia = sin inconsistencia.
        $identityMismatch = (bool) data_get($person?->kyc_data, 'ine_verification.mismatch', false);

        // Resultado del facematch (selfie vs INE) que el onboarding registra en
        // kyc_data: true (coincide), false (no coincide) o null (no concluyó / no
        // se ejecutó). El motor solo lo evalúa si la política incluye la regla.
        $faceMatch = data_get($person?->kyc_data, 'face_match.passed', null);

        return [
            'inputs' => [
                'kyc_status' => $kycStatus,
                'identity_mismatch' => $identityMismatch,
                'face_match' => $faceMatch,
                'phone_risk' => $phoneRisk ? [
                    'status' => $phoneRisk->status,
                    'score' => $phoneRisk->score,
                    'level' => $phoneRisk->level,
                    'recommendation' => $phoneRisk->recommendation,
                ] : null,
                'bank' => $bank,
                'variables' => $variables,
            ],
            'missing' => $missing,
        ];
    }

    /**
     * Normaliza el conteo de créditos declarados a las llaves del mapa de
     * puntos ('0'..'4', '5+').
     */
    private function normalizeLoansCount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $count = (int) $value;

        return $count >= 5 ? '5+' : (string) $count;
    }
}
