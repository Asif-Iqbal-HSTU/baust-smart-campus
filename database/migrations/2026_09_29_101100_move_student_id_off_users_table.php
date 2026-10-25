<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student ID now lives on students.student_id (students sign in through
 * that relation). Imported student accounts have no password until one is
 * issued, so password becomes nullable.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->dropColumn('student_id');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id', 30)->nullable()->unique()->after('mobile');
            $table->string('password')->nullable(false)->change();
        });
    }
};
