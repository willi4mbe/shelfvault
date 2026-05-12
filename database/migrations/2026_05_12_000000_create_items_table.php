<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->string('sort_title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->string('barcode', 128)->nullable()->index();
            $table->string('cover_path', 2048)->nullable();
            $table->string('physical_format', 64)->nullable();
            $table->string('edition', 120)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('condition', 32)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('status', 32)->index();
            $table->boolean('is_favorite')->default(false);
            $table->longText('personal_notes')->nullable();
            $table->date('acquired_at')->nullable();

            $table->unsignedSmallInteger('runtime_minutes')->nullable();
            $table->string('director')->nullable();
            $table->json('cast_members')->nullable();
            $table->json('genres')->nullable();
            $table->string('studio')->nullable();
            $table->string('age_rating')->nullable();
            $table->string('external_tmdb_id', 64)->nullable();

            $table->string('platform')->nullable();
            $table->string('developer')->nullable();
            $table->string('publisher')->nullable();
            $table->json('modes')->nullable();
            $table->string('external_igdb_id', 64)->nullable();

            $table->unsignedSmallInteger('min_players')->nullable();
            $table->unsignedSmallInteger('max_players')->nullable();
            $table->unsignedSmallInteger('play_time_minutes')->nullable();
            $table->string('designer')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
