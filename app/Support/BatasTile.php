<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

// Tile garis batas wilayah (level 1-6) dari GeoServer, disimpan di storage/app/public/batas-tiles
// supaya tiap tile cukup digambar GeoServer sekali. Setelah tersimpan, tile dilayani web server
// sebagai file statis lewat /storage/batas-tiles/...
class BatasTile
{
    const GEOSERVER = 'https://aws.simontini.id/geoserver/proteus/wms';

    // Setengah keliling bumi di Web Mercator (EPSG:3857), dalam meter
    const TEPI = 20037508.342789244;

    // Tile hanya disiapkan untuk sekitar Indonesia, supaya cache tidak bisa diisi tile sembarang
    const BATAS = ['lat' => [-15, 10], 'lng' => [90, 145]];

    public static function path(string $lapisan, int $z, int $x, int $y, bool $retina): string
    {
        return "batas-tiles/{$lapisan}/{$z}/{$x}/{$y}" . ($retina ? '@2x' : '') . '.png';
    }

    public static function ada(string $lapisan, int $z, int $x, int $y, bool $retina): bool
    {
        return Storage::disk('public')->exists(self::path($lapisan, $z, $x, $y, $retina));
    }

    public static function simpan(string $lapisan, int $z, int $x, int $y, bool $retina, string $png): void
    {
        Storage::disk('public')->put(self::path($lapisan, $z, $x, $y, $retina), $png);
    }

    // Parameter GetMap GeoServer untuk tile 512px pada grid Web Mercator (sama seperti hitungan
    // Leaflet dengan tileSize 512), atau null kalau tile di luar sekitar Indonesia
    public static function parameter(string $lapisan, int $z, int $x, int $y, bool $retina): ?array
    {
        [$level, $peran] = explode('-', $lapisan);
        $skala = $retina ? 2 : 1;

        $ukuran = 4 * self::TEPI / 2 ** $z;
        $minx = -self::TEPI + $x * $ukuran;
        $maxy = self::TEPI - $y * $ukuran;
        $bbox = [$minx, $maxy - $ukuran, $minx + $ukuran, $maxy];

        if ($bbox[2] < self::meterX(self::BATAS['lng'][0]) || $bbox[0] > self::meterX(self::BATAS['lng'][1])
            || $bbox[3] < self::meterY(self::BATAS['lat'][0]) || $bbox[1] > self::meterY(self::BATAS['lat'][1])) {
            return null;
        }

        // Garis bawaan GeoServer cuma 0.2px dan server menolak SLD kustom, jadi ketebalan diatur
        // lewat dpi: tebal (px) = 0.2 * dpi / 90. Garis induk digambar lebih tebal.
        $tebal = $peran === 'induk' ? 1.8 : 1;

        return [
            'service' => 'WMS',
            'request' => 'GetMap',
            'version' => '1.3.0',
            'layers' => "proteus:POLITICAL_LEVEL_{$level}_dissolved",
            'styles' => '',
            'format' => 'image/png',
            'transparent' => 'true',
            'format_options' => 'dpi:' . round($tebal * 450 * $skala),
            'width' => 512 * $skala,
            'height' => 512 * $skala,
            'crs' => 'EPSG:3857',
            'bbox' => implode(',', $bbox),
        ];
    }

    // Nomor tile 512px [x, y] yang memuat suatu titik pada zoom $z
    public static function nomor(float $lat, float $lng, int $z): array
    {
        $ukuran = 4 * self::TEPI / 2 ** $z;

        return [
            (int) floor((self::meterX($lng) + self::TEPI) / $ukuran),
            (int) floor((self::TEPI - self::meterY($lat)) / $ukuran),
        ];
    }

    private static function meterX(float $lng): float
    {
        return $lng * self::TEPI / 180;
    }

    private static function meterY(float $lat): float
    {
        return log(tan(M_PI / 4 + deg2rad($lat) / 2)) * self::TEPI / M_PI;
    }
}
