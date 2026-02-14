<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Seed sample tenants for local development.
     */
    public function run(): void
    {
        Tenant::factory()->withSlug('latifa', 'Latifa Kitchen')->create();
        Tenant::factory()->withSlug('powel', 'Chef Powel')->create();
        Tenant::factory()->withSlug('mama-ngono', 'Mama Ngono Dishes')->create();

        // One inactive tenant for testing
        Tenant::factory()->withSlug('closed-cook', 'Closed Cook')->inactive()->create();

        // One with custom domain
        Tenant::factory()
            ->withSlug('mariette', 'Chez Mariette')
            ->withCustomDomain('mariette.cm')
            ->create();
    }
}
