<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('talks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('edition_id');
            $table->string('title');
            $table->text('description');
            $table->string('shift');  // original type: char(1)
            $table->string('kind');  // original type: char(1)
            $table->unsignedBigInteger('talk_subject_id');
            $table->string('slide_file')->nullable();
            $table->string('slide_url')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('audited_at')->nullable();
            $table->text('audit_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('presented_at')->nullable();
            $table->timestamps();
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talks');
    }
};
