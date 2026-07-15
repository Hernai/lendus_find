<?php

namespace App\Services\Decision;

use App\Models\Application;
use App\Models\DecisionPolicy;
use App\Models\Person;
use Carbon\Carbon;

/**
 * Cooldown post-rechazo (Regla 01): tras una solicitud REJECTED, la persona no
 * puede crear otra en el tenant hasta cumplir los días configurados en la
 * política de tenant (default 30). Solo el rechazo real cuenta — CANCELLED
 * (oferta expirada, rechazo del cliente) y fallas técnicas no bloquean.
 * El staff puede levantar el bloqueo con motivo auditado (cooldown_waived_*).
 */
class CooldownService
{
    /**
     * Estado de cooldown de la persona en el tenant. null = sin bloqueo.
     *
     * @return array{blocked_until: string, rejected_at: string, application_id: string, days_left: int}|null
     */
    public function status(Person $person, string $tenantId): ?array
    {
        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('product_id')
            ->where('is_active', true)
            ->first();

        if (!$policy || !$policy->modeEnum()->evaluates()) {
            return null;
        }

        $days = (int) $policy->rule('cooldown.days', 30);
        if ($days <= 0) {
            return null;
        }

        $rejected = Application::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('person_id', $person->id)
            ->where('status', Application::STATUS_REJECTED)
            ->whereNull('cooldown_waived_at')
            ->whereNull('deleted_at')
            ->where('decision_at', '>=', now()->subDays($days))
            ->orderByDesc('decision_at')
            ->first();

        if (!$rejected) {
            return null;
        }

        // En sombra se registra el estado pero no se bloquea (lo decide el caller).
        $rejectedAt = Carbon::parse($rejected->decision_at);
        $blockedUntil = $rejectedAt->copy()->addDays($days);

        return [
            'application_id' => $rejected->id,
            'rejected_at' => $rejectedAt->toIso8601String(),
            'blocked_until' => $blockedUntil->toIso8601String(),
            'days_left' => max(1, (int) ceil(now()->diffInHours($blockedUntil, false) / 24)),
            'enforced' => $policy->modeEnum()->executes(),
        ];
    }
}
