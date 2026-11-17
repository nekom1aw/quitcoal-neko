<?php

namespace App\Livewire\Cms;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Editor grafik ala Flourish di menu Data & Grafik. Editornya berjalan di browser (Alpine, lihat
// resources/js/grafik.js); komponen ini hanya memuat grafik dan menyimpannya.
class PageFormGrafik extends Component
{
    const JENIS = [
        'bar' => 'Batang',
        'bar-stacked' => 'Batang bertumpuk',
        'bar-100' => 'Batang 100%',
        'bar-horizontal' => 'Batang horizontal',
        'bar-horizontal-stacked' => 'Horizontal bertumpuk',
        'line' => 'Garis',
        'area' => 'Area',
        'area-stacked' => 'Area bertumpuk',
        'pie' => 'Lingkaran',
        'donut' => 'Donat',
    ];

    // Pengaturan bawaan sekaligus daftar pengaturan yang boleh disimpan (lihat bersihkanPengaturan)
    const PENGATURAN = [
        'palet' => 'quitcoal',
        'legenda' => 'atas',
        'formatAngka' => 'id', // cara angka ditampilkan: id (1.234,5) atau en (1,234.5)
        // Pemisah desimal data di tabel: otomatis (ditebak dari isi tabel), koma, atau titik
        'formatData' => 'otomatis',
        'awalan' => '',
        'akhiran' => '',
        'desimal' => -1, // -1 = otomatis
        'labelNilai' => false,
        'judulSumbuLabel' => '',
        'judulSumbuNilai' => '',
        'mulaiNol' => true,
        'garisBantu' => true,
        'halus' => true,
        'titik' => true,
        'titikBerongga' => false,
        'tinggi' => 420,
        'sumber' => '',
        'sumberUrl' => '',
        'catatan' => '',
        'rataJudul' => 'kiri', // kiri, tengah, kanan, atau rata (rata kiri-kanan)
        // Bahasa teks bawaan grafik ("Sumber:" atau "Source:"): id atau en
        'bahasa' => 'id',
        // Warna pilihan per seri (per potongan untuk lingkaran/donat); "" = ikut palet
        'warnaKhusus' => [],
        // Grafik kombinasi: seri yang digambar sebagai garis di atas batang (template batang dan
        // batang bertumpuk), per urutan seri
        'seriGaris' => [],
    ];

    const MAKS_KOLOM = 20;
    const MAKS_BARIS = 1000;

    #[Locked]
    public $grafikId = null;

    // Template galeri untuk grafik baru (kunci contoh())
    #[Locked]
    public $template = null;

    public function mount($id = null, $template = null)
    {
        if ($id !== null) {
            abort_unless(DB::table('grafik')->where('id', $id)->exists(), 404);
            $this->grafikId = (int) $id;
        }
        $this->template = array_key_exists((string) $template, self::contoh()) ? $template : 'bar';
    }

    // Template di galeri "Buat grafik", masing-masing dengan contoh data yang cocok supaya grafiknya
    // langsung terlihat; admin tinggal mengganti datanya. Data batu bara (juta ton) dari grafik
    // "Produksi Batu Bara: Ekspor vs DMO" (Kementerian ESDM); DMO 2008 tidak tersedia.
    // Tiap template: [kelompok, nama, keterangan, jenis, data, pengaturan].
    public static function contoh(): array
    {
        $produksi = [194, 217, 240, 256, 275, 353, 386, 475, 458, 462, 456, 461, 558, 616, 565, 614, 687, 775, 836, 817];
        $dmo = [50, 54, null, 58, 67, 81, 90, 97, 77, 87, 91, 97, 115, 138, 132, 133, 216, 213, 233, 247];
        $ekspor = [144, 163, 191, 198, 208, 273, 296, 378, 381, 375, 365, 364, 443, 478, 405, 435, 465, 518, 555, 391];
        // Tabel tahun $dari-2025 dengan kolom pilihan
        $tabel = function (int $dari, array $seri) {
            return [
                'kolom' => array_merge(['Tahun'], array_keys($seri)),
                'baris' => array_map(
                    fn ($t) => array_merge([(string) $t], array_map(fn ($nilai) => (string) ($nilai[$t - 2006] ?? ''), array_values($seri))),
                    range($dari, 2025)
                ),
            ];
        };
        $kategori = fn (array $seri) => [
            'kolom' => array_merge(['Kategori'], array_keys($seri)),
            'baris' => array_map(
                fn ($i) => array_merge(['Kategori ' . chr(65 + $i)], array_map(fn ($nilai) => (string) $nilai[$i], array_values($seri))),
                array_keys(reset($seri))
            ),
        ];
        $satuan = ['judulSumbuNilai' => 'Juta ton'];

        return [
            'line' => ['Garis & area', 'Garis', 'Tren dari waktu ke waktu, mis. produksi per tahun.', 'line',
                $tabel(2006, ['Produksi' => $produksi]), $satuan],
            'area' => ['Garis & area', 'Area', 'Tren yang menonjolkan besarnya nilai.', 'area',
                $tabel(2006, ['Produksi' => $produksi]), $satuan],
            'area-stacked' => ['Garis & area', 'Area bertumpuk', 'Tren beberapa bagian yang membentuk total.', 'area-stacked',
                $tabel(2009, ['DMO' => $dmo, 'Ekspor' => $ekspor]), $satuan],
            'bar' => ['Batang', 'Batang', 'Membandingkan beberapa seri berdampingan.', 'bar',
                $tabel(2019, ['DMO' => $dmo, 'Ekspor' => $ekspor]), $satuan],
            'bar-stacked' => ['Batang', 'Batang bertumpuk', 'Rincian bagian di tiap batang, mis. DMO dan ekspor.', 'bar-stacked',
                $tabel(2014, ['DMO' => $dmo, 'Ekspor' => $ekspor]), $satuan],
            'kombinasi' => ['Batang', 'Kombinasi batang & garis', 'Total sebagai garis di atas rinciannya sebagai batang.', 'bar-stacked',
                $tabel(2014, ['Produksi' => $produksi, 'DMO' => $dmo, 'Ekspor' => $ekspor]),
                $satuan + ['seriGaris' => [true], 'labelNilai' => true, 'warnaKhusus' => ['#333333', '#c9434f', '#ebcf7f']]],
            'bar-horizontal' => ['Batang', 'Batang horizontal', 'Membandingkan kategori dengan nama panjang.', 'bar-horizontal',
                $kategori(['Nilai' => [420, 365, 280, 190, 120, 75]]), []],
            'bar-horizontal-stacked' => ['Batang', 'Horizontal bertumpuk', 'Rincian bagian per kategori.', 'bar-horizontal-stacked',
                $kategori(['Bagian 1' => [240, 180, 150, 90, 60], 'Bagian 2' => [130, 150, 90, 70, 40]]), []],
            'bar-100' => ['Bagian dari keseluruhan', 'Batang 100%', 'Persentase tiap bagian per tahun, mis. porsi DMO dan ekspor.', 'bar-100',
                $tabel(2014, ['DMO' => $dmo, 'Ekspor' => $ekspor]), ['labelNilai' => true]],
            'pie' => ['Bagian dari keseluruhan', 'Lingkaran', 'Komposisi satu total, sebaiknya maksimal 6 bagian.', 'pie',
                $kategori(['Porsi' => [35, 25, 20, 12, 8]]), ['akhiran' => '%', 'labelNilai' => true]],
            'donut' => ['Bagian dari keseluruhan', 'Donat', 'Seperti lingkaran, dengan ruang di tengah.', 'donut',
                $kategori(['Porsi' => [35, 25, 20, 12, 8]]), ['akhiran' => '%', 'labelNilai' => true]],
        ];
    }

    // Isi pengaturan dari editor dirapikan: hanya kunci yang dikenal, tipe data sesuai bawaannya
    public static function bersihkanPengaturan($masuk): array
    {
        $hasil = self::PENGATURAN;
        foreach (self::PENGATURAN as $kunci => $bawaan) {
            if (is_array($bawaan) || !is_array($masuk) || !array_key_exists($kunci, $masuk) || !is_scalar($masuk[$kunci])) {
                continue;
            }
            $nilai = $masuk[$kunci];
            $hasil[$kunci] = match (true) {
                is_bool($bawaan) => (bool) $nilai,
                is_int($bawaan) => (int) $nilai,
                // Spasi awalan/akhiran bagian dari formatnya (" juta ton", "Rp ")
                in_array($kunci, ['awalan', 'akhiran']) => Str::limit((string) $nilai, 30, ''),
                default => Str::limit(trim((string) $nilai), 300, ''),
            };
        }

        $hasil['tinggi'] = max(240, min(900, $hasil['tinggi']));
        $hasil['desimal'] = max(-1, min(4, $hasil['desimal']));
        $hasil['rataJudul'] = in_array($hasil['rataJudul'], ['tengah', 'kanan', 'rata']) ? $hasil['rataJudul'] : 'kiri';
        $hasil['bahasa'] = $hasil['bahasa'] === 'en' ? 'en' : 'id';
        $hasil['formatData'] = in_array($hasil['formatData'], ['koma', 'titik']) ? $hasil['formatData'] : 'otomatis';
        $hasil['warnaKhusus'] = array_map(
            fn ($w) => is_string($w) && preg_match('/^#[0-9a-f]{6}$/i', $w) ? strtolower($w) : '',
            array_slice(array_values((array) ($masuk['warnaKhusus'] ?? [])), 0, self::MAKS_BARIS)
        );
        $hasil['seriGaris'] = array_map(
            fn ($v) => (bool) $v,
            array_slice(array_values((array) ($masuk['seriGaris'] ?? [])), 0, self::MAKS_KOLOM)
        );
        // Tautan sumber tampil di halaman publik, jadi hanya http(s)
        if (!preg_match('#^https?://#i', $hasil['sumberUrl']) || !filter_var($hasil['sumberUrl'], FILTER_VALIDATE_URL)) {
            $hasil['sumberUrl'] = '';
        }

        return $hasil;
    }

    // Isian grafik dari editor (atau impor Flourish) dirapikan dan diperiksa. Hasilnya kolom-kolom tabel
    // grafik siap disimpan, atau pesan galat (string).
    public static function rapikan($isi): array|string
    {
        $teks = fn ($nilai, $maks) => is_scalar($nilai) ? Str::limit(trim((string) $nilai), $maks, '') : '';

        $judul = $teks($isi['judul'] ?? '', 255);
        if ($judul === '') {
            return 'Judul grafik belum diisi.';
        }
        $jenis = $isi['jenis'] ?? '';
        if (!array_key_exists($jenis, self::JENIS)) {
            return 'Template grafik tidak dikenal.';
        }

        $kolom = array_map(fn ($k) => $teks($k, 100), array_slice(array_values((array) ($isi['kolom'] ?? [])), 0, self::MAKS_KOLOM));
        if (count($kolom) < 2) {
            return 'Data butuh minimal 2 kolom: label dan nilai.';
        }

        // Baris kosong dibuang, tiap baris dipotong/ditambah sesuai jumlah kolom
        $baris = [];
        foreach (array_slice(array_values((array) ($isi['baris'] ?? [])), 0, self::MAKS_BARIS) as $sel) {
            $sel = array_values((array) $sel);
            $rapi = array_map(fn ($c) => $teks($sel[$c] ?? '', 200), array_keys($kolom));
            if (implode('', $rapi) !== '') {
                $baris[] = $rapi;
            }
        }
        if (!$baris) {
            return 'Isi minimal satu baris data.';
        }

        return [
            'judul' => $judul,
            'subjudul' => $teks($isi['subjudul'] ?? '', 500) ?: null,
            'jenis' => $jenis,
            'data' => json_encode(['kolom' => $kolom, 'baris' => $baris], JSON_UNESCAPED_UNICODE),
            'pengaturan' => json_encode(self::bersihkanPengaturan($isi['pengaturan'] ?? []), JSON_UNESCAPED_UNICODE),
            'terbit' => (bool) ($isi['terbit'] ?? false),
            'updated_at' => now(),
        ];
    }

    public function simpan($isi)
    {
        // Editor tetap di browser; halaman tidak perlu digambar ulang
        $this->skipRender();

        $simpan = self::rapikan($isi);
        if (is_string($simpan)) {
            return ['ok' => false, 'pesan' => $simpan];
        }

        if ($this->grafikId) {
            DB::table('grafik')->where('id', $this->grafikId)->update($simpan);
            $id = $this->grafikId;
        } else {
            $id = DB::table('grafik')->insertGetId($simpan + ['created_at' => now()]);
        }

        return [
            'ok' => true,
            'id' => $id,
            'url' => route('cms.grafik.edit', ['locale' => app()->getLocale(), 'id' => $id]),
        ];
    }

    // Grafik baru dari template galeri: contoh datanya langsung tampil di pratinjau, diganti di tab Data
    private function awalTemplate(): array
    {
        [, , , $jenis, $data, $pengaturan] = self::contoh()[$this->template];

        return [
            'id' => null,
            'judul' => '',
            'subjudul' => '',
            'jenis' => $jenis,
            'data' => $data,
            'pengaturan' => self::bersihkanPengaturan(array_merge(self::PENGATURAN, $pengaturan)),
            'terbit' => false,
        ];
    }

    public function render()
    {
        $grafik = $this->grafikId ? DB::table('grafik')->where('id', $this->grafikId)->first() : null;

        $awal = $grafik ? [
            'id' => $grafik->id,
            'judul' => $grafik->judul,
            'subjudul' => $grafik->subjudul ?? '',
            'jenis' => $grafik->jenis,
            'data' => json_decode($grafik->data, true),
            'pengaturan' => self::bersihkanPengaturan(json_decode($grafik->pengaturan, true)),
            'terbit' => (bool) $grafik->terbit,
        ] : $this->awalTemplate();

        return view('livewire.cms.page-form-grafik', [
            'awal' => $awal,
            'pilihanJenis' => self::JENIS,
            'opsi' => [
                'urlDaftar' => route('cms.grafik.index', ['locale' => app()->getLocale()]),
                'urlTampil' => route('grafik.tampil', ['id' => '__ID__']),
                'maksKolom' => self::MAKS_KOLOM,
            ],
        ]);
    }
}
