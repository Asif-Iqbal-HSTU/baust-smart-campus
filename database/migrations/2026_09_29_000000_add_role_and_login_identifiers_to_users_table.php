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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('name')->index();
            $table->string('mobile', 20)->nullable()->unique()->after('role');
            $table->string('student_id', 30)->nullable()->unique()->after('mobile');
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropUnique(['student_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'mobile', 'student_id']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
