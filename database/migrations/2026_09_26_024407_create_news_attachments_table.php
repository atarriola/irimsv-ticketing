<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The photos and videos that go with a news post. The files live on a
     * private disk and are only served to people allowed to read the post.
     */
    public function up(): void
    {
        Schema::create('news_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index('news_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_attachments');
    }
};
