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
        Schema::create('tbl_part', function (Blueprint $table) {
            $table->id();
            $table->string('Inv_id')->unique( 'Inv_id_unique');
            $table->string('Part_name')->nullable();
            $table->string('Part_number')->nullable();
            $table->string('supplier')->nullable();
            $table->string('subcont')->default('KBI');

            $table->unsignedBigInteger('id_category');
            $table->unsignedBigInteger('id_customer')->nullable();
            $table->unsignedBigInteger('id_plan')->nullable();
            $table->unsignedBigInteger('id_area')->nullable();


            // index
            $table->index('Inv_id');
            $table->index('id_customer');
            $table->index('id_plan');
            $table->index('id_area');
            $table->index('id_category');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('supplier');
            $table->index('subcont');
            // Foreign key constraints
            $table->foreign('id_category')->references('id')->on('tbl_category')->onDelete('cascade');

            $table->foreign('id_customer')->references('id')->on('tbl_customer')->onDelete('cascade');
            $table->foreign('id_plan')->references('id')->on('tbl_plan')->onDelete('cascade');
            $table->foreign('id_area')->references('id')->on('tbl_area')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_part');
    }
};
