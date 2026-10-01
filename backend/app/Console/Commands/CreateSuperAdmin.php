<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('infratrack:create-super-admin')]
#[Description('Create a new admin user with global Super Admin privileges')]
class CreateSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('====================================');
        $this->info('  Creating a New Super Admin User   ');
        $this->info('====================================');

        // 1. Gather Input From Console
        $name = $this->ask('Enter Name');
        $email = $this->ask('Enter Email Address');
        $password = $this->secret('Enter Password');
        $passwordConfirmation = $this->secret('Confirm Password');

        // 2. Validate Inputs against database constraints
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->error('Registration Failed. See Errors Below:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("- $error");
            }
            return Command::FAILURE;
        }

        // 3. Find the core 'admin' role in your system
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $this->error('Error: The "admin" role does not exist in your roles table.');
            $this->comment('Please check your RoleSeeder to ensure "admin" is populated first.');
            return Command::FAILURE;
        }

        // 4. Create and store the Super Admin in the DB
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id, // Assigns the mandatory 'admin' role relation
            'super' => true,             // Explicitly flags them as a Super Admin
            'is_active' => true,         // Explicitly ensures the account starts active
        ]);

        $this->info('====================================');
        $this->info("Success! Super Admin [{$user->email}] created successfully.");
        $this->info('====================================');

        return Command::SUCCESS;
    }
}
