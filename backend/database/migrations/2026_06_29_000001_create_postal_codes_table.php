<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de códigos postales de México (SEPOMEX / Correos de México).
 *
 * Tabla de REFERENCIA global (no por tenant, sin UUID): una fila por
 * asentamiento (colonia). Se consulta por `cp` para autollenar estado,
 * municipio y ofrecer las colonias en el alta de domicilio.
 * Se importa con `php artisan postal-codes:import <archivo>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cp', 5)->index();
            $table->string('asentamiento', 150);       // colonia / asentamiento
            $table->string('tipo_asentamiento', 60)->nullable();
            $table->string('municipio', 150);
            $table->string('estado', 100);
            $table->string('ciudad', 150)->nullable();
            $table->string('estado_clave', 5)->nullable();    // c_estado (clave INEGI)
            $table->string('municipio_clave', 5)->nullable(); // c_mnpio
            // Sin timestamps: catálogo estático.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
