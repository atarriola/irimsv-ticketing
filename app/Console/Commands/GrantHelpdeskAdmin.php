<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Usertype;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helpdesk:grant-admin {username : The LRMIS username} {--revoke= : Return the account to this LRMIS user type instead, such as "Teacher"}')]
#[Description('Move an LRMIS account onto the Administrator user type, whose users administer the helpdesk')]
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

        $usertype = $this->targetUsertype();

        if ($usertype === null) {
            return self::FAILURE;
        }

        $user->usertype()->associate($usertype)->save();

        $this->info($usertype->isAdministrator()
            ? "{$user->name} is now a helpdesk administrator."
            : "{$user->name} is now a {$usertype->type_name} and no longer administers the helpdesk.");

        return self::SUCCESS;
    }

    /**
     * Find the LRMIS user type the account should end up with, reporting why when there is none.
     */
    private function targetUsertype(): ?Usertype
    {
        $typeName = $this->option('revoke');

        if (blank($typeName)) {
            $administrator = Usertype::query()->administrator()->first();

            if ($administrator === null) {
                $this->error('LRMIS has no Administrator user type.');
            }

            return $administrator;
        }

        $usertype = Usertype::query()->where('type_name', $typeName)->first();

        if ($usertype === null) {
            $names = Usertype::query()->orderBy('level')->orderBy('type_name')->pluck('type_name')->implode(', ');

            $this->error("LRMIS has no user type named \"{$typeName}\". The types are: {$names}.");
        }

        return $usertype;
    }
}
