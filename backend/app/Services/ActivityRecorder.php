<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicantAccount;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\StaffAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * Helper centralizado para registrar actividad sobre solicitudes y entidades.
 *
 * Cada accion de negocio escribe a DOS tablas:
 *   1. `application_status_history` cuando es un evento del lifecycle de la
 *      solicitud (status change, doc approval, etc.). Mantiene el historial
 *      narrativo, con `notes` legibles para staff.
 *   2. `audit_logs` siempre. Es el log centralizado de auditoria, con
 *      entity_type/entity_id/old_values/new_values + trazabilidad de
 *      request (IP, UA, geo, device) auto-capturada por AuditLog::log().
 *
 * El feed unificado `/applications/{id}/activity` LEE de ambas tablas y de
 * `api_logs` para presentar todo cronologicamente.
 *
 * Patron de uso:
 *
 *   ActivityRecorder::recordApplicationEvent($app, 'DOCUMENT_APPROVED', [
 *       'notes'      => "Documento INE_FRONT aprobado",
 *       'entity'     => $document,
 *       'new_values' => ['status' => 'APPROVED'],
 *       'old_values' => ['status' => 'PENDING'],
 *   ]);
 *
 * El actor (changed_by, user_id) se resuelve automaticamente desde el
 * `request()->user()`.
 */
class ActivityRecorder
{
    /**
     * Registra un evento de lifecycle sobre una solicitud.
     *
     * Escribe a `application_status_history` (siempre) Y a `audit_logs`
     * (siempre). Las opciones determinan que campos se rellenan en cada lado.
     *
     * @param  Application  $application  Solicitud sobre la que ocurre el evento.
     * @param  string  $action  Valor del enum AuditAction (ej. 'DOCUMENT_APPROVED').
     * @param  array{
     *     from_status?: string|null,
     *     to_status?: string|null,
     *     notes?: string|null,
     *     entity?: Model|null,
     *     old_values?: array<string, mixed>|null,
     *     new_values?: array<string, mixed>|null,
     *     metadata?: array<string, mixed>|null,
     * }  $options
     * @return array{history: ApplicationStatusHistory, audit: AuditLog|null}
     */
    public static function recordApplicationEvent(
        Application $application,
        string $action,
        array $options = [],
        ?Request $request = null
    ): array {
        $request ??= request();
        $actor = self::resolveActor($request, $options);

        // Reconstruir el shape historico del registro `application_status_history`.
        // `from_status`/`to_status` son lo que define el tipo de cambio (puede ser
        // status real o lifecycle event como 'DOCUMENT_APPROVED' inline).
        $fromStatus = $options['from_status'] ?? $application->status;
        $toStatus = $options['to_status'] ?? $action;

        $history = ApplicationStatusHistory::create([
            'application_id'  => $application->id,
            'from_status'     => $fromStatus,
            'to_status'       => $toStatus,
            'changed_by'      => $actor['id'],
            'changed_by_type' => $actor['type'],
            'notes'           => $options['notes'] ?? null,
            'metadata'        => self::buildHistoryMetadata($action, $options),
            'created_at'      => now(),
        ]);

        $audit = null;
        try {
            // Si el caller paso un actor explicito en options, propagamos
            // user_id/applicant_id al audit log derivandolos del actor
            // resuelto. Si no, AuditLog::log() los infiere del request.
            $auditOpts = [
                'application_id' => $application->id,
                'entity_type'    => self::resolveEntityType($options['entity'] ?? null),
                'entity_id'      => isset($options['entity']) && $options['entity'] instanceof Model
                    ? $options['entity']->getKey()
                    : null,
                'old_values'     => $options['old_values'] ?? null,
                'new_values'     => $options['new_values'] ?? null,
                'metadata'       => array_merge(
                    $options['metadata'] ?? [],
                    [
                        'notes'             => $options['notes'] ?? null,
                        'status_history_id' => $history->id,
                    ]
                ),
            ];

            // Propagar actor explicito al audit log cuando viene de options
            // (Jobs/console). Sin esto AuditLog::log() lo inferiria del
            // request, lo cual es incorrecto si el caller real es otro.
            if ($actor['type'] === StaffAccount::class) {
                $auditOpts['user_id'] = $actor['id'];
            } elseif ($actor['type'] === ApplicantAccount::class) {
                $auditOpts['applicant_id'] = $actor['id'];
            }

            $audit = AuditLog::log($action, $application->tenant_id, $auditOpts);
        } catch (Throwable) {
            // Audit logging es secundario - si falla no debemos romper la accion
            // de negocio. El historial principal ya quedo persistido.
        }

        return ['history' => $history, 'audit' => $audit];
    }

    /**
     * Registra un cambio en una entidad SIN crear entrada en
     * application_status_history. Util para cambios que no son del lifecycle
     * de la solicitud (ej. actualizacion de perfil de aplicante, cambio de
     * configuracion de tenant).
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public static function recordEntityChange(
        Model $entity,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
        ?Application $application = null,
        ?Request $request = null
    ): ?AuditLog {
        $request ??= request();
        $tenantId = $application?->tenant_id
            ?? ($entity->tenant_id ?? null)
            ?? $request->attributes->get('tenant')?->id;

        try {
            return AuditLog::log($action, $tenantId, [
                'application_id' => $application?->id,
                'entity_type'    => $entity::class,
                'entity_id'      => $entity->getKey(),
                'old_values'     => $oldValues,
                'new_values'     => $newValues,
                'metadata'       => $metadata,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resuelve el actor (changed_by/user_id). Prioridad:
     *   1. options['actor_id'] + options['actor_type'] (overrides explicitos)
     *   2. inferencia desde request->user()
     *   3. system (id=null)
     *
     * Util cuando el evento se dispara desde un Job, comando de consola o
     * codigo donde el actor real ya esta resuelto fuera del request HTTP.
     *
     * @param  array<string, mixed>  $options
     * @return array{id: string|null, type: string|null}
     */
    private static function resolveActor(Request $request, array $options = []): array
    {
        if (array_key_exists('actor_id', $options) || array_key_exists('actor_type', $options)) {
            return [
                'id' => $options['actor_id'] ?? null,
                'type' => $options['actor_type'] ?? null,
            ];
        }

        $user = $request->user();
        if ($user instanceof StaffAccount) {
            return ['id' => $user->id, 'type' => StaffAccount::class];
        }
        if ($user instanceof ApplicantAccount) {
            return ['id' => $user->id, 'type' => ApplicantAccount::class];
        }
        return ['id' => null, 'type' => null];
    }

    /**
     * @return class-string|null
     */
    private static function resolveEntityType(mixed $entity): ?string
    {
        return $entity instanceof Model ? $entity::class : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private static function buildHistoryMetadata(string $action, array $options): array
    {
        $metadata = $options['metadata'] ?? [];
        $metadata['action'] = $action;

        if (isset($options['entity']) && $options['entity'] instanceof Model) {
            $metadata['entity_type'] = $options['entity']::class;
            $metadata['entity_id'] = $options['entity']->getKey();
        }

        if (isset($options['old_values'])) {
            $metadata['old_values'] = $options['old_values'];
        }
        if (isset($options['new_values'])) {
            $metadata['new_values'] = $options['new_values'];
        }

        return $metadata;
    }
}
