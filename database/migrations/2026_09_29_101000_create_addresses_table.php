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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->string('type', 20);
            $table->string('address_line')->nullable();
            $table->string('post_office', 100)->nullable();
            $table->string('post_code', 10)->nullable();
            $table->string('upazila', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('division', 100)->nullable();
            $table->string('country', 60)->default('Bangladesh');
            $table->text('raw_text')->nullable();
            $table->timestamps();

            $table->index(['addressable_type', 'addressable_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
