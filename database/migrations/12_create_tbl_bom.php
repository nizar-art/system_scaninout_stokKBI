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
        Schema::create('tbl_bom', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('component_id')->index()->nullable();
            $table->double('quantity')->default(0);
            $table->string('unit');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('tbl_part')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('tbl_part')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_bom');
    }
};
