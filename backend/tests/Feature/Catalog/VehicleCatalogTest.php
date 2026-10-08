<?php

namespace Tests\Feature\Catalog;

use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_filters_by_brand(): void
    {
        Vehicle::factory()->approved()->create(['brand' => 'Toyota']);
        Vehicle::factory()->approved()->create(['brand' => 'Honda']);

        $response = $this->getJson('/api/vehicles?brand=Toyota');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Toyota', $response->json('data.0.brand'));
    }

    public function test_catalog_filters_by_price_range(): void
    {
        Vehicle::factory()->approved()->create(['price' => 100_000_000]);
        Vehicle::factory()->approved()->create(['price' => 500_000_000]);

        $response = $this->getJson('/api/vehicles?price_min=200000000');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_draft_listings_are_excluded(): void
    {
        Vehicle::factory()->create();

        $response = $this->getJson('/api/vehicles');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_catalog_cache_does_not_hide_a_listing_just_approved_by_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::PendingReview]);

        // Warm the cache with a response from before the vehicle is approved.
        $this->getJson('/api/vehicles')->assertOk()->assertJsonMissing(['id' => $vehicle->id]);

        $this->actingAs($admin)->patchJson("/api/admin/vehicles/{$vehicle->id}", [
            'status' => 'approved',
        ])->assertOk();

        $this->getJson('/api/vehicles')->assertOk()->assertJsonFragment(['id' => $vehicle->id]);
    }

    public function test_catalog_cover_photo_is_the_lowest_sort_order_photo(): void
    {
        $vehicle = Vehicle::factory()->approved()->create();
        $vehicle->photos()->create(['path' => 'vehicles/1/second.jpg', 'sort_order' => 1]);
        $vehicle->photos()->create(['path' => 'vehicles/1/first.jpg', 'sort_order' => 0]);

        $response = $this->getJson('/api/vehicles')->assertOk();

        $this->assertStringContainsString('first.jpg', $response->json('data.0.cover_photo_url'));
    }

    public function test_catalog_repeats_the_same_result_for_an_identical_cached_request(): void
    {
        Vehicle::factory()->approved()->create(['brand' => 'Toyota']);

        $first = $this->getJson('/api/vehicles?brand=Toyota')->assertOk();
        $second = $this->getJson('/api/vehicles?brand=Toyota')->assertOk();

        $this->assertSame($first->json('data.0.id'), $second->json('data.0.id'));
    }

    /**
     * The test environment defaults to the `array` cache store, which
     * never actually serializes anything — it would not have caught the
     * production bug where caching the raw Eloquent paginator crashed on
     * unserialize() with the real `database` store (see CatalogCache).
     * This forces the real driver so a regression here fails loudly.
     */
    public function test_catalog_survives_a_real_cache_write_and_read_cycle_with_the_database_cache_store(): void
    {
        config(['cache.default' => 'database']);
        Vehicle::factory()->approved()->create();

        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/vehicles')->assertOk()->assertJsonCount(1, 'data');
    }
}
