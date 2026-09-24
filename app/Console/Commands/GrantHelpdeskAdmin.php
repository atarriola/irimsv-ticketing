<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helpdesk:grant-admin {username : The LRMIS username} {--revoke : Make the account a member again}')]
#[Description('Make an LRMIS account a helpdesk administrator, or a member again')]
class GrantHelpdeskAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $username = $this->argument('username');
        $user = User::query()->where('username', $username)->first();

        if ($user === null) {
            $this->error("No LRMIS account has the username \"{$username}\".");

            return self::FAILURE;
        }

        $role = $this->option('revoke') ? UserRole::User : UserRole::Admin;

        $user->assignRole($role);

        $this->info($role === UserRole::Admin
            ? "{$user->name} is now a helpdesk administrator."
            : "{$user->name} is now a member.");

        return self::SUCCESS;
    }
}
