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
        Schema::create('tbl_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_part');
            $table->date('date')->nullable();
            $table->integer('plan_stock'); // allow negative values for plan_stock
            $table->integer('act_stock')->default(0);
            $table->enum('remark', ['normal', 'abnormal'])->default('normal');
            $table->string('note_remark')->nullable();


            // index
            $table->index('id_part');
            $table->index('plan_stock');
            $table->index('act_stock');
            $table->index('created_at');

            // fk
            $table->foreign('id_part')->references('id')->on('tbl_part')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_inventory');
    }
};