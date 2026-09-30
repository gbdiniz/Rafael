<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE TABLE records (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    user_id INTEGER NOT NULL,
    kind VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    scheduled_at DATETIME,
    last_transcript TEXT NOT NULL,
    last_voice_turn_id INTEGER,
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY(last_voice_turn_id) REFERENCES voice_turns(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CHECK ((kind = 'task') OR (kind = 'appointment' AND scheduled_at IS NOT NULL))
)
SQL);

            DB::statement('CREATE INDEX records_user_id_kind_deleted_at_scheduled_at_index ON records (user_id, kind, deleted_at, scheduled_at)');
            DB::statement('CREATE INDEX records_user_id_deleted_at_index ON records (user_id, deleted_at)');

            return;
        }

        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('kind', 32);
            $table->string('title', 255);
            $table->timestamp('scheduled_at')->nullable();
            $table->text('last_transcript');
            $table->foreignId('last_voice_turn_id')->nullable()->constrained('voice_turns')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'kind', 'deleted_at', 'scheduled_at']);
            $table->index(['user_id', 'deleted_at']);
        });

        DB::statement("ALTER TABLE records ADD CONSTRAINT records_kind_scheduled_check CHECK ((kind = 'task') OR (kind = 'appointment' AND scheduled_at IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('records');
    }
};
