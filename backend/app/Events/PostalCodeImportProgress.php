<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Progreso en vivo de una importación del catálogo de códigos postales.
 *
 * Se emite por el canal privado `postal-codes-import.{importId}` para que la
 * pantalla de carga refleje el avance sin recargar. Fases:
 *  - `parsing`    → parseo a staging (con `processed` acumulado)
 *  - `validating` → validación de conteo mínimo
 *  - `ready`      → parseado y validado, listo para aplicar (con conteos)
 *  - `rejected`   → rechazado (con `reason`)
 *  - `applying`   → swap atómico en curso
 *  - `applied`    → catálogo reemplazado
 *
 * El respaldo sin WebSocket es `GET /catalogs/postal-codes/imports/{id}`, que
 * devuelve el mismo estado para degradar a polling si Echo no conecta.
 */
class PostalCodeImportProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $importId,
        public string $phase,
        public ?int $processed = null,
        public ?int $rows = null,
        public ?int $states = null,
        public ?int $municipalities = null,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("postal-codes-import.{$this->importId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }

    /**
     * Solo se serializan las llaves con valor; el frontend lee `phase` para
     * decidir qué mostrar y el resto según la fase.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'importId' => $this->importId,
            'phase' => $this->phase,
            'processed' => $this->processed,
            'rows' => $this->rows,
            'states' => $this->states,
            'municipalities' => $this->municipalities,
            'reason' => $this->reason,
        ], fn ($value) => $value !== null);
    }
}
