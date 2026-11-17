<?php

namespace App\Support;

use App\Livewire\Cms\PageFormGrafik;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Impor grafik Flourish (template "Line, bar and pie charts") ke menu Data & Grafik. Halaman embed
// Flourish yang sudah dipublikasikan memuat data dan pengaturannya sebagai JSON (_Flourish_data,
// _Flourish_data_column_names, _Flourish_settings); keduanya diubah ke format grafik di sini.
class ImporFlourish
{
    // Isian grafik (seperti dari editor) dari tautan Flourish, atau RuntimeException berisi pesan untuk admin
    public static function dariTautan(string $tautan): array
    {
        // Diterima: flo.uri.sh/visualisation/123/embed, public.flourish.studio/visualisation/123/,
        // app.flourish.studio/visualisation/123/edit, atau kode embed <div ... data-src="visualisation/123">
        if (!preg_match('#visuali[sz]ation/(\d+)#', $tautan, $m)) {
            throw new RuntimeException('Tautan tidak dikenali. Contoh: https://flo.uri.sh/visualisation/4242524/embed');
        }

        // Selalu diambil dari alamat embed resmi berdasarkan nomornya, bukan dari tautan yang ditempel
        try {
            $res = Http::timeout(20)->get("https://flo.uri.sh/visualisation/{$m[1]}/embed");
        } catch (ConnectionException) {
            throw new RuntimeException('Flourish tidak bisa dihubungi. Periksa koneksi internet lalu coba lagi.');
        }
        if (!$res->successful()) {
            throw new RuntimeException('Visualisasi tidak ditemukan. Pastikan grafiknya sudah dipublikasikan di Flourish.');
        }

        $html = $res->body();
        $data = self::ambilJson($html, '_Flourish_data');
        $kolom = self::ambilJson($html, '_Flourish_data_column_names');
        $pengaturan = self::ambilJson($html, '_Flourish_settings') ?? [];
        preg_match('#<title>(.*?)</title>#s', $html, $judulHalaman);

        return self::keIsian($data, $kolom, $pengaturan, html_entity_decode($judulHalaman[1] ?? ''));
    }

    // Objek JSON setelah "nama = " di dalam script halaman embed
    private static function ambilJson(string $html, string $nama): ?array
    {
        if (!preg_match('/\b' . preg_quote($nama, '/') . '\s*=\s*\{/', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // Cari kurung kurawal penutupnya, dengan melewati isi string
        $mulai = $m[0][1] + strlen($m[0][0]) - 1;
        $dalam = 0;
        $diString = false;
        for ($i = $mulai, $n = strlen($html); $i < $n; $i++) {
            $c = $html[$i];
            if ($diString) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $diString = false;
                }
            } elseif ($c === '"') {
                $diString = true;
            } elseif ($c === '{') {
                $dalam++;
            } elseif ($c === '}' && --$dalam === 0) {
                return json_decode(substr($html, $mulai, $i - $mulai + 1), true);
            }
        }

        return null;
    }

    private static function keIsian(?array $data, ?array $kolom, array $set, string $judulHalaman): array
    {
        $baris = $data['data'] ?? null;
        $nama = $kolom['data'] ?? null;
        if (!is_array($baris) || !isset($nama['label'], $nama['value']) || !is_array($nama['value'])) {
            throw new RuntimeException('Visualisasi ini bukan grafik garis/batang/lingkaran Flourish, jadi belum bisa diimpor.');
        }

        $jumlahSeri = min(count($nama['value']), PageFormGrafik::MAKS_KOLOM - 1);
        if ($jumlahSeri < 1) {
            throw new RuntimeException('Visualisasi ini tidak punya kolom angka.');
        }
        $namaSeri = array_map('strval', array_slice($nama['value'], 0, $jumlahSeri));

        $isiBaris = [];
        foreach (array_slice($baris, 0, PageFormGrafik::MAKS_BARIS) as $b) {
            $nilai = array_values((array) ($b['value'] ?? []));
            $isiBaris[] = array_merge(
                [trim((string) ($b['label'] ?? ''))],
                array_map(fn ($k) => self::angka($nilai[$k] ?? ''), range(0, $jumlahSeri - 1))
            );
        }

        $jenis = self::jenis((string) ($set['chart_type'] ?? 'line'));
        $teks = fn ($kunci) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($set[$kunci] ?? '')))));

        $p = PageFormGrafik::PENGATURAN;
        // Angka hasil impor ditulis dengan koma desimal (lihat angka())
        $p['formatData'] = 'koma';
        // Judul Flourish rata tengah kecuali diatur lain
        $p['rataJudul'] = ['left' => 'kiri', 'right' => 'kanan'][$set['layout.header_align'] ?? 'center'] ?? 'tengah';
        $p['judulSumbuLabel'] = $teks('x.title');
        $p['judulSumbuNilai'] = $teks('y.title');
        $p['legenda'] = ($set['legend_mode'] ?? '') === 'off'
            ? 'sembunyi'
            : (['below' => 'bawah', 'right' => 'kanan'][$set['legend_position'] ?? ''] ?? 'atas');
        $p['labelNilai'] = !empty($set['labels']);
        $p['halus'] = !empty($set['line_interpolate']);
        // Titik mode "auto" di Flourish tampil kalau titiknya tidak terlalu rapat
        $p['titik'] = match ($set['dot_mode'] ?? 'auto') {
            'on' => true,
            'auto' => count($isiBaris) <= 50,
            default => false,
        };
        $p['titikBerongga'] = !empty($set['dot_hollow']);
        // Editor sudah menulis "Sumber:" di depan nama sumber
        $p['sumber'] = preg_replace('/^(sumber|source)\s*:\s*/i', '', $teks('layout.source_name'));
        $p['sumberUrl'] = trim((string) ($set['layout.source_url'] ?? ''));
        $p['catatan'] = $teks('layout.footer_note');
        $p['awalan'] = (string) ($set['number_format.prefix'] ?? $set['y.number_format.prefix'] ?? '');
        $p['akhiran'] = (string) ($set['number_format.suffix'] ?? $set['y.number_format.suffix'] ?? '');
        if (isset($set['y.linear_min']) && is_numeric($set['y.linear_min']) && $set['y.linear_min'] > 0) {
            $p['mulaiNol'] = false;
        }
        $p['warnaKhusus'] = self::warna($set, $jenis === 'pie' || $jenis === 'donut' ? array_column($isiBaris, 0) : $namaSeri);

        return [
            'judul' => $teks('layout.title') ?: trim($judulHalaman) ?: 'Grafik dari Flourish',
            'subjudul' => $teks('layout.subtitle'),
            'jenis' => $jenis,
            'kolom' => array_merge([(string) $nama['label']], $namaSeri),
            'baris' => $isiBaris,
            'pengaturan' => $p,
        ];
    }

    // chart_type Flourish -> template Data & Grafik
    private static function jenis(string $jenisFlourish): string
    {
        return match (true) {
            $jenisFlourish === 'column_grouped' => 'bar',
            $jenisFlourish === 'column_stacked_prop' => 'bar-100',
            str_starts_with($jenisFlourish, 'column_stacked') => 'bar-stacked',
            str_starts_with($jenisFlourish, 'bar_stacked') => 'bar-horizontal-stacked',
            str_starts_with($jenisFlourish, 'bar') => 'bar-horizontal',
            str_starts_with($jenisFlourish, 'area_stacked') => 'area-stacked',
            str_starts_with($jenisFlourish, 'area') => 'area',
            $jenisFlourish === 'donut' => 'donut',
            $jenisFlourish === 'pie' => 'pie',
            default => 'line',
        };
    }

    // Warna tiap seri: dari palet kustom Flourish ("Nama seri: #hex" per baris) kalau ada, kalau tidak
    // dari urutan palet kategorinya
    private static function warna(array $set, array $nama): array
    {
        $kustom = [];
        foreach (preg_split('/\R/', (string) ($set['color.categorical_custom_palette'] ?? '')) as $baris) {
            if (preg_match('/^(.+?)\s*:\s*(#[0-9a-f]{6})\s*$/i', trim($baris), $m)) {
                $kustom[$m[1]] = $m[2];
            }
        }
        $palet = array_values(array_filter((array) ($set['color.categorical_palette'] ?? []), 'is_string'));

        return array_map(
            fn ($n, $i) => $kustom[$n] ?? ($palet ? $palet[$i % count($palet)] : ''),
            $nama,
            array_keys($nama)
        );
    }

    // Angka Flourish ditulis format internasional (1,234.5); disimpan dalam format Indonesia tanpa
    // pemisah ribuan (1234,5) supaya terbaca benar di editor. Selain angka dibiarkan apa adanya.
    private static function angka($nilai): string
    {
        $s = str_replace([',', ' '], '', trim((string) $nilai));
        if (!is_numeric($s)) {
            return trim((string) $nilai);
        }

        return rtrim(rtrim(number_format((float) $s, 6, ',', ''), '0'), ',');
    }
}
