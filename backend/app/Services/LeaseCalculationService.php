<?php

namespace App\Services;

use App\Models\Application;

/**
 * Motor de cálculo de ARRENDAMIENTO (leasing) para arrendadoras.
 *
 * A diferencia del crédito (interés sobre un principal), el arrendamiento cobra
 * una RENTA por el uso del bien, con anticipo/enganche, valor residual + opción
 * de compra, y un DESEMBOLSO INICIAL (la "primera cuota" que se paga al firmar).
 *
 * Reutiliza la anualidad francesa de LoanCalculationService para la renta y
 * separa el desembolso inicial de la renta recurrente. No toca el flujo de
 * crédito. Toda la config sale de `products.rules.lease` con defaults por modalidad.
 */
class LeaseCalculationService
{
    public function __construct(
        private LoanCalculationService $loan,
    ) {
    }

    /**
     * Snapshot financiero (sub-objeto `lease`) de una solicitud de ARRENDAMIENTO a
     * partir de su producto + metadata. Sirve de BACKFILL en los formatters cuando
     * `metadata.lease.simulation` no se guardó al crear (solicitudes viejas), para
     * que renta mensual y desembolso inicial se muestren igual. No persiste; calcula.
     *
     * @return array<string, mixed>|null
     */
    public function snapshotForApplication(Application $app): ?array
    {
        $product = $app->product;
        if (!$product) {
            return null;
        }
        $leaseConfig = $product->rules['lease'] ?? [];
        $meta = $app->metadata['lease'] ?? [];
        $assetValue = (float) ($meta['asset_estimated_value'] ?? $app->requested_amount ?? 0);
        if ($assetValue <= 0) {
            return null;
        }
        $term = (int) ($meta['term_months'] ?? $app->requested_term_months ?? 12);
        $downPct = (float) ($meta['simulation']['down_payment_pct'] ?? $leaseConfig['anticipo_pct_default'] ?? 20);

        try {
            $calc = $this->calculateLeaseSimulation(
                $assetValue,
                $downPct,
                $term,
                'MONTHLY',
                (float) $product->annual_rate,
                $leaseConfig,
            );

            return $calc['lease'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cotiza un arrendamiento.
     *
     * @param  array<string, mixed>  $leaseConfig  products.rules.lease
     * @return array<string, mixed>
     */
    public function calculateLeaseSimulation(
        float $assetValue,
        float $downPaymentPct,
        int $termMonths,
        string $frequency,
        float $annualRate,
        array $leaseConfig = [],
    ): array {
        // --- Config con defaults por modalidad ---
        $modality = strtoupper((string) ($leaseConfig['modality'] ?? 'FINANCIERO'));
        $purchaseOption = (bool) ($leaseConfig['purchase_option'] ?? ($modality === 'FINANCIERO'));

        $residualRaw = $leaseConfig['residual_value_pct'] ?? null;
        $residualPct = $residualRaw === null
            ? ($purchaseOption ? 20.0 : 0.0)
            : (float) $residualRaw;

        $pagoAnticipado = (bool) ($leaseConfig['pago_anticipado'] ?? true);
        $numRentasAnticipadas = max(0, (int) ($leaseConfig['numero_rentas_anticipadas'] ?? 1));
        $depositoMeses = (float) ($leaseConfig['deposito_garantia_meses'] ?? 1);
        $comisionPct = (float) ($leaseConfig['comision_apertura_pct'] ?? 1.5);
        $ivaPct = (float) ($leaseConfig['iva_pct'] ?? 16);

        // --- Anualidad ---
        $r = $this->loan->calculatePeriodicRate($annualRate, $frequency);
        $n = $this->loan->calculateTotalPeriods($termMonths, $frequency);

        $anticipo = round($assetValue * $downPaymentPct / 100, 2);
        $residual = round($assetValue * $residualPct / 100, 2);

        // El residual NO se paga en la renta: es globo/opción de compra al final,
        // así que se descuenta a valor presente de la base amortizable.
        $pvResidual = $r > 0 ? $residual / pow(1 + $r, $n) : $residual;
        $base = max(0.0, ($assetValue - $anticipo) - $pvResidual);

        // Renta vencida (fórmula francesa reutilizada); anticipada = vencida/(1+r).
        $rentaVencida = $this->loan->calculatePayment($base, $annualRate, $termMonths, $frequency);
        $renta = ($pagoAnticipado && $r > 0) ? round($rentaVencida / (1 + $r), 2) : $rentaVencida;
        $rentaConIva = round($renta * (1 + $ivaPct / 100), 2);

        // --- Desembolso inicial (primera cuota) ---
        $comisionBase = max(0.0, $assetValue - $anticipo);
        $comision = round($comisionBase * $comisionPct / 100, 2);
        $comisionIva = round($comision * $ivaPct / 100, 2);
        $deposito = round($depositoMeses * $renta, 2);                 // reembolsable, sin IVA
        $rentasAnticipadas = $pagoAnticipado ? round($numRentasAnticipadas * $rentaConIva, 2) : 0.0;
        $ivaRentasAnt = $pagoAnticipado ? round($numRentasAnticipadas * $renta * $ivaPct / 100, 2) : 0.0;
        $ivaTotal = round($comisionIva + $ivaRentasAnt, 2);
        $desembolsoInicial = round($anticipo + $comision + $comisionIva + $deposito + $rentasAnticipadas, 2);

        // --- Totales ---
        $purchaseOptionAmount = $purchaseOption ? $residual : 0.0;
        $totalRentas = round($rentaConIva * $n, 2);
        // El depósito se reembolsa al final, no es costo real.
        $costoTotal = round($desembolsoInicial + $totalRentas - $deposito, 2);
        $totalInterest = round($totalRentas - $base, 2);
        $cat = $this->loan->calculateCAT($base, $totalRentas, $comision, $termMonths);

        return [
            // Claves de compatibilidad (el mapeo actual del frontend las lee).
            'requested_amount' => round($assetValue, 2),
            'term_months' => $termMonths,
            'payment_frequency' => strtoupper($frequency),
            'annual_rate' => $annualRate,
            'periodic_rate' => round($r * 100, 4),
            'total_periods' => $n,
            'payment_amount' => $rentaConIva,      // la "cuota" visible = renta con IVA
            'opening_commission_rate' => $comisionPct,
            'opening_commission' => $comision,
            'net_amount' => $base,
            'total_to_pay' => $costoTotal,
            'total_interest' => $totalInterest,
            'cat' => $cat,

            // Detalle específico de arrendamiento.
            'lease' => [
                'modality' => $modality,
                'asset_value' => round($assetValue, 2),
                'down_payment_pct' => $downPaymentPct,
                'down_payment' => $anticipo,
                'financed_amount' => $base,
                'monthly_rental' => $renta,
                'monthly_rental_with_iva' => $rentaConIva,
                'iva_pct' => $ivaPct,
                'is_pago_anticipado' => $pagoAnticipado,
                'first_installment' => [
                    'anticipo' => $anticipo,
                    'comision' => $comision,
                    'comision_iva' => $comisionIva,
                    'deposito_reembolsable' => $deposito,
                    'rentas_anticipadas' => $rentasAnticipadas,
                    'iva_total' => $ivaTotal,
                    'total' => $desembolsoInicial,
                ],
                'next_payment_amount' => $rentaConIva,
                'next_payment_offset_days' => 30,
                'residual_value' => $residual,
                'residual_value_pct' => $residualPct,
                'purchase_option' => $purchaseOption,
                'purchase_option_amount' => $purchaseOptionAmount,
                'total_rentas' => $totalRentas,
                'total_cost' => $costoTotal,
            ],
        ];
    }
}
