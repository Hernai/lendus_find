<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApiLog;
use App\Models\ApplicantAccount;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\StaffAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Feed unificado de actividad de una solicitud.
 *
 * Combina 3 fuentes en una sola linea de tiempo cronologica:
 *   - `application_status_history` (eventos de negocio del lifecycle)
 *   - `audit_logs`                  (trazabilidad de cambios + auth + HTTP)
 *   - `api_logs`                    (llamadas a APIs externas como Nubarium)
 *
 * Estrategia: ejecutamos 3 queries en paralelo limitando cada una por el
 * cursor temporal (`before`/`since`), luego mezclamos en PHP y reordenamos.
 * Para una solicitud tipica con ~50 eventos totales esto es O(50) y no
 * requiere vista materializada ni union SQL.
 *
 * Si el volumen crece (1000+ eventos por solicitud) se puede mover a una
 * vista materializada `application_activity_v` o agregar pagination keyset
 * mas sofisticado.
 */
class ActivityFeedService
{
    private const PAGE_SIZE_DEFAULT = 50;
    private const PAGE_SIZE_MAX = 200;

    /**
     * Caches por-request para evitar N+1 al resolver actores polimorficos en
     * `application_status_history.changed_by` (puede ser staff o applicant).
     * Se pueblan con 2 queries unicas (whereIn) en lugar de N queries lazy.
     *
     * @var Collection<string, StaffAccount>|null
     */
    private ?Collection $staffCache = null;
    /**
     * @var Collection<string, ApplicantAccount>|null
     */
    private ?Collection $applicantCache = null;

    /**
     * Mapa `application_status_history.id -> AuditLog` poblado en
     * loadStatusHistory(). Permite que mapStatusHistory enriquezca cada
     * evento con la geo/IP/dispositivo del audit log pareja (que
     * ActivityRecorder escribio en la misma transaccion).
     *
     * @var Collection<string, AuditLog>|null
     */
    private ?Collection $historyAuditCache = null;

    /**
     * Construye el feed para una solicitud.
     *
     * @param  array{
     *     cursor?: string|null,
     *     kind?: string|null,
     *     q?: string|null,
     *     per_page?: int|null,
     *     include_http?: bool,
     * }  $filters
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     next_cursor: string|null,
     *     has_more: bool,
     * }
     */
    public function forApplication(Application $application, array $filters = []): array
    {
        $perPage = max(1, min(self::PAGE_SIZE_MAX, (int) ($filters['per_page'] ?? self::PAGE_SIZE_DEFAULT)));
        $kind = $filters['kind'] ?? null;
        $search = $filters['q'] ?? null;
        $includeHttp = (bool) ($filters['include_http'] ?? false);
        // Cursor compuesto (timestamp, id). Necesario porque ActivityRecorder
        // escribe history + audit en la misma transaccion y comparten
        // timestamp exacto. Un cursor solo por timestamp duplicaria o
        // saltaria items entre paginas.
        [$beforeTs, $beforeId] = $this->decodeCursor($filters['cursor'] ?? null);

        $applicantAccountId = $application->person?->account_id;

        $items = collect();

        // 1. application_status_history (lifecycle events).
        if ($kind === null || $kind === 'event') {
            $items = $items->merge($this->loadStatusHistory($application, $beforeTs, $perPage + 1, $search));
        }

        // 2. audit_logs (trazabilidad). Excluimos HTTP_REQUEST por default
        //    para no inundar el feed con cada peticion del SPA.
        if ($kind === null || $kind === 'audit') {
            $items = $items->merge($this->loadAuditLogs($application, $applicantAccountId, $beforeTs, $perPage + 1, $includeHttp, $search));
        }

        // 3. api_logs (integraciones externas).
        if ($kind === null || $kind === 'api') {
            $items = $items->merge($this->loadApiLogs($application, $beforeTs, $perPage + 1, $search));
        }

        // Orden global desc por (timestamp, id) compuesto. El id como
        // tiebreaker es lo que hace que items con mismo timestamp tengan un
        // orden estable entre paginas.
        $sorted = $items
            ->sortBy(fn (array $i) => $i['timestamp'].'|'.$i['id'], descending: true)
            ->values();

        // Cursor compuesto en PHP: descarta los items "ya vistos" usando la
        // tupla (ts, id). Esto cubre el edge case donde varios items tienen
        // mismo timestamp y el cursor solo por ts saltaria/duplicaria.
        if ($beforeTs !== null) {
            $sorted = $sorted->filter(function (array $i) use ($beforeTs, $beforeId) {
                if ($i['timestamp'] < $beforeTs) return true;
                if ($i['timestamp'] === $beforeTs && $beforeId !== null) {
                    return strcmp($i['id'], $beforeId) < 0;
                }
                return false;
            })->values();
        }

        $page = $sorted->take($perPage);
        $hasMore = $sorted->count() > $perPage;
        $nextCursor = $hasMore && $page->isNotEmpty()
            ? $this->encodeCursor($page->last()['timestamp'], $page->last()['id'])
            : null;

        return [
            'items' => $page->values()->all(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadStatusHistory(Application $application, ?string $before, int $limit, ?string $search = null): Collection
    {
        $query = ApplicationStatusHistory::where('application_id', $application->id)
            ->orderByDesc('created_at')
            ->limit($limit);

        // <= en SQL para no perder items con timestamp == cursor. El tiebreak
        // por id en PHP filter despues descarta los ya vistos.
        if ($before) {
            $query->where('created_at', '<=', $before);
        }

        if ($search) {
            // Busca en `notes` (texto legible) y `to_status` (status code).
            // Postgres ILIKE es case-insensitive nativo.
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('notes', 'ILIKE', $like)
                  ->orWhere('to_status', 'ILIKE', $like)
                  ->orWhere('from_status', 'ILIKE', $like);
            });
        }

        $rows = $query->get();

        // Pre-resolver actor.name en batch para evitar N+1 desde el accessor
        // `changed_by_name` (polimorfico: staff o applicant). Hacemos 2 queries
        // unicas para todos los IDs en lugar de 1 query por item.
        $this->primeStatusHistoryActors($rows);

        // Pre-cargar los audit logs pareja (uno por evento, escrito por
        // ActivityRecorder con metadata.status_history_id). Asi los items
        // de tipo `event` tambien muestran geo/IP/dispositivo del request
        // que origino el cambio, sin necesidad de mostrar 2 items separados.
        $this->primeHistoryAuditPairs($rows);

        return $rows->map(fn (ApplicationStatusHistory $h) => $this->mapStatusHistory($h));
    }

    /**
     * @param  Collection<int, ApplicationStatusHistory>  $rows
     */
    private function primeHistoryAuditPairs($rows): void
    {
        if ($rows->isEmpty()) {
            $this->historyAuditCache = collect();
            return;
        }

        $ids = $rows->pluck('id')->all();
        // Postgres: metadata->>'status_history_id' extrae el campo como text.
        // Indice: si la tabla audit_logs crece mucho, vale la pena un GIN
        // sobre metadata o un indice expresion sobre (metadata->>'status_history_id').
        $audits = AuditLog::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw("metadata->>'status_history_id'"), $ids)
            ->get();

        $this->historyAuditCache = $audits->keyBy(fn (AuditLog $a) => $a->metadata['status_history_id'] ?? null);
    }

    /**
     * @param  Collection<int, ApplicationStatusHistory>  $rows
     */
    private function primeStatusHistoryActors($rows): void
    {
        $staffIds = $rows
            ->filter(fn (ApplicationStatusHistory $h) => $h->changed_by && in_array($h->changed_by_type, [StaffAccount::class, 'staff_accounts'], true))
            ->pluck('changed_by')
            ->unique()
            ->values();

        $applicantIds = $rows
            ->filter(fn (ApplicationStatusHistory $h) => $h->changed_by && in_array($h->changed_by_type, [ApplicantAccount::class, 'applicant_accounts'], true))
            ->pluck('changed_by')
            ->unique()
            ->values();

        if ($staffIds->isNotEmpty()) {
            $this->staffCache = StaffAccount::with('profile')
                ->whereIn('id', $staffIds)
                ->get()
                ->keyBy('id');
        }

        if ($applicantIds->isNotEmpty()) {
            $this->applicantCache = ApplicantAccount::whereIn('id', $applicantIds)
                ->get()
                ->keyBy('id');
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadAuditLogs(
        Application $application,
        ?string $applicantAccountId,
        ?string $before,
        int $limit,
        bool $includeHttp,
        ?string $search = null
    ): Collection {
        // Eager-load staff.profile y person para evitar N+1 al resolver
        // actor.name en mapAuditLog (~2 queries por item del feed).
        $query = AuditLog::query()
            ->with(['staff.profile', 'person'])
            ->where(function ($q) use ($application, $applicantAccountId) {
                $q->where('application_id', $application->id);
                if ($applicantAccountId) {
                    $q->orWhere(function ($q2) use ($applicantAccountId, $application) {
                        $q2->where('applicant_id', $applicantAccountId)
                            ->where('created_at', '>=', $application->created_at);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit($limit);

        if (! $includeHttp) {
            $query->where('action', '!=', 'HTTP_REQUEST');
        }

        if ($before) {
            $query->where('created_at', '<=', $before);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('action', 'ILIKE', $like)
                  ->orWhere('entity_type', 'ILIKE', $like)
                  ->orWhere('ip_address', 'ILIKE', $like)
                  ->orWhere('city', 'ILIKE', $like);
            });
        }

        return $query->get()->map(fn (AuditLog $l) => $this->mapAuditLog($l));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadApiLogs(Application $application, ?string $before, int $limit, ?string $search = null): Collection
    {
        // ApiLog usa polimorfismo `entity_type/entity_id` para asociar el
        // log con Person (persona fisica) o Company (persona moral). Una
        // solicitud puede ser de cualquiera de los dos; cubrimos ambos.
        $query = ApiLog::query()
            ->where(function ($q) use ($application) {
                $q->where('application_id', $application->id);
                if ($application->person_id) {
                    $q->orWhere(function ($q2) use ($application) {
                        $q2->where('entity_type', \App\Models\Person::class)
                            ->where('entity_id', $application->person_id);
                    });
                }
                if (! empty($application->company_id)) {
                    // Company model esta reservado para personas morales
                    // (no implementado aun, ver Application:20). Usamos el
                    // string del FQCN para no romper si la clase no existe.
                    $q->orWhere(function ($q2) use ($application) {
                        $q2->where('entity_type', 'App\\Models\\Company')
                            ->where('entity_id', $application->company_id);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($before) {
            $query->where('created_at', '<=', $before);
        }

        if ($search) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('provider', 'ILIKE', $like)
                  ->orWhere('service', 'ILIKE', $like)
                  ->orWhere('endpoint', 'ILIKE', $like)
                  ->orWhere('error_message', 'ILIKE', $like);
            });
        }

        return $query->get()->map(fn (ApiLog $l) => $this->mapApiLog($l));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStatusHistory(ApplicationStatusHistory $h): array
    {
        $action = $h->metadata['action'] ?? $h->to_status;
        $actorName = $this->resolveHistoryActorName($h);

        // Buscar el audit log pareja (mismo request) para enriquecer con
        // geolocalizacion + dispositivo del cambio. ActivityRecorder los
        // escribe en transaccion, asi que siempre que haya un audit, debe
        // estar aqui. Si no hay (eventos legacy pre-refactor), seguimos sin geo.
        $audit = $this->historyAuditCache?->get($h->id);

        $location = $audit ? (
            $this->formatLocation($audit->city, $audit->region, $audit->country)
            ?? $this->formatCoords($audit->latitude, $audit->longitude)
        ) : null;
        $device = $audit ? $this->formatDevice($audit->device_type, $audit->browser) : null;

        // Compone el summary con geo si esta disponible.
        $summary = $h->notes ?: $this->statusSummary($h);
        if ($location || $audit?->ip_address) {
            $summary = trim($summary.' · '.($location ?: $audit->ip_address));
        }
        if ($device) {
            $summary .= ' · '.$device;
        }

        return [
            'id' => 'evt_'.$h->id,
            'kind' => 'event',
            'timestamp' => optional($h->created_at)->toIso8601String(),
            'actor' => [
                'type' => $this->actorTypeFromHistory($h),
                'id' => $h->changed_by,
                'name' => $actorName,
            ],
            'title' => $this->humanizeAction($action ?? $h->to_status),
            'summary' => $summary,
            'severity' => $this->severityForAction($action ?? $h->to_status),
            'icon' => $this->iconForAction($action ?? $h->to_status),
            'metadata' => [
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'changed_by_type' => $h->changed_by_type,
                // Geo/device del audit pareja para que el frontend pueda
                // pintar el chip de ubicacion en items kind=event tambien.
                'ip_address' => $audit?->ip_address,
                'user_agent' => $audit?->user_agent,
                'city' => $audit?->city,
                'region' => $audit?->region,
                'country' => $audit?->country,
                'device_type' => $audit?->device_type,
                'browser' => $audit?->browser,
                'os' => $audit?->os,
                'latitude' => $audit?->latitude,
                'longitude' => $audit?->longitude,
                'raw' => $h->metadata,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAuditLog(AuditLog $l): array
    {
        $action = $l->action?->value ?? (string) $l->action;
        $actorName = $this->resolveAuditActorName($l);

        return [
            'id' => 'aud_'.$l->id,
            'kind' => 'audit',
            'timestamp' => optional($l->created_at)->toIso8601String(),
            'actor' => [
                'type' => $l->user_id ? 'staff' : ($l->applicant_id ? 'applicant' : 'system'),
                'id' => $l->user_id ?? $l->applicant_id,
                'name' => $actorName,
            ],
            'title' => $l->action?->label() ?? $this->humanizeAction($action),
            'summary' => $this->auditSummary($l),
            'severity' => $this->severityForAction($action),
            'icon' => $this->iconForAction($action),
            'metadata' => [
                'entity_type' => $l->entity_type,
                'entity_id' => $l->entity_id,
                'old_values' => $l->old_values,
                'new_values' => $l->new_values,
                'ip_address' => $l->ip_address,
                'user_agent' => $l->user_agent,
                'city' => $l->city,
                'region' => $l->region,
                'country' => $l->country,
                'device_type' => $l->device_type,
                'browser' => $l->browser,
                'os' => $l->os,
                'extra' => $l->metadata,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapApiLog(ApiLog $l): array
    {
        $success = (bool) $l->success;

        return [
            'id' => 'api_'.$l->id,
            'kind' => 'api',
            'timestamp' => optional($l->created_at)->toIso8601String(),
            'actor' => [
                'type' => 'system',
                'id' => $l->provider,
                'name' => $l->provider,
            ],
            'title' => sprintf('%s · %s', $l->provider, $l->service),
            'summary' => sprintf(
                '%s %s · %s · %dms',
                strtoupper($l->method ?? 'GET'),
                $l->endpoint ?? '',
                $success ? 'OK' : ('ERROR '.($l->error_code ?? '')),
                (int) ($l->duration_ms ?? 0)
            ),
            'severity' => $success ? 'info' : 'error',
            'icon' => 'lightning',
            'metadata' => [
                'provider' => $l->provider,
                'service' => $l->service,
                'endpoint' => $l->endpoint,
                'method' => $l->method,
                'success' => $success,
                'error_code' => $l->error_code,
                'error_message' => $l->error_message,
                'duration_ms' => $l->duration_ms,
                'cost' => $l->cost,
                'request' => $l->request_payload ?? $l->request_body,
                'response' => $l->response_payload ?? $l->response_body,
                'extra' => $l->metadata,
            ],
        ];
    }

    /**
     * Decodifica cursor compuesto a tupla [timestamp ISO, id].
     * Formato: base64("<iso_timestamp>|<item_id>"). Cursor v1 (solo timestamp)
     * tambien se acepta para compat con clientes en curso. Si el cursor es
     * malformado lo logueamos con warning para detectar clientes pasando
     * datos sucios o bugs de paginacion en el frontend.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function decodeCursor(?string $cursor): array
    {
        if (! $cursor) {
            return [null, null];
        }
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            \Illuminate\Support\Facades\Log::warning('Activity feed cursor base64 invalido', [
                'cursor' => substr($cursor, 0, 50),
            ]);
            return [null, null];
        }
        $parts = explode('|', $decoded, 2);
        $ts = $parts[0] ?? null;
        $id = $parts[1] ?? null;
        try {
            if ($ts !== null) {
                Carbon::parse($ts);
            }
            return [$ts, $id];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Activity feed cursor con timestamp invalido', [
                'cursor' => substr($cursor, 0, 50),
                'decoded' => substr($decoded, 0, 100),
                'error' => $e->getMessage(),
            ]);
            return [null, null];
        }
    }

    private function encodeCursor(?string $timestamp, ?string $id = null): ?string
    {
        if (! $timestamp) {
            return null;
        }
        return base64_encode($timestamp.'|'.($id ?? ''));
    }

    private function actorTypeFromHistory(ApplicationStatusHistory $h): string
    {
        return match ($h->changed_by_type) {
            StaffAccount::class, 'staff_accounts' => 'staff',
            ApplicantAccount::class, 'applicant_accounts' => 'applicant',
            default => 'system',
        };
    }

    private function resolveHistoryActorName(ApplicationStatusHistory $h): string
    {
        if (! $h->changed_by) {
            return 'Sistema';
        }

        $type = $h->changed_by_type;
        if (in_array($type, [StaffAccount::class, 'staff_accounts'], true)) {
            $staff = $this->staffCache?->get($h->changed_by);
            return $staff?->profile?->full_name
                ?? $staff?->name
                ?? $staff?->email
                ?? 'Staff';
        }
        if (in_array($type, [ApplicantAccount::class, 'applicant_accounts'], true)) {
            $applicant = $this->applicantCache?->get($h->changed_by);
            // ApplicantAccount no tiene full_name directo; intentamos profile o
            // email/phone como fallback razonable.
            return $applicant?->person?->full_name
                ?? $applicant?->email
                ?? $applicant?->phone
                ?? 'Solicitante';
        }

        return 'Sistema';
    }

    private function resolveAuditActorName(AuditLog $l): string
    {
        if ($l->user_id) {
            $staff = $l->staff;
            return $staff?->profile?->full_name
                ?? $staff?->name
                ?? $staff?->email
                ?? 'Staff';
        }
        if ($l->applicant_id) {
            return optional($l->person)->full_name ?? 'Solicitante';
        }
        return 'Sistema';
    }

    private function humanizeAction(?string $action): string
    {
        if (! $action) {
            return 'Evento';
        }
        $map = [
            'DRAFT'           => 'Borrador iniciado',
            'SUBMITTED'       => 'Solicitud enviada',
            'IN_REVIEW'       => 'En revisión',
            'DOCS_PENDING'    => 'Documentos pendientes',
            'APPROVED'        => 'Solicitud aprobada',
            'REJECTED'        => 'Solicitud rechazada',
            'CANCELLED'       => 'Solicitud cancelada',
            'SYNCED'          => 'Sincronizada con sistema externo',
            'BANK_VALIDATION_NUBARIUM' => 'Validación bancaria (Nubarium)',
            'RISK_QUERY_NUBARIUM' => 'Consulta de riesgo (Nubarium)',
        ];
        if (isset($map[$action])) {
            return $map[$action];
        }
        return ucfirst(mb_strtolower(str_replace('_', ' ', $action)));
    }

    private function statusSummary(ApplicationStatusHistory $h): string
    {
        if ($h->from_status && $h->to_status && $h->from_status !== $h->to_status) {
            return "{$h->from_status} → {$h->to_status}";
        }
        return '';
    }

    private function auditSummary(AuditLog $l): string
    {
        $bits = [];
        if ($l->entity_type) {
            $bits[] = class_basename($l->entity_type);
        }

        // Geo legible: preferimos "Ciudad, País" > coords GPS truncadas > IP.
        // En produccion con MaxMind sale ciudad; en mobile/web con permiso de
        // ubicacion sale coords del device; localhost/proxy cae al IP.
        $location = $this->formatLocation($l->city, $l->region, $l->country)
            ?? $this->formatCoords($l->latitude, $l->longitude);
        if ($location) {
            $bits[] = $location;
        } elseif ($l->ip_address) {
            $bits[] = $l->ip_address;
        }

        // Dispositivo: solo el tipo si esta resuelto (mobile/desktop/tablet),
        // mas el browser. El UA crudo va al expand del item.
        $device = $this->formatDevice($l->device_type, $l->browser);
        if ($device) {
            $bits[] = $device;
        }

        return implode(' · ', $bits);
    }

    /**
     * Formatea ciudad + region + pais en una sola linea legible.
     * Ej: "CDMX, MX" o "Tlalpan, CDMX, MX". Devuelve null si no hay datos.
     */
    private function formatLocation(?string $city, ?string $region, ?string $country): ?string
    {
        $parts = array_filter([$city, $region, $country], fn ($v) => ! empty($v));
        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Coordenadas truncadas a 4 decimales (~10m de precision visible) como
     * fallback cuando MaxMind no resolvio ciudad (ej. localhost) pero el
     * cliente mando lat/lng via headers X-Geo-Lat/Lng.
     */
    private function formatCoords(mixed $lat, mixed $lng): ?string
    {
        if ($lat === null || $lng === null) return null;
        return sprintf('%.4f, %.4f', (float) $lat, (float) $lng);
    }

    private function formatDevice(?string $deviceType, ?string $browser): ?string
    {
        $parts = array_filter([$deviceType, $browser], fn ($v) => ! empty($v));
        return $parts ? implode(' ', $parts) : null;
    }

    private function severityForAction(?string $action): string
    {
        if (! $action) {
            return 'info';
        }
        if (str_contains($action, 'REJECTED') || str_contains($action, 'FAILED') || str_contains($action, 'DELETED')) {
            return 'error';
        }
        if (str_contains($action, 'APPROVED') || str_contains($action, 'VERIFIED') || str_contains($action, 'SUCCESS')) {
            return 'success';
        }
        if (str_contains($action, 'PENDING') || str_contains($action, 'WARNING')) {
            return 'warning';
        }
        return 'info';
    }

    private function iconForAction(?string $action): string
    {
        if (! $action) {
            return 'circle';
        }
        if (str_contains($action, 'DOCUMENT')) {
            return 'document';
        }
        if (str_contains($action, 'LOGIN') || str_contains($action, 'OTP') || str_contains($action, 'PIN')) {
            return 'shield';
        }
        if (str_contains($action, 'KYC')) {
            return 'fingerprint';
        }
        if (str_contains($action, 'RISK') || str_contains($action, 'NUBARIUM')) {
            return 'shield';
        }
        if (str_contains($action, 'APPLICATION')) {
            return 'file';
        }
        if (str_contains($action, 'STATUS')) {
            return 'flag';
        }
        return 'circle';
    }
}
