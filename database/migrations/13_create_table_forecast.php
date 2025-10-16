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
        Schema::create('tbl_forecast', function (Blueprint $table) {
            $table->id();
            $table->date('issued_at')->nullable();
            $table->unsignedBigInteger('id_part');
            $table->unsignedBigInteger('id_work')->nullable();
            $table->integer('hari_kerja')->nullable();
            $table->date('forecast_month' );
            $table->integer('frequensi_delivery')->nullable(); 
            $table->integer('PO_pcs' );
            $table->integer('min')->default(0);
            $table->integer('max')->default(0);

            // index
            $table->index('id_part');
            $table->index('min');
            $table->index('max');
            $table->index('id_work');

            // fk
            $table->foreign('id_work')->references('id')->on('tbl_working_days')->onDelete('set null');
            $table->foreign('id_part')->references('id')->on('tbl_part')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_forecast');
    }
};