<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePosUser extends Command
{
    protected $signature = 'pos:create-user
        {--tenant= : Tenant ID (created if --tenant-name is given)}
        {--tenant-name= : Name for a new tenant}
        {--name= : User full name}
        {--email= : User email}
        {--role=owner : owner|manager|cashier}';

    protected $description = 'Create a POS user interactively (password is prompted, never stored in code).';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        if (! $tenantId && $this->option('tenant-name')) {
            $tenantId = Tenant::create(['name' => $this->option('tenant-name')])->id;
            $this->info("Tenant created: #{$tenantId}");
        }

        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email');
        $role = $this->option('role');
        if (! in_array($role, ['owner', 'manager', 'cashier'], true)) {
            $this->error('Role must be owner, manager, or cashier.');
            return self::FAILURE;
        }

        $password = $this->secret('Password (will not be shown)');
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'tenant_id' => $tenantId ?: null,
                'role' => $role,
            ]
        );

        $this->info("User #{$user->id} ({$user->email}, role {$role}) ready.");
        if (! $tenantId) {
            $this->warn('No tenant assigned. Assign tenant_id/role before using the dashboard or API.');
        }

        return self::SUCCESS;
    }
}
