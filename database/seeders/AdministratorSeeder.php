<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Creates the helpdesk administrator account configured in config/helpdesk.php.
 * Helpdesk administrators are LRMIS accounts of the Administrator user type, so
 * the account is written to the LRMIS users table on that type. An account
 * that already has the configured username is left untouched, so re-seeding
 * never resets a password or promotes somebody else's LRMIS account.
 */
class AdministratorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array{username: string, password: string, email: string, firstname: string, lastname: string} $account */
        $account = config('helpdesk.administrator');

        if (User::query()->where('username', $account['username'])->exists()) {
            $this->command?->warn('An LRMIS account already has the username "'.$account['username'].'", so no administrator was seeded. Promote an existing account with helpdesk:grant-admin instead.');

            return;
        }

        $user = (new User)->forceFill([
            'firstname' => $account['firstname'],
            'middlename' => null,
            'lastname' => $account['lastname'],
            'extension_name' => null,
            'gender' => 'other',
            'birthday' => null,
            'username' => $account['username'],
            'password' => $account['password'],
            'email' => $account['email'],
            'contact_number' => '',
            'photo' => null,
            'usertype_id' => $this->administratorUsertype()->id,
            'station_id' => $this->stationId(),
            'status' => UserStatus::Active,
            'approved_by' => null,
        ]);

        $user->save();

        $this->command?->info('Seeded the helpdesk administrator account "'.$account['username'].'".');
    }

    /**
     * Get the LRMIS Administrator user type, creating it only on a database
     * that has no LRMIS user types yet, such as the test database.
     */
    private function administratorUsertype(): Usertype
    {
        $usertype = Usertype::query()->administrator()->first();

        if ($usertype !== null) {
            return $usertype;
        }

        $usertype = (new Usertype)->forceFill([
            'type_name' => 'Administrator',
            'level' => Usertype::ADMINISTRATOR_LEVEL,
        ]);

        $usertype->save();

        return $usertype;
    }

    /**
     * Get the station for the account. LRMIS stations its region-level staff
     * at the region, so the administrator is stationed there too. A database
     * without the LRMIS regions table gets a placeholder station instead.
     */
    private function stationId(): string
    {
        $regionId = Schema::hasTable('regions')
            ? DB::table('regions')->orderBy('created_at')->value('id')
            : null;

        return $regionId === null ? (string) Str::uuid() : (string) $regionId;
    }
}
