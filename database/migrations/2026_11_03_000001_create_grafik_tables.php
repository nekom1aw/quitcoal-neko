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
        // Grafik yang dibuat admin di menu Data & Grafik (editor ala Flourish)
        Schema::create('grafik', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('subjudul', 500)->nullable();
            // Template: bar, bar-horizontal, bar-stacked, line, area, pie, donut
            $table->string('jenis', 30)->default('bar');
            // Tabel data: {"kolom": ["Tahun", "Produksi", ...], "baris": [["2019", "616", ...], ...]}
            $table->json('data');
            // Warna, legenda, format angka, sumbu, sumber, tinggi, ... (lihat PageFormGrafik::PENGATURAN)
            $table->json('pengaturan');
            // Hanya grafik yang diterbitkan yang bisa dibuka/di-embed publik lewat /grafik/{id}
            $table->boolean('terbit')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // grafik_data: tabel versi awal fitur ini
        Schema::dropIfExists('grafik_data');
        Schema::dropIfExists('grafik');
    }
};
