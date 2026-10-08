<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The columns that let a ticket move with its conversation (last activity,
     * who it is waiting on), be kept private or shared, be rated, be linked to
     * a release post or the forum thread it was raised from, and be deleted
     * without losing its history.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('closed_at');
            $table->string('waiting_on')->nullable()->after('status');
            $table->boolean('is_shared')->default(false)->after('description');
            $table->unsignedTinyInteger('rating')->nullable()->after('closed_at');
            $table->text('rating_comment')->nullable()->after('rating');
            $table->foreignId('news_post_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('forum_thread_id')->nullable()->after('news_post_id')->constrained()->nullOnDelete();
            $table->softDeletes();

            $table->index(['status', 'last_activity_at']);
            $table->index('waiting_on');
        });

        // Tickets raised before this column existed were last active when they were created,
        // and the active ones have been waiting on the helpdesk all along.
        DB::table('tickets')->update(['last_activity_at' => DB::raw('created_at')]);
        DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->update(['waiting_on' => 'support']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_activity_at']);
            $table->dropIndex(['waiting_on']);
            $table->dropConstrainedForeignId('news_post_id');
            $table->dropConstrainedForeignId('forum_thread_id');
            $table->dropSoftDeletes();
            $table->dropColumn(['last_activity_at', 'waiting_on', 'is_shared', 'rating', 'rating_comment']);
        });
    }
};
