<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FacebookAdsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos para Facebook Ads Scraper
        $permissions = [
            'view-facebook-ads' => 'Ver búsquedas de Facebook Ads',
            'create-facebook-ads' => 'Crear búsquedas de Facebook Ads',
            'export-facebook-ads' => 'Exportar resultados de Facebook Ads',
            'use-apify' => 'Usar Apify para datos reales',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        // Asignar permisos al rol super-admin
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(array_keys($permissions));
        }

        // Crear rol específico para Facebook Ads
        $facebookAdsRole = Role::firstOrCreate(
            ['name' => 'facebook-ads-user'],
            ['guard_name' => 'web']
        );

        // Asignar permisos básicos al rol facebook-ads-user
        $facebookAdsRole->givePermissionTo([
            'view-facebook-ads',
            'create-facebook-ads',
            'export-facebook-ads',
        ]);

        $this->command->info('✅ Permisos de Facebook Ads creados exitosamente');
    }
}
