<?php

namespace App\Console\Commands;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Re-encola las entregas RETRYING cuyo next_retry_at ya venció. Corre en el
 * scheduler cada minuto (routes/console.php).
 */
class DispatchWebhookRetries extends Command
{
    protected $signature = 'webhooks:dispatch-retries';

    protected $description = 'Re-encola entregas de webhook en reintento cuyo backoff ya venció';

    public function handle(): int
    {
        $due = WebhookDelivery::withoutTenant()
            ->where('status', WebhookDelivery::STATUS_RETRYING)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->limit(200)
            ->get();

        foreach ($due as $delivery) {
            DeliverWebhookJob::dispatch($delivery->id);
        }

        $this->info("Reintentos re-encolados: {$due->count()}");

        return self::SUCCESS;
    }
}
