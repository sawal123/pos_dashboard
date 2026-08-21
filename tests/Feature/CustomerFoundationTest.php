<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFoundationTest extends TestCase
{
    use RefreshDatabase;

    // --- Foundation Tests ---

    public function test_customer_can_be_created_for_business(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka No. 10',
            'notes' => 'Regular customer',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'business_id' => $business->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka No. 10',
            'notes' => 'Regular customer',
            'status' => 'active',
        ]);
        $this->assertNotNull($customer->id);
    }

    public function test_customer_belongs_to_business(): void
    {
        $business = Business::factory()->create();
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertNotNull($customer->business);
        $this->assertSame($business->id, $customer->business->id);
    }

    public function test_business_can_have_multiple_customers(): void
    {
        $business = Business::factory()->create();

        $cust1 = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Customer Satu']);
        $cust2 = Customer::factory()->create(['business_id' => $business->id, 'name' => 'Customer Dua']);

        $this->assertCount(2, $business->customers);
        $this->assertTrue($business->customers->contains($cust1));
        $this->assertTrue($business->customers->contains($cust2));
    }

    public function test_customer_requires_valid_business_id(): void
    {
        $this->expectException(QueryException::class);

        Customer::create([
            'business_id' => 999999,
            'name' => 'Orphan Customer',
        ]);
    }

    // --- Tenant Isolation Tests ---

    public function test_customers_of_business_a_do_not_appear_in_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $custA = Customer::factory()->create(['business_id' => $businessA->id, 'name' => 'Customer A']);
        $custB = Customer::factory()->create(['business_id' => $businessB->id, 'name' => 'Customer B']);

        $this->assertCount(1, $businessA->customers);
        $this->assertTrue($businessA->customers->contains($custA));
        $this->assertFalse($businessA->customers->contains($custB));

        $this->assertCount(1, $businessB->customers);
        $this->assertTrue($businessB->customers->contains($custB));
        $this->assertFalse($businessB->customers->contains($custA));
    }

    public function test_customers_of_business_b_do_not_appear_in_business_a(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $custA = Customer::factory()->create(['business_id' => $businessA->id]);
        $custB = Customer::factory()->create(['business_id' => $businessB->id]);

        $this->assertFalse($businessA->customers->contains($custB));
        $this->assertFalse($businessB->customers->contains($custA));
    }

    // --- Field & Nullability Tests ---

    public function test_customer_name_is_stored(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Siti Nurhaliza',
        ]);

        $this->assertSame('Siti Nurhaliza', $customer->name);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Siti Nurhaliza']);
    }

    public function test_customer_phone_can_be_null(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'No Phone Customer',
            'phone' => null,
        ]);

        $this->assertNull($customer->phone);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'phone' => null]);
    }

    public function test_customer_email_can_be_null(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'No Email Customer',
            'email' => null,
        ]);

        $this->assertNull($customer->email);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'email' => null]);
    }

    public function test_customer_address_can_be_null(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'No Address Customer',
            'address' => null,
        ]);

        $this->assertNull($customer->address);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'address' => null]);
    }

    public function test_customer_notes_can_be_null(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'No Notes Customer',
            'notes' => null,
        ]);

        $this->assertNull($customer->notes);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'notes' => null]);
    }

    public function test_customer_with_all_contact_fields_null_is_valid(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Walk-in Customer',
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => null,
        ]);

        $this->assertNotNull($customer->id);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->email);
        $this->assertNull($customer->address);
        $this->assertNull($customer->notes);
        $this->assertSame('active', $customer->status);
    }

    // --- Duplicate Realistic Data Tests ---

    public function test_duplicate_customer_names_in_same_business_are_allowed(): void
    {
        $business = Business::factory()->create();

        $cust1 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Budi',
            'phone' => '0811111111',
        ]);

        $cust2 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Budi',
            'phone' => '0822222222',
        ]);

        $this->assertDatabaseHas('customers', ['id' => $cust1->id, 'name' => 'Budi']);
        $this->assertDatabaseHas('customers', ['id' => $cust2->id, 'name' => 'Budi']);
    }

    public function test_duplicate_phone_numbers_in_same_business_are_allowed(): void
    {
        $business = Business::factory()->create();

        $cust1 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Suami',
            'phone' => '08123456789',
        ]);

        $cust2 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Istri',
            'phone' => '08123456789',
        ]);

        $this->assertDatabaseHas('customers', ['id' => $cust1->id, 'phone' => '08123456789']);
        $this->assertDatabaseHas('customers', ['id' => $cust2->id, 'phone' => '08123456789']);
    }

    public function test_duplicate_emails_in_same_business_are_allowed(): void
    {
        $business = Business::factory()->create();

        $cust1 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Staff 1',
            'email' => 'shared@company.com',
        ]);

        $cust2 = Customer::create([
            'business_id' => $business->id,
            'name' => 'Staff 2',
            'email' => 'shared@company.com',
        ]);

        $this->assertDatabaseHas('customers', ['id' => $cust1->id, 'email' => 'shared@company.com']);
        $this->assertDatabaseHas('customers', ['id' => $cust2->id, 'email' => 'shared@company.com']);
    }

    // --- Status Tests ---

    public function test_default_status_of_customer_is_active(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Active Customer',
        ]);

        $this->assertSame('active', $customer->status);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);
    }

    public function test_inactive_customer_can_be_created(): void
    {
        $business = Business::factory()->create();

        $customer = Customer::factory()->inactive()->create([
            'business_id' => $business->id,
        ]);

        $this->assertSame('inactive', $customer->status);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'inactive']);
    }

    // --- Cascade Safety Tests ---

    public function test_deleting_business_cascades_and_deletes_its_customers(): void
    {
        $business = Business::factory()->create();
        $customer = Customer::factory()->create([
            'business_id' => $business->id,
        ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);

        $business->delete();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_deleting_business_a_does_not_delete_customers_of_business_b(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();

        $custA = Customer::factory()->create(['business_id' => $businessA->id]);
        $custB = Customer::factory()->create(['business_id' => $businessB->id]);

        $businessA->delete();

        $this->assertDatabaseMissing('customers', ['id' => $custA->id]);
        $this->assertDatabaseHas('customers', ['id' => $custB->id]);
    }

    // --- Factory Tests ---

    public function test_factory_can_create_valid_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->assertNotNull($customer->id);
        $this->assertNotEmpty($customer->name);
        $this->assertSame('active', $customer->status);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_factory_inactive_state_produces_inactive_status(): void
    {
        $customer = Customer::factory()->inactive()->create();

        $this->assertSame('inactive', $customer->status);
    }
}
