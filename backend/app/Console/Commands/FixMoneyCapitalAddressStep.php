<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Reactiva el paso de DOMICILIO en el onboarding de MoneyCapital: quita la
 * `condition` = 'unless_kyc_provider' del paso `address` en `onboarding_steps`
 * de los productos del tenant. Con proveedor KYC (Nubarium) esa condición
 * excluía el paso del flujo y el domicilio nunca se capturaba (llegaba vacío al
 * admin), aunque el INE/Nubarium NO aporta domicilio.
 *
 * Actualiza el dato YA sembrado en prod sin re-correr el seeder completo.
 * Idempotente: solo toca pasos `address` que aún tengan `condition`.
 */
class FixMoneyCapitalAddressStep extends Command
{
    protected $signature = 'moneycapital:fix-address-step
        {--tenant=moneycapital : Slug del tenant a corregir}
        {--dry-run : Solo reporta, no modifica}';

    protected $description = 'Quita la condición unless_kyc_provider del paso address en onboarding_steps para que el domicilio siempre se capture.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slug = (string) $this->option('tenant');

        $tenant = Tenant::where('slug', $slug)->first();
        if (!$tenant) {
            $this->error("No existe el tenant con slug '{$slug}'.");

            return self::FAILURE;
        }

        $products = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->get();

        if ($products->isEmpty()) {
            $this->info("El tenant '{$slug}' no tiene productos. Nada que corregir.");

            return self::SUCCESS;
        }

        $fixed = 0;

        foreach ($products as $product) {
            $steps = $product->onboarding_steps ?? [];
            if (!is_array($steps) || $steps === []) {
                continue;
            }

            $changed = false;
            foreach ($steps as $i => $step) {
                $isAddress = ($step['id'] ?? null) === 'address' || ($step['type'] ?? null) === 'address';
                if ($isAddress && array_key_exists('condition', $step)) {
                    unset($steps[$i]['condition']);
                    $changed = true;
                    $this->line("· Producto {$product->id}: quitada 'condition' del paso address.");
                }
            }

            if ($changed) {
                if (!$dryRun) {
                    $product->onboarding_steps = array_values($steps);
                    $product->save();
                }
                $fixed++;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Se corregirían' : 'Corregidos';
        $this->info("{$verb}: {$fixed} producto(s).");

        if ($fixed === 0) {
            $this->comment('Ningún paso address tenía condition (ya estaba corregido o no existe el paso).');
        }
        if ($dryRun) {
            $this->comment('Modo dry-run: no se modificó nada. Corre sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }
}
