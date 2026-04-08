<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ebook_ratings')) {
            return;
        }

        Schema::table('ebook_ratings', function (Blueprint $table): void {
            if (!Schema::hasColumn('ebook_ratings', 'reviewer_name')) {
                $table->string('reviewer_name', 80)->nullable()->after('star');
            }

            if (!Schema::hasColumn('ebook_ratings', 'improvement_suggestion')) {
                $table->string('improvement_suggestion', 280)->nullable()->after('review_text');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ebook_ratings')) {
            return;
        }

        Schema::table('ebook_ratings', function (Blueprint $table): void {
            if (Schema::hasColumn('ebook_ratings', 'improvement_suggestion')) {
                $table->dropColumn('improvement_suggestion');
            }

            if (Schema::hasColumn('ebook_ratings', 'reviewer_name')) {
                $table->dropColumn('reviewer_name');
            }
        });
    }
};

