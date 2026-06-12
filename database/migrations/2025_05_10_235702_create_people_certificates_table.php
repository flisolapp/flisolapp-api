<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('people_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('people_id');
            $table->unsignedBigInteger('edition_id');
            $table->unsignedBigInteger('organizer_id')->nullable();
            $table->unsignedBigInteger('collaborator_id')->nullable();
            $table->unsignedBigInteger('talk_id')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('name');
            $table->string('federal_code')->nullable();
            $table->string('code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_view_at')->nullable();
            $table->timestamps();
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_certificates');
    }
};
