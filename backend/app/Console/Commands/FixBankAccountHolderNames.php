<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use Illuminate\Console\Command;

/**
 * Corrige cuentas bancarias cuyo titular quedó guardado como el literal
 * "TITULAR" (placeholder que el onboarding mandaba cuando el perfil aún no
 * tenía nombre cargado en el store). Lo reemplaza por el nombre real de la
 * persona dueña de la cuenta.
 *
 * Idempotente: solo toca filas con holder_name = 'TITULAR' (case-insensitive)
 * cuya persona tenga un nombre disponible.
 */
class FixBankAccountHolderNames extends Command
{
    protected $signature = 'bank-accounts:fix-holder-names
        {--dry-run : Solo reporta, no modifica}';

    protected $description = 'Reemplaza el titular "TITULAR" por el nombre real de la persona dueña de la cuenta.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // En consola no hay tenant ligado, así que el global scope de HasTenant
        // es no-op y recorremos todas las cuentas de todos los tenants.
        $accounts = BankAccount::query()
            ->whereRaw('UPPER(TRIM(holder_name)) = ?', ['TITULAR'])
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No hay cuentas con titular "TITULAR". Nada que corregir.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            $person = $account->person; // belongsTo Person via entity_id
            $fullName = trim((string) ($person->full_name ?? ''));

            if ($fullName === '') {
                $skipped++;
                $this->warn("· Cuenta {$account->id}: sin nombre de persona, se omite.");
                continue;
            }

            $newName = strtoupper($fullName);
            $this->line("· Cuenta {$account->id}: 'TITULAR' → '{$newName}'");

            if (!$dryRun) {
                $account->holder_name = $newName;
                $account->save();
            }

            $fixed++;
        }

        $this->newLine();
        $verb = $dryRun ? 'Se corregirían' : 'Corregidas';
        $this->info("{$verb}: {$fixed}. Omitidas (sin nombre): {$skipped}.");

        if ($dryRun) {
            $this->comment('Modo dry-run: no se modificó nada. Corre sin --dry-run para aplicar.');
        }

        return self::SUCCESS;
    }
}
