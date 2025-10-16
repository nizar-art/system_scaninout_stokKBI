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
        Schema::create('tbl_daily_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_inventory');
            $table->date('date')->nullable();
            $table->unsignedBigInteger('prepared_by');
            $table->unsignedBigInteger('id_box_complete')->nullable();
            $table->unsignedBigInteger('id_box_uncomplete')->nullable();
            $table->unsignedBigInteger('id_area_head')->nullable();
            $table->integer('Total_qty')->default(0);
             $table->double('stock_per_day')->default(0);

            $table->enum('status', ['OK', 'NG','VIRGIN','FUNSAI']);

            // index
            $table->index('id_inventory');
            $table->index('prepared_by');
            $table->index('id_box_complete');
            $table->index('id_box_uncomplete');
            $table->index('Total_qty');
            $table->index('stock_per_day');
            $table->index('created_at');
            $table->index('status');
            $table->index('updated_at');

            // fk
            $table->foreign('id_area_head')->references('id')->on('tbl_head_area')->onDelete('cascade');
            $table->foreign('id_box_complete')->references('id')->on('tbl_box_complete')->onDelete('cascade');
            $table->foreign('id_box_uncomplete')->references('id')->on('tbl_box_uncomplete')->onDelete('cascade');
            $table->foreign('id_inventory')->references('id')->on('tbl_part')->onDelete('cascade');
            $table->foreign('prepared_by')->references('id')->on('tbl_user')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_daily_stock_logs');
    }
};

