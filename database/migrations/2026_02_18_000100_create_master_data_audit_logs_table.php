<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_data_audit_logs')) {
            return;
        }

        Schema::create('master_data_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_role', 32);
            $table->string('entity_type', 64);
            $table->string('entity_id', 64)->nullable();
            $table->string('action', 64);
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->longText('meta_json')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('actor_role');
            $table->index('entity_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_audit_logs');
    }
};

