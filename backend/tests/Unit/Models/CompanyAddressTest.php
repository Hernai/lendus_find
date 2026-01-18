<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAddressTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->company = Company::factory()->for($this->tenant)->create();
    }

    // =====================================================
    // Relationship Tests
    // =====================================================

    public function test_belongs_to_tenant(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create();

        $this->assertInstanceOf(Tenant::class, $address->tenant);
        $this->assertEquals($this->tenant->id, $address->tenant->id);
    }

    public function test_belongs_to_company(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create();

        $this->assertInstanceOf(Company::class, $address->company);
        $this->assertEquals($this->company->id, $address->company->id);
    }

    public function test_belongs_to_verified_by_staff(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->verified()
            ->create(['verified_by' => $staff->id]);

        $this->assertInstanceOf(StaffAccount::class, $address->verifiedByStaff);
        $this->assertEquals($staff->id, $address->verifiedByStaff->id);
    }

    public function test_can_have_previous_version(): void
    {
        $oldAddress = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->historical()
            ->create();

        $newAddress = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['previous_version_id' => $oldAddress->id]);

        $this->assertInstanceOf(CompanyAddress::class, $newAddress->previousVersion);
        $this->assertEquals($oldAddress->id, $newAddress->previousVersion->id);
    }

    // =====================================================
    // Accessor Tests
    // =====================================================

    public function test_full_address_accessor(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create([
                'street' => 'Av. Reforma',
                'exterior_number' => '222',
                'interior_number' => 'Piso 10',
                'neighborhood' => 'Juárez',
                'municipality' => 'Cuauhtémoc',
                'state' => 'CDMX',
                'postal_code' => '06600',
            ]);

        $fullAddress = $address->full_address;

        $this->assertStringContainsString('Av. Reforma', $fullAddress);
        $this->assertStringContainsString('222', $fullAddress);
        $this->assertStringContainsString('Int. Piso 10', $fullAddress);
        $this->assertStringContainsString('Juárez', $fullAddress);
        $this->assertStringContainsString('C.P. 06600', $fullAddress);
    }

    public function test_full_address_without_interior(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create([
                'street' => 'Calle Principal',
                'exterior_number' => '100',
                'interior_number' => null,
                'neighborhood' => 'Centro',
                'municipality' => 'Centro',
                'state' => 'CDMX',
                'postal_code' => '06000',
            ]);

        $fullAddress = $address->full_address;

        $this->assertStringNotContainsString('Int.', $fullAddress);
    }

    public function test_short_address_accessor(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create([
                'street' => 'Av. Insurgentes',
                'exterior_number' => '500',
                'neighborhood' => 'Roma Norte',
            ]);

        $this->assertEquals('Av. Insurgentes 500, Roma Norte', $address->short_address);
    }

    public function test_type_label_accessor(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->fiscal()
            ->create();

        $this->assertEquals('Domicilio Fiscal', $address->type_label);
    }

    // =====================================================
    // Status Helper Tests
    // =====================================================

    public function test_is_verified(): void
    {
        $verified = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->verified()
            ->create();

        $pending = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['status' => CompanyAddress::STATUS_PENDING]);

        $this->assertTrue($verified->isVerified());
        $this->assertFalse($pending->isVerified());
    }

    public function test_is_pending(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['status' => CompanyAddress::STATUS_PENDING]);

        $this->assertTrue($address->isPending());
    }

    public function test_is_current(): void
    {
        $current = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['is_current' => true]);

        $historical = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->historical()
            ->create();

        $this->assertTrue($current->isCurrent());
        $this->assertFalse($historical->isCurrent());
    }

    // =====================================================
    // Action Tests
    // =====================================================

    public function test_verify_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['status' => CompanyAddress::STATUS_PENDING]);

        $address->verify($staff->id);

        $this->assertEquals(CompanyAddress::STATUS_VERIFIED, $address->status);
        $this->assertNotNull($address->verified_at);
        $this->assertEquals($staff->id, $address->verified_by);
    }

    public function test_reject_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['status' => CompanyAddress::STATUS_PENDING]);

        $address->reject($staff->id);

        $this->assertEquals(CompanyAddress::STATUS_REJECTED, $address->status);
        $this->assertNotNull($address->verified_at);
        $this->assertEquals($staff->id, $address->verified_by);
    }

    public function test_mark_as_replaced_action(): void
    {
        $address = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create(['is_current' => true]);

        $newAddress = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->create();

        $address->markAsReplaced($newAddress->id);

        $this->assertFalse($address->is_current);
        $this->assertNotNull($address->replaced_at);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_current_scope(): void
    {
        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->count(3)
            ->create(['is_current' => true]);

        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->historical()
            ->count(2)
            ->create();

        $current = CompanyAddress::current()->get();

        $this->assertCount(3, $current);
    }

    public function test_verified_scope(): void
    {
        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->verified()
            ->count(2)
            ->create();

        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->count(3)
            ->create(['status' => CompanyAddress::STATUS_PENDING]);

        $verified = CompanyAddress::verified()->get();

        $this->assertCount(2, $verified);
    }

    public function test_by_type_scope(): void
    {
        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->fiscal()
            ->create();

        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->headquarters()
            ->count(2)
            ->create();

        $fiscal = CompanyAddress::byType(CompanyAddress::TYPE_FISCAL)->get();
        $headquarters = CompanyAddress::byType(CompanyAddress::TYPE_HEADQUARTERS)->get();

        $this->assertCount(1, $fiscal);
        $this->assertCount(2, $headquarters);
    }

    public function test_fiscal_scope(): void
    {
        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->fiscal()
            ->count(2)
            ->create();

        CompanyAddress::factory()
            ->for($this->tenant)
            ->for($this->company)
            ->headquarters()
            ->create();

        $fiscal = CompanyAddress::fiscal()->get();

        $this->assertCount(2, $fiscal);
    }

    // =====================================================
    // Static Method Tests
    // =====================================================

    public function test_types_static_method(): void
    {
        $types = CompanyAddress::types();

        $this->assertIsArray($types);
        $this->assertArrayHasKey(CompanyAddress::TYPE_FISCAL, $types);
        $this->assertArrayHasKey(CompanyAddress::TYPE_HEADQUARTERS, $types);
        $this->assertArrayHasKey(CompanyAddress::TYPE_BRANCH, $types);
        $this->assertArrayHasKey(CompanyAddress::TYPE_WAREHOUSE, $types);
    }
}
