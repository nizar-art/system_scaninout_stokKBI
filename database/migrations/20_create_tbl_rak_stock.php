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
            $table->unsignedBigInteger('id_inventory');
            $table->string('rak_name'); // contoh: "Rak 1", "Rak 2"
            $table->integer('stok')->default(0);
            $table->timestamps();

            $table->foreign('id_inventory')
                  ->references('id')
                  ->on('tbl_part')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_rak_stock');
    }
};
