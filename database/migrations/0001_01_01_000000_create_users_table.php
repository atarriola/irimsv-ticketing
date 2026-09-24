<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The ticketing system lives inside the LRMIS schema and authenticates
     * against the LRMIS `users` table, so the LRMIS-owned `usertypes` and
     * `users` tables are only created when they are absent (for example on
     * the test database) and mirror the LRMIS column definitions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('usertypes')) {
            Schema::create('usertypes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type_name');
                $table->integer('level');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('firstname');
                $table->string('middlename')->nullable();
                $table->string('lastname');
                $table->string('extension_name')->nullable();
                $table->string('gender');
                $table->date('birthday')->nullable();
                $table->string('username');
                $table->string('password');
                $table->string('email');
                $table->string('contact_number');
                $table->string('photo')->nullable();
                $table->foreignUuid('usertype_id')->constrained('usertypes');
                $table->uuid('station_id');
                $table->string('status');
                $table->uuid('approved_by')->nullable();
                $table->timestamps();
                $table->text('remember_token')->nullable();
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignUuid('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Nothing is dropped: the schema is shared with LRMIS, and dropping the
     * `users` table would destroy every LRMIS account.
     */
    public function down(): void
    {
        //
    }
};
