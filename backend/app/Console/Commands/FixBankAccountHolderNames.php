<?php

namespace App\Console\Commands;

use App\Jobs\StartClabeValidationJob;
use App\Models\BankAccount;
use Illuminate\Console\Command;

/**
 * Corrige cuentas bancarias cuyo titular quedó guardado como un placeholder
 * ("TITULAR" o "TITULAR NO DEFINIDO") porque la cuenta se creó en el onboarding
 * antes de que el perfil tuviera nombre. Lo reemplaza por el nombre real de la
 * persona dueña y re-encola la validación de CLABE con Nubarium (que en el flujo
 * normal se dispara junto al backfill; ver ProfileController::backfillBankAccountHolders).
 *
 * Idempotente: solo toca filas con el placeholder cuya persona tenga nombre.
 */
class FixBankAccountHolderNames extends Command
{
    /** Placeholders que el onboarding dejó cuando aún no había nombre cargado. */
    private const PLACEHOLDERS = ['TITULAR', 'TITULAR NO DEFINIDO'];

    protected $signature = 'bank-accounts:fix-holder-names
        {--dry-run : Solo reporta, no modifica}';

    protected $description = 'Reemplaza el titular placeholder ("TITULAR" / "TITULAR NO DEFINIDO") por el nombre real y re-encola la validación de CLABE.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // En consola no hay tenant ligado, así que el global scope de HasTenant
        // es no-op y recorremos todas las cuentas de todos los tenants.
        $placeholders = implode(',', array_fill(0, count(self::PLACEHOLDERS), '?'));
        $accounts = BankAccount::query()
            ->whereRaw("UPPER(TRIM(holder_name)) IN ($placeholders)", self::PLACEHOLDERS)
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No hay cuentas con titular placeholder. Nada que corregir.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;
        $revalidated = 0;

        foreach ($accounts as $account) {
            $person = $account->person; // belongsTo Person via entity_id
            $fullName = trim((string) ($person->full_name ?? ''));

            if ($fullName === '') {
                $skipped++;
                $this->warn("· Cuenta {$account->id}: sin nombre de persona, se omite.");
                continue;
            }

            $newName = strtoupper($fullName);
            $this->line("· Cuenta {$account->id}: '{$account->holder_name}' → '{$newName}'");

            if (!$dryRun) {
                $account->holder_name = $newName;
                $account->save();

                // Ahora que hay titular real, dispara la validación de CLABE con
                // Nubarium si la cuenta es CLABE y aún no está verificada.
                if (!empty($account->clabe) && !$account->is_verified) {
                    StartClabeValidationJob::dispatch($account->id, $account->tenant_id);
                    $revalidated++;
                }
            }

            $fixed++;
        }

        $this->newLine();
        $verb = $dryRun ? 'Se corregirían' : 'Corregidas';
        $this->info("{$verb}: {$fixed}. Omitidas (sin nombre): {$skipped}. Validaciones CLABE encoladas: {$revalidated}.");

        if ($dryRun) {
            $this->comment('Modo dry-run: no se modificó nada. Corre sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }
}
