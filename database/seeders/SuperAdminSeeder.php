<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear permisos básicos
        $permissions = [
            // Usuarios
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            
            // Roles y permisos
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'assign-roles',
            
            // Dashboard
            'view-dashboard',
            
            // Configuración
            'view-settings',
            'edit-settings',
            
            // Productos
            'view-products',
            'create-products',
            'edit-products',
            'delete-products',
            
            // Categorías
            'view-categories',
            'create-categories',
            'edit-categories',
            'delete-categories',
            
            // Facebook Ads
            'view-facebook-ads',
            'create-facebook-ads',
            'export-facebook-ads',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Crear roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Asignar todos los permisos al super admin
        $superAdminRole->syncPermissions(Permission::all());

        // Asignar permisos específicos al admin
        $adminRole->syncPermissions([
            'view-users',
            'create-users',
            'edit-users',
            'view-dashboard',
            'view-settings',
            'view-products',
            'create-products',
            'edit-products',
            'view-categories',
            'create-categories',
            'edit-categories',
        ]);

        // Asignar permisos básicos al usuario
        $userRole->syncPermissions([
            'view-dashboard',
            'view-products',
            'view-categories',
        ]);

        // Crear usuario super admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@infoproductos.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // Asignar rol de super admin
        $superAdmin->assignRole('super-admin');

        $this->command->info('Super Admin creado exitosamente:');
        $this->command->info('Email: admin@infoproductos.com');
        $this->command->info('Password: admin123');
        $this->command->info('Roles y permisos configurados correctamente.');
    }
}
