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
        Schema::create('tbl_area', function (Blueprint $table) {
            $table->id();
            $table->string('nama_area');
            $table->unsignedBigInteger('id_plan')->nullable();
            $table->timestamps();

            // index
            $table->index(['id_plan'], 'idx_plan');
            // Foreign key constraints
            $table->foreign('id_plan')->references('id')->on('tbl_plan')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_area');
    }
};
