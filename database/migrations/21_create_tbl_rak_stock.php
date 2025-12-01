<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_rak_stock', function (Blueprint $table) {
            $table->id();

            // Foreign key ke tabel part
            $table->unsignedBigInteger('id_inventory');

            // Foreign key ke tabel head rak
            $table->unsignedBigInteger('id_rak_head');

            $table->integer('stok')->default(0);
            $table->timestamps();

            // Relasi ke tbl_part
            $table->foreign('id_inventory')
                ->references('id')
                ->on('tbl_part')
                ->onDelete('cascade');

            // Relasi ke tbl_head_rak
            $table->foreign('id_rak_head')
                ->references('id')
                ->on('tbl_head_rak')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rak_stock');
    }
};
