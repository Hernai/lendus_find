<?php

namespace App\Services\PostalCode;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Resuelve el archivo subido a la ruta de un TXT/CSV parseable por
 * {@see PostalCodeImporter}.
 *
 * Acepta el ZIP oficial de SEPOMEX ("Descarga nacional") o el TXT/CSV ya
 * extraído. Ante un ZIP lo abre con ZipArchive, exige un ÚNICO archivo de datos
 * con el encabezado esperado (`d_codigo`/`d_asenta`) y lo extrae a un directorio
 * temporal controlado bajo `storage/app/{tmp_path}/`. Nunca usa los nombres
 * internos del zip como ruta (defensa contra path traversal) ni descomprime más
 * allá del límite configurado (defensa contra zip-bombs).
 */
class PostalCodeFileResolver
{
    /** Firma del encabezado que identifica al archivo de datos SEPOMEX. */
    private const HEADER_NEEDLES = ['d_codigo', 'd_asenta'];

    /** Bytes iniciales que se inspeccionan de cada entrada para detectar el encabezado. */
    private const HEADER_PEEK_BYTES = 8192;

    /**
     * Devuelve la ruta a un TXT/CSV listo para parsear.
     *
     * - `.txt`/`.csv`: devuelve `$uploadedPath` sin cambios.
     * - `.zip`: extrae el único archivo de datos a un directorio temporal y
     *   devuelve su ruta. El llamador debe invocar {@see cleanup()} con el
     *   directorio contenedor (`dirname()` de la ruta devuelta) al terminar.
     *
     * @throws \RuntimeException Formato no soportado, ZIP ilegible, 0 o >1
     *                           archivos de datos, o tamaño descomprimido excedido.
     */
    public function resolve(string $uploadedPath): string
    {
        if (! is_file($uploadedPath)) {
            throw new \RuntimeException("No existe el archivo: {$uploadedPath}");
        }

        $ext = strtolower(pathinfo($uploadedPath, PATHINFO_EXTENSION));

        if ($ext === 'zip' || $this->looksLikeZip($uploadedPath)) {
            return $this->extractFromZip($uploadedPath);
        }

        if (in_array($ext, ['txt', 'csv'], true)) {
            return $uploadedPath;
        }

        throw new \RuntimeException(
            'Formato no soportado: se esperaba un ZIP oficial de SEPOMEX o un TXT/CSV.'
        );
    }

    /**
     * Borra un directorio temporal de importación, solo si está contenido bajo
     * la base configurada (`storage/app/{tmp_path}`). Fuera de esa base es un
     * no-op, para no borrar por error la ubicación del archivo original.
     */
    public function cleanup(string $dir): void
    {
        $base = $this->tmpBase();
        $real = realpath($dir);
        $realBase = realpath($base);

        if ($real === false || $realBase === false) {
            return;
        }

        // Solo dentro de la base controlada.
        if ($real !== $realBase && ! str_starts_with($real, $realBase . DIRECTORY_SEPARATOR)) {
            return;
        }

        File::deleteDirectory($real);
    }

    /**
     * Abre el ZIP, valida que contenga un único archivo de datos con el
     * encabezado esperado, lo extrae (streaming, con tope de tamaño) a un
     * directorio único bajo la base temporal y devuelve la ruta del TXT.
     */
    private function extractFromZip(string $zipPath): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión PHP zip no está disponible en el servidor.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP.');
        }

        try {
            $dataEntries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = $stat['name'];

                // Ignora directorios y basura de macOS.
                if (str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) {
                    continue;
                }

                if ($this->entryHasHeader($zip, $name)) {
                    $dataEntries[] = $stat;
                }
            }

            if (count($dataEntries) === 0) {
                throw new \RuntimeException(
                    'El ZIP no contiene un archivo de datos con el encabezado esperado (d_codigo/d_asenta).'
                );
            }
            if (count($dataEntries) > 1) {
                throw new \RuntimeException(
                    'El ZIP contiene más de un archivo de datos; sube solo el catálogo (un único TXT/CSV).'
                );
            }

            $entry = $dataEntries[0];
            $maxBytes = $this->maxUncompressedBytes();

            // Chequeo temprano contra el tamaño declarado (se revalida al copiar).
            if (isset($entry['size']) && $entry['size'] > $maxBytes) {
                throw new \RuntimeException(
                    'El archivo descomprimido excede el límite de ' . $this->maxUploadMb() . ' MB.'
                );
            }

            return $this->extractEntry($zip, $entry['name'], $maxBytes);
        } finally {
            $zip->close();
        }
    }

    /**
     * Extrae una entrada del ZIP por streaming a un directorio temporal único,
     * abortando si el contenido descomprimido supera `$maxBytes`.
     */
    private function extractEntry(\ZipArchive $zip, string $entryName, int $maxBytes): string
    {
        $targetDir = $this->tmpBase() . '/' . Str::random(24);
        File::ensureDirectoryExists($targetDir, 0755);

        // basename(): nunca se usa la ruta interna del zip (anti path traversal).
        $targetPath = $targetDir . '/' . basename($entryName);

        $in = $zip->getStream($entryName);
        if ($in === false) {
            $this->cleanup($targetDir);
            throw new \RuntimeException("No se pudo leer la entrada del ZIP: {$entryName}");
        }

        $out = fopen($targetPath, 'w');
        if ($out === false) {
            fclose($in);
            $this->cleanup($targetDir);
            throw new \RuntimeException('No se pudo escribir el archivo temporal de importación.');
        }

        $written = 0;
        try {
            while (! feof($in)) {
                $buffer = fread($in, 1 << 20); // 1 MB
                if ($buffer === false) {
                    break;
                }
                $written += strlen($buffer);
                if ($written > $maxBytes) {
                    throw new \RuntimeException(
                        'El archivo descomprimido excede el límite de ' . $this->maxUploadMb() . ' MB.'
                    );
                }
                fwrite($out, $buffer);
            }
        } catch (\RuntimeException $e) {
            fclose($in);
            fclose($out);
            $this->cleanup($targetDir);
            throw $e;
        }

        fclose($in);
        fclose($out);

        return $targetPath;
    }

    /** Lee los primeros bytes de una entrada y detecta el encabezado esperado. */
    private function entryHasHeader(\ZipArchive $zip, string $entryName): bool
    {
        $stream = $zip->getStream($entryName);
        if ($stream === false) {
            return false;
        }

        $head = fread($stream, self::HEADER_PEEK_BYTES);
        fclose($stream);

        if ($head === false) {
            return false;
        }

        foreach (self::HEADER_NEEDLES as $needle) {
            if (stripos($head, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Detecta un ZIP por su firma mágica (PK\x03\x04), útil si falta la extensión. */
    private function looksLikeZip(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = fread($handle, 4);
        fclose($handle);

        return $magic === "PK\x03\x04";
    }

    /** Base temporal absoluta: storage/app/{tmp_path}. */
    private function tmpBase(): string
    {
        return storage_path('app/' . trim((string) config('postal_codes.tmp_path', 'tmp/postal-imports'), '/'));
    }

    private function maxUploadMb(): int
    {
        return (int) config('postal_codes.max_upload_mb', 30);
    }

    private function maxUncompressedBytes(): int
    {
        return $this->maxUploadMb() * 1024 * 1024;
    }
}
