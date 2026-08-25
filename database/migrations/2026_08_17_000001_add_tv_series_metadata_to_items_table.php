<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('season_count')->nullable()->after('external_tmdb_id');
            $table->unsignedSmallInteger('episode_count')->nullable()->after('season_count');
            $table->string('showrunner')->nullable()->after('episode_count');
            $table->string('network')->nullable()->after('showrunner');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn([
                'season_count',
                'episode_count',
                'showrunner',
                'network',
            ]);
        });
    }
};
