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
        Schema::create('tbl_stock_scan_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_inventory');
            $table->unsignedBigInteger('id_daily_stock_log')->nullable(); // ✅ relasi ke stok harian
            $table->unsignedBigInteger('user_id');
            $table->string('qrcode_raw')->nullable(); // bisa kosong kalau OUT manual
            $table->integer('stok_inout'); // jumlah stok masuk/keluar
            $table->enum('status', ['IN', 'OUT']); // IN = tambah, OUT = kurangi
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            // 🔹 Foreign key relasi
            $table->foreign('id_inventory')
                ->references('id')
                ->on('tbl_part')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('tbl_user')
                ->onDelete('cascade');

            $table->foreign('id_daily_stock_log')
                ->references('id')
                ->on('tbl_daily_stock_logs')
                ->onDelete('set null'); // kalau daily log dihapus, history tetap aman
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_stock_scan_histories');
    }
};
