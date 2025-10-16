<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_plan_stock_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_inventory');
            $table->integer('plan_stock_before')->nullable();
            $table->integer('plan_stock_after')->default(0);
            $table->timestamps();

            // index
            $table->index('id_inventory');


            // fk
            $table->foreign('id_inventory')->references('id')->on('tbl_inventory')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_plan_stock_log');
    }
};
