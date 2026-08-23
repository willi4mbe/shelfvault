<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_service_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('service');
            $table->string('key');
            $table->text('value')->nullable();
            $table->text('encrypted_value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['service', 'key']);
            $table->index('service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_service_settings');
    }
};
