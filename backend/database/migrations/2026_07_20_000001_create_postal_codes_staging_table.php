<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de staging del catálogo de códigos postales.
 *
 * Réplica exacta de `postal_codes` (misma estructura, mismo índice de `cp`,
 * sin timestamps, sin tenant, sin UUID). El job de importación del panel
 * parsea el archivo hacia aquí y, tras validar, hace un swap atómico por
 * RENAME contra `postal_codes` (ver cambio OpenSpec catalogo-cp-autoservicio).
 * Tras el swap conserva el catálogo viejo; se trunca al inicio de la
 * siguiente carga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes_staging', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cp', 5)->index();
            $table->string('asentamiento', 150);       // colonia / asentamiento
            $table->string('tipo_asentamiento', 60)->nullable();
            $table->string('municipio', 150);
            $table->string('estado', 100);
            $table->string('ciudad', 150)->nullable();
            $table->string('estado_clave', 5)->nullable();    // c_estado (clave INEGI)
            $table->string('municipio_clave', 5)->nullable(); // c_mnpio
            // Sin timestamps: réplica del catálogo estático.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes_staging');
    }
};
