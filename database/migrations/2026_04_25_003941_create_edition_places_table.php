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
        Schema::create('edition_places', function (Blueprint $table) {
            $table->id();

            // Public UUIDv7 identifier used for QRCode and external sharing.
            $table->char('_id', 36)->unique();

            // Edition relationship.
            $table->foreignId('edition_id')
                ->constrained('editions')
                ->restrictOnDelete();

            // Place classification: room, lab, main_space, food_area, support, etc.
            $table->string('kind', 30)->default('room');

            // Place information.
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('floor', 50)->nullable();
            $table->unsignedInteger('capacity')->nullable();

            // Status.
            $table->boolean('active')->default(true);

            // Laravel timestamps.
            $table->timestamps();

            // Soft delete using project convention.
            $table->timestamp('removed_at')->nullable();

            // Indexes.
            $table->index('edition_id');
            $table->index('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_places');
    }
};
