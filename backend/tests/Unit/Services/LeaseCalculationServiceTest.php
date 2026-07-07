<?php

namespace Tests\Unit\Services;

use App\Services\LeaseCalculationService;
use App\Services\LoanCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests del motor de cálculo de ARRENDAMIENTO.
 *
 * Valida la renta, el anticipo, el valor residual y —lo más importante— que el
 * DESEMBOLSO INICIAL (primera cuota) sea la suma exacta de sus componentes.
 */
class LeaseCalculationServiceTest extends TestCase
{
    private LeaseCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeaseCalculationService(new LoanCalculationService());
    }

    /** Config base de arrendamiento financiero. */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'modality' => 'FINANCIERO',
            'purchase_option' => true,
            'residual_value_pct' => 15,
            'pago_anticipado' => true,
            'numero_rentas_anticipadas' => 1,
            'deposito_garantia_meses' => 1,
            'comision_apertura_pct' => 1.5,
            'iva_pct' => 16,
        ], $overrides);
    }

    /** @test */
    public function financiero_anticipado_desglosa_el_desembolso_inicial(): void
    {
        $r = $this->service->calculateLeaseSimulation(500000, 20, 36, 'MONTHLY', 18, $this->config());
        $l = $r['lease'];

        $this->assertGreaterThan(0, $l['monthly_rental']);
        $this->assertEquals(100000.0, $l['down_payment']);          // 20% de 500k
        $this->assertEquals(75000.0, $l['residual_value']);         // 15% de 500k
        $this->assertEquals(75000.0, $l['purchase_option_amount']);

        // La renta con IVA = renta * 1.16.
        $this->assertEqualsWithDelta($l['monthly_rental'] * 1.16, $l['monthly_rental_with_iva'], 0.01);

        // INVARIANTE: el total del desembolso inicial es la suma de sus componentes.
        $fi = $l['first_installment'];
        $suma = round(
            $fi['anticipo'] + $fi['comision'] + $fi['comision_iva']
            + $fi['deposito_reembolsable'] + $fi['rentas_anticipadas'],
            2
        );
        $this->assertEqualsWithDelta($fi['total'], $suma, 0.01);

        // Con pago anticipado, la primera renta va en el desembolso.
        $this->assertGreaterThan(0, $fi['rentas_anticipadas']);
    }

    /** @test */
    public function puro_sin_opcion_de_compra_amortiza_el_bien_completo(): void
    {
        $cfg = $this->config(['modality' => 'PURO', 'purchase_option' => false, 'residual_value_pct' => null]);
        $r = $this->service->calculateLeaseSimulation(300000, 10, 24, 'MONTHLY', 20, $cfg);
        $l = $r['lease'];

        $this->assertEquals(0.0, $l['residual_value']);             // sin residual
        $this->assertEquals(0.0, $l['purchase_option_amount']);
        // financiado = valor - anticipo (no se descuenta residual).
        $this->assertEqualsWithDelta(300000 - 30000, $l['financed_amount'], 0.01);
    }

    /** @test */
    public function vencido_no_incluye_renta_anticipada(): void
    {
        $r = $this->service->calculateLeaseSimulation(500000, 20, 36, 'MONTHLY', 18, $this->config(['pago_anticipado' => false]));
        $l = $r['lease'];

        $this->assertFalse($l['is_pago_anticipado']);
        $this->assertEquals(0.0, $l['first_installment']['rentas_anticipadas']);
    }

    /** @test */
    public function tasa_cero_no_truena_y_renta_es_base_entre_n(): void
    {
        $cfg = $this->config([
            'residual_value_pct' => 0, 'purchase_option' => false,
            'comision_apertura_pct' => 0, 'deposito_garantia_meses' => 0, 'pago_anticipado' => false,
        ]);
        $r = $this->service->calculateLeaseSimulation(120000, 0, 12, 'MONTHLY', 0, $cfg);
        $l = $r['lease'];

        // base = 120000 (anticipo 0%, residual 0); renta = 120000/12 = 10000.
        $this->assertEqualsWithDelta(10000.0, $l['monthly_rental'], 0.01);
    }
}
