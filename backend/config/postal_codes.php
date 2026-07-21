<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catálogo de códigos postales (SEPOMEX)
    |--------------------------------------------------------------------------
    |
    | Parámetros de la carga/actualización del catálogo global `postal_codes`
    | desde el panel (staging + swap atómico) y del comando `postal-codes:import`.
    | El catálogo nacional ronda las ~145 mil filas y 32 estados; los umbrales
    | mínimos rechazan archivos truncados antes de reemplazar el catálogo vigente.
    |
    */

    // Mínimo de filas válidas en staging para permitir el swap.
    'min_rows' => (int) env('POSTAL_CODES_MIN_ROWS', 100000),

    // Mínimo de estados distintos en staging para permitir el swap.
    'min_states' => (int) env('POSTAL_CODES_MIN_STATES', 32),

    // Límite de tamaño (MB) del archivo subido y del contenido descomprimido de un ZIP.
    'max_upload_mb' => (int) env('POSTAL_CODES_MAX_UPLOAD_MB', 30),

    // Tabla de staging donde se parsea el archivo antes del swap.
    'staging_table' => env('POSTAL_CODES_STAGING_TABLE', 'postal_codes_staging'),

    // Ruta (relativa a storage/app) para los archivos temporales de importación.
    'tmp_path' => env('POSTAL_CODES_TMP_PATH', 'tmp/postal-imports'),

];
