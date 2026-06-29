<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de códigos postales (SEPOMEX). Referencia global: sin tenant, sin
 * UUID, sin timestamps. Una fila por asentamiento (colonia).
 */
class PostalCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cp',
        'asentamiento',
        'tipo_asentamiento',
        'municipio',
        'estado',
        'ciudad',
        'estado_clave',
        'municipio_clave',
    ];
}
