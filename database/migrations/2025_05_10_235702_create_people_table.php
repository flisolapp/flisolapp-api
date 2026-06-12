<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('federal_code')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->text('photo')->nullable();
            $table->text('bio')->nullable();
            $table->string('site')->nullable();
            $table->boolean('use_free');
            $table->unsignedBigInteger('distro_id')->nullable();
            $table->unsignedBigInteger('student_info_id')->nullable();
            $table->string('student_place')->nullable();
            $table->string('student_course')->nullable();
            $table->string('address_state')->nullable();  // original type: char(2)
            $table->timestamps();
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
