<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collaborators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('edition_id');
            $table->unsignedBigInteger('people_id');
            $table->timestamp('audited_at')->nullable();
            $table->string('audit_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('presented_at')->nullable();
            $table->timestamps();
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborators');
    }
};
