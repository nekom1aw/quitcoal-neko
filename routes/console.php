<?php

use App\Http\Controllers\PltuServiceController;
use App\Support\BatasTile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mengisi cache tile garis batas wilayah lebih dulu, supaya kunjungan pertama ke peta juga cepat:
// tahap pulau sampai kabupaten untuk seluruh Indonesia, dan tahap desa di sekitar tiap PLTU
// (zoom 13, saat marker diklik). Tile yang sudah tersimpan dilewati, jadi aman dijalankan ulang.
Artisan::command('peta:siapkan-batas
    {--sampai-zoom=9 : Zoom tile tertinggi untuk tahap kabupaten di seluruh Indonesia}
    {--tanpa-retina : Lewati tile @2x untuk layar retina}
    {--hitung : Hanya hitung jumlah tile tanpa mengambilnya}', function () {
    $sampai = (int) $this->option('sampai-zoom');

    // [lapisan, zoom tile dari, sampai] untuk seluruh Indonesia
    $rencana = [
        ['2-utama', 4, 6],
        ['3-utama', 5, min(8, $sampai)],
        ['4-utama', 7, $sampai],
        ['3-induk', 7, $sampai],
    ];

    $tugas = [];
    foreach ($rencana as [$lapisan, $dari, $ke]) {
        for ($z = $dari; $z <= $ke; $z++) {
            [$x0, $y0] = BatasTile::nomor(6.5, 95, $z);
            [$x1, $y1] = BatasTile::nomor(-11, 141, $z);
            for ($x = $x0; $x <= $x1; $x++) {
                for ($y = $y0; $y <= $y1; $y++) {
                    $tugas[] = [$lapisan, $z, $x, $y];
                }
            }
        }
    }

    $pltu = DB::table('profil_pltu')->whereNotNull('latitude')->whereNotNull('longitude')->get(['latitude', 'longitude']);
    foreach ($pltu as $p) {
        [$cx, $cy] = BatasTile::nomor((float) $p->latitude, (float) $p->longitude, 13);
        foreach (['6-utama', '5-induk'] as $lapisan) {
            for ($x = $cx - 1; $x <= $cx + 1; $x++) {
                for ($y = $cy - 1; $y <= $cy + 1; $y++) {
                    $tugas[] = [$lapisan, 13, $x, $y];
                }
            }
        }
    }

    // Tiap tile dalam versi biasa dan retina, tanpa duplikat dan yang sudah tersimpan
    $antrian = collect($tugas)
        ->crossJoin($this->option('tanpa-retina') ? [false] : [false, true])
        ->map(fn ($t) => [...$t[0], $t[1]])
        ->unique(fn ($t) => implode('/', $t))
        ->filter(fn ($t) => BatasTile::parameter(...$t) !== null && !BatasTile::ada(...$t))
        ->values();

    $this->info("{$antrian->count()} tile perlu diambil dari GeoServer ({$pltu->count()} PLTU).");
    if ($this->option('hitung')) {
        return;
    }

    // Bentuk pulau dan sub-wilayahnya (provinsi tiap pulau, kabupaten tiap provinsi) juga disiapkan,
    // supaya hover dan klik setelah memilih wilayah langsung tersedia. Sudah di-cache = cepat dilewati.
    $controller = app(PltuServiceController::class);
    $controller->getBatasPulau();
    foreach (['id', 'en'] as $locale) {
        $controller->getWilayahProvinsi(Request::create('/wilayah-provinsi', 'GET', ['locale' => $locale]));
    }
    $induk = [
        3 => DB::connection('pgsql')->table(DB::raw('"proteus"."POLITICAL_LEVEL_2_dissolved"'))->pluck('id'),
        4 => DB::connection('pgsql')->table(DB::raw('"proteus"."POLITICAL_LEVEL_3_dissolved"'))->pluck('id'),
    ];
    $this->info('Menyiapkan bentuk sub-wilayah pulau dan provinsi...');
    $bar = $this->output->createProgressBar(($induk[3]->count() + $induk[4]->count()) * 2);
    foreach ($induk as $level => $ids) {
        foreach ($ids as $id) {
            foreach (['id', 'en'] as $locale) {
                $controller->getWilayahAnak(Request::create('/wilayah-anak', 'GET', ['level' => $level, 'induk' => $id, 'locale' => $locale]));
                $bar->advance();
            }
            // Garis luar pulau/provinsi itu saat dipilih
            $controller->getWilayahGaris(Request::create('/wilayah-garis', 'GET', ['level' => $level - 1, 'id' => $id]));
        }
    }
    $bar->finish();
    $this->newLine();

    if ($antrian->isEmpty()) {
        return;
    }

    $bar = $this->output->createProgressBar($antrian->count());
    $gagal = 0;

    // 8 permintaan sekaligus, supaya cepat tapi tidak membebani GeoServer
    foreach ($antrian->chunk(8) as $kelompok) {
        $kelompok = $kelompok->values();
        $hasil = Http::pool(function (Pool $pool) use ($kelompok) {
            foreach ($kelompok as $t) {
                $pool->timeout(60)->get(BatasTile::GEOSERVER, BatasTile::parameter(...$t));
            }
        });

        foreach ($kelompok as $i => [$lapisan, $z, $x, $y, $retina]) {
            $res = $hasil[$i];
            if ($res instanceof Response && $res->successful() && str_starts_with((string) $res->header('Content-Type'), 'image/png')) {
                BatasTile::simpan($lapisan, $z, $x, $y, $retina, $res->body());
            } else {
                $gagal++;
            }
            $bar->advance();
        }
    }

    $bar->finish();
    $this->newLine();
    $this->info($gagal ? "Selesai, {$gagal} tile gagal. Jalankan ulang perintah ini untuk mencobanya lagi." : 'Selesai.');
})->purpose('Isi cache tile garis batas wilayah lebih dulu supaya peta cepat sejak kunjungan pertama');
