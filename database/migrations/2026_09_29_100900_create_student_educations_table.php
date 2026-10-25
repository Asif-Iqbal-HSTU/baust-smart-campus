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
        Schema::create('student_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('exam', 20);
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->foreignId('education_board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institute', 200)->nullable();
            $table->string('group', 30)->nullable();
            $table->string('roll', 20)->nullable();
            $table->string('registration_no', 30)->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->decimal('gpa_scale', 3, 1)->default(5.0);
            $table->timestamps();

            $table->unique(['student_id', 'exam']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_educations');
    }
};
