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
        Schema::create('tbl_user', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('nik')->nullable();
            // $table->string('email')->nullable()->unique();
            // $table->unsignedBigInteger('id_role')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('password');

            // index
            $table->index('username');
            $table->index('nik');
            // $table->index(['id_role'], 'idx_role');


            // $table->foreign('id_role')->references('id')->on('tbl_role')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_user');
    }
};