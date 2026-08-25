<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('borrower_name');
            $table->string('borrower_contact')->nullable();
            $table->date('loaned_at');
            $table->date('expected_return_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_loans');
    }
};
