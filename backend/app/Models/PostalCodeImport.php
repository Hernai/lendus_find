<?php

namespace App\Models;

use App\Enums\PostalCodeImportStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial y máquina de estado de una importación del catálogo de códigos
 * postales cargada desde el panel.
 *
 * Tabla GLOBAL de auditoría: NO usa el trait HasTenant. El catálogo de códigos
 * postales es compartido y todo SUPER_ADMIN debe ver el historial completo sin
 * filtro de tenant; `origin_tenant_id`/`origin_tenant_slug` son informativos.
 */
class PostalCodeImport extends Model
{
    use HasUuid;

    protected $fillable = [
        'staff_account_id',
        'origin_tenant_id',
        'origin_tenant_slug',
        'original_filename',
        'status',
        'rows_count',
        'states_count',
        'municipalities_count',
        'sample',
        'rejection_reason',
    ];

    protected $casts = [
        'status' => PostalCodeImportStatus::class,
        'sample' => 'array',
        'rows_count' => 'integer',
        'states_count' => 'integer',
        'municipalities_count' => 'integer',
    ];
}
