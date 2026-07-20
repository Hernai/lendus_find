<?php

namespace Tests\Unit\Enums;

use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\HousingType;
use PHPUnit\Framework\TestCase;

/**
 * Cubre los ajustes de catálogos del onboarding de MoneyCapital:
 * EducationLevel::NONE, el relabel de EmploymentType::BUSINESS_OWNER a
 * "Comerciante" y la unificación de HousingType (mapeo de valores legacy).
 */
class OnboardingCatalogsEnumTest extends TestCase
{
    // =====================================================
    // EducationLevel::NONE ("Sin educación")
    // =====================================================

    public function test_education_level_none_label(): void
    {
        $this->assertSame('Sin educación', EducationLevel::NONE->label());
    }

    public function test_education_level_none_case_and_value(): void
    {
        $this->assertSame('NONE', EducationLevel::NONE->value);
    }

    public function test_education_level_options_include_none_and_the_seven_levels(): void
    {
        $values = array_keys(EducationLevel::toLabels());

        // NONE presente junto a los siete niveles preexistentes, sin faltantes.
        $expected = [
            'NONE',
            'PRIMARY',
            'SECONDARY',
            'HIGH_SCHOOL',
            'TECHNICAL',
            'BACHELOR',
            'MASTER',
            'DOCTORATE',
        ];
        foreach ($expected as $case) {
            $this->assertContains($case, $values, "Falta el nivel {$case} en el catálogo");
        }

        // Sin duplicados.
        $this->assertSame(count($values), count(array_unique($values)));

        // Todas las opciones tienen label no vacío (label() cubre NONE).
        foreach (EducationLevel::toOptions() as $opt) {
            $this->assertNotSame('', $opt['label']);
        }
    }

    public function test_education_level_normalize_none_and_legacy(): void
    {
        $this->assertSame(EducationLevel::NONE, EducationLevel::normalize('NONE'));
        $this->assertSame(EducationLevel::NONE, EducationLevel::normalize('none'));
        $this->assertSame(EducationLevel::NONE, EducationLevel::normalize('SIN EDUCACION'));
        $this->assertSame(EducationLevel::NONE, EducationLevel::normalize('  sin educacion  '));
    }

    // =====================================================
    // EmploymentType::BUSINESS_OWNER → "Comerciante"
    // =====================================================

    public function test_employment_business_owner_label_is_comerciante(): void
    {
        $this->assertSame('Comerciante', EmploymentType::BUSINESS_OWNER->label());
    }

    public function test_employment_business_owner_value_unchanged(): void
    {
        // El value backing no cambia para no romper datos ni scoring existentes.
        $this->assertSame('BUSINESS_OWNER', EmploymentType::BUSINESS_OWNER->value);
    }

    public function test_employment_normalize_comerciante_and_empresario(): void
    {
        $this->assertSame(EmploymentType::BUSINESS_OWNER, EmploymentType::normalize('COMERCIANTE'));
        $this->assertSame(EmploymentType::BUSINESS_OWNER, EmploymentType::normalize('comerciante'));
        // El legacy "EMPRESARIO" se sigue reconociendo.
        $this->assertSame(EmploymentType::BUSINESS_OWNER, EmploymentType::normalize('EMPRESARIO'));
        // El value canónico también.
        $this->assertSame(EmploymentType::BUSINESS_OWNER, EmploymentType::normalize('BUSINESS_OWNER'));
    }

    // =====================================================
    // HousingType — unificación al enum canónico
    // =====================================================

    /**
     * @return array<string, array{0: string, 1: HousingType}>
     */
    public static function housingLegacyProvider(): array
    {
        return [
            // Tokens legacy del frontend/store anterior.
            'OWN → OWNED_PAID' => ['OWN', HousingType::OWNED_PAID],
            'OWNED → OWNED_PAID' => ['OWNED', HousingType::OWNED_PAID],
            'RENT → RENTED' => ['RENT', HousingType::RENTED],
            'RENTED → RENTED' => ['RENTED', HousingType::RENTED],
            'MORTGAGED → OWNED_MORTGAGE' => ['MORTGAGED', HousingType::OWNED_MORTGAGE],
            'EMPLOYER → OTHER' => ['EMPLOYER', HousingType::OTHER],
            'FAMILY → FAMILY' => ['FAMILY', HousingType::FAMILY],
            // Valores canónicos (tryFrom).
            'OWNED_PAID canónico' => ['OWNED_PAID', HousingType::OWNED_PAID],
            'OWNED_MORTGAGE canónico' => ['OWNED_MORTGAGE', HousingType::OWNED_MORTGAGE],
            'BORROWED canónico' => ['BORROWED', HousingType::BORROWED],
            // Valores legacy en español.
            'RENTADA → RENTED' => ['RENTADA', HousingType::RENTED],
            'FAMILIAR → FAMILY' => ['FAMILIAR', HousingType::FAMILY],
            'PROPIA_PAGADA → OWNED_PAID' => ['PROPIA_PAGADA', HousingType::OWNED_PAID],
            'PROPIA_HIPOTECA → OWNED_MORTGAGE' => ['PROPIA_HIPOTECA', HousingType::OWNED_MORTGAGE],
            // Case-insensitive + trim.
            'lower own con espacios' => ['  own  ', HousingType::OWNED_PAID],
        ];
    }

    /**
     * @dataProvider housingLegacyProvider
     */
    public function test_housing_normalize_maps_to_canonical(string $input, HousingType $expected): void
    {
        $this->assertSame($expected, HousingType::normalize($input));
    }

    public function test_housing_normalize_unknown_returns_null_without_exception(): void
    {
        $this->assertNull(HousingType::normalize('NO_EXISTE'));
        $this->assertNull(HousingType::normalize(''));
    }
}
