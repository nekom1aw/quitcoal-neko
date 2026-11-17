<?php

namespace App\Http\Controllers;

use App\Livewire\Cms\PageFormGrafik;
use Illuminate\Support\Facades\DB;

class GrafikController extends Controller
{
    // Halaman publik satu grafik dari menu Data & Grafik, untuk dibuka langsung atau di-embed
    // lewat iframe. Grafik yang belum diterbitkan tidak bisa dilihat.
    public function tampil(int $id)
    {
        $grafik = DB::table('grafik')->where('id', $id)->where('terbit', true)->first();
        abort_unless($grafik, 404);

        $pengaturan = PageFormGrafik::bersihkanPengaturan(json_decode($grafik->pengaturan, true));

        return view('grafik.tampil', [
            'grafik' => $grafik,
            'p' => $pengaturan,
            'spek' => [
                'jenis' => $grafik->jenis,
                'data' => json_decode($grafik->data, true),
                'pengaturan' => $pengaturan,
            ],
        ]);
    }
}
