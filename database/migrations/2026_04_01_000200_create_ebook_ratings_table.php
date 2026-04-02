<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebook_ratings')) {
            return;
        }

        Schema::create('ebook_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('star');
            $table->string('review_text', 280)->nullable();
            $table->string('source_page', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('star');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_ratings');
    }
};

