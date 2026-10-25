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
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 20)->nullable()->unique();
            $table->string('source', 10); // office | online
            $table->string('status', 20)->index();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('quota_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name_en', 150);
            $table->string('name_bn', 150)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('blood_group', 3)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('marital_status', 20)->nullable();
            $table->string('nid', 20)->nullable();
            $table->string('birth_registration_no', 30)->nullable();
            $table->string('mobile', 20)->index();
            $table->string('email', 150)->nullable();
            $table->string('photo_path')->nullable();

            $table->string('father_name', 150);
            $table->string('father_mobile', 20)->nullable();
            $table->string('father_occupation', 100)->nullable();
            $table->string('mother_name', 150);
            $table->string('mother_mobile', 20)->nullable();
            $table->string('mother_occupation', 100)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_relation', 50)->nullable();
            $table->string('guardian_mobile', 20)->nullable();

            // SSC + HSC (or equivalent) GPA; below the threshold means an admission test.
            $table->decimal('gpa_total', 4, 2)->nullable();

            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 60)->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('test_score', 5, 2)->nullable();
            $table->boolean('test_passed')->nullable();
            $table->timestamp('test_recorded_at')->nullable();
            $table->foreignId('test_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admitted_at')->nullable();
            $table->foreignId('admitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->index(['batch_id', 'status']);
        });

        Schema::create('admission_application_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications', indexName: 'admission_educations_application_fk')->cascadeOnDelete();
            $table->string('level', 20); // secondary | higher_secondary
            $table->string('exam', 20);
            $table->unsignedSmallInteger('passing_year');
            $table->foreignId('education_board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institute', 200)->nullable();
            $table->string('group', 30)->nullable();
            $table->string('roll', 20);
            $table->string('registration_no', 30)->nullable();
            $table->decimal('gpa', 4, 2);
            $table->decimal('gpa_scale', 3, 1)->default(5.0);
            $table->timestamps();
            $table->unique(['admission_application_id', 'level'], 'admission_educations_application_level_unique');
            $table->index(['roll', 'passing_year'], 'admission_educations_roll_year_index');
        });

        Schema::create('admission_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications', indexName: 'admission_documents_application_fk')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_application_documents');
        Schema::dropIfExists('admission_application_educations');
        Schema::dropIfExists('admission_applications');
    }
};
