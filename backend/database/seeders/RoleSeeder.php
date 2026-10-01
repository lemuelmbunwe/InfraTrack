<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $roles = [
            [
                'name' => 'admin',
                'description' => 'manages users, assigns issues and manages processes'
            ],
            [
                'name'=>'inspector',
                'description' => 'goes into field and reports issues'
            ],
            [
                'name' => 'contractor',
                'description' => 'responsible for repairing issues'
            ]
        ];

        foreach ($roles as $role){
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
