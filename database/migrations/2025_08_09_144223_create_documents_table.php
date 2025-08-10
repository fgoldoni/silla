<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('uid')->unique();
            $table->string('champ1')->nullable();
            $table->string('champ2')->nullable(); // select
            $table->string('champ3')->nullable(); // select
            $table->string('champ4')->nullable();

            $table->longText('commentaire')->nullable();

            $table->string('file_name');
            $table->string('file_path'); // chemin relatif dans le disque
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 191);
            $table->string('hash', 64)->index(); // SHA-256

            $table->unsignedInteger('version')->default(1);

            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ulid('team_id')->nullable()->index(); // au besoin

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['file_name']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE documents ALTER COLUMN tags TYPE jsonb USING tags::jsonb");
            DB::statement("ALTER TABLE documents ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
