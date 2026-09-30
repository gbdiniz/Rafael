<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('uploaded');
            $table->string('locale', 16)->default('pt-BR');
            $table->string('disk', 32)->nullable();
            $table->string('audio_path', 255)->nullable();
            $table->timestamp('audio_deleted_at')->nullable();
            $table->text('transcript')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_turns');
    }
};
