<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Helpdesk roles now follow the LRMIS user type, so the table that held
     * manually granted roles is no longer read.
     */
    public function up(): void
    {
        Schema::dropIfExists('user_roles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
        });
    }
};
