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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('student_id', 20)->unique();
            $table->string('registration_no', 30)->nullable()->index();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('quota_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained()->nullOnDelete();
            $table->string('enrollment_type', 20)->default('general');
            $table->string('name_en', 150);
            $table->string('name_bn', 150)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 3)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('marital_status', 20)->nullable();
            $table->string('nid', 20)->nullable();
            $table->string('mobile', 20)->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['program_id', 'batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
