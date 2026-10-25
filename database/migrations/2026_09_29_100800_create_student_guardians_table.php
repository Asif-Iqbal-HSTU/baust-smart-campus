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
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            $table->string('name', 150);
            $table->string('mobile', 20)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('designation', 100)->nullable();
            $table->boolean('is_govt_employee')->nullable();
            $table->decimal('yearly_income', 12, 2)->nullable();
            $table->boolean('is_military')->nullable();
            $table->boolean('is_serving')->nullable();
            $table->string('military_rank', 50)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'relation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
