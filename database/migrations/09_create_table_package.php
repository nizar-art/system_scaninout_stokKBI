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
        Schema::create('tbl_package', function (Blueprint $table) {
            $table->id();
            $table->string('type_pkg')->nullable();
            $table->integer('qty')->default(0);
            $table->unsignedBigInteger('id_part')->nullable();
            // index
            $table->index('id_part');
            // foreign key constraints
            $table->foreign('id_part')->references('id')->on('tbl_part')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_package');
    }
};
