<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\BatasTile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PltuServiceController extends Controller
{

    public function getMarker(Request $request)
    {
        $query = DB::table('profil_pltu')
            ->select(
                'id',
                'nama_pltu',
                'jenis_pltu',
                'level_2',
                'level_3',
                'level_4',
                'level_5',
                'level_6',
                'latitude',
                'longitude'
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (in_array($request->query('jenis_pltu'), ['captive', 'non captive'], true)) {
            $query->where('jenis_pltu', $request->query('jenis_pltu'));
        } elseif ($request->query('jenis_pltu') === 'belum diisi') {
            $query->whereNull('jenis_pltu');
        }

        $data = $query->get();

        $features = [];

        foreach ($data as $row) {
            $features[] = [
                "type" => "Feature",
                "geometry" => [
                    "type" => "Point",
                    "coordinates" => [
                        (float) $row->longitude,
                        (float) $row->latitude
                    ]
                ],
                "properties" => [
                    "id" => $row->id,
                    "nama" => $row->nama_pltu,
                    "jenis_pltu" => $row->jenis_pltu,
                    "level_2" => $row->level_2,
                    "level_3" => $row->level_3,
                    "level_4" => $row->level_4,
                    "level_5" => $row->level_5,
                    "level_6" => $row->level_6,
                    "latitude" => $row->latitude,
                    "longitude" => $row->longitude
                ]
            ];
        }

        return response()->json([
            "type" => "FeatureCollection",
            "features" => $features
        ]);
    }



    public function getProvinsiCentroid()
    {
        $rows = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_3_dissolved"'))
            ->selectRaw('
                "POLITICAL3" as nama,
                ST_Y(ST_PointOnSurface(geom)) as latitude,
                ST_X(ST_PointOnSurface(geom)) as longitude
            ')
            ->get();

        return response()->json($rows);
    }

    public function getPltuMarkerByKota($kota)
    {
        $rows = DB::connection('mysql')
            ->table('profil_pltu')
            ->where('level_4', $kota)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('level_4', 'level_6', 'latitude', 'longitude')
            ->get();

        return response()->json($rows);
    }


    public function getProvinsiCentroidPltu()
    {
        $provinsiList = DB::connection('mysql')
            ->table('profil_pltu')
            ->whereNotNull('level_3')
            ->distinct()
            ->pluck('level_3')
            ->toArray();

        if (count($provinsiList) === 0) {
            return response()->json([]);
        }

        $rows = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_3_dissolved"'))
            ->selectRaw('
                "POLITICAL3" as nama,
                ST_Y(ST_PointOnSurface(geom)) as latitude,
                ST_X(ST_PointOnSurface(geom)) as longitude
            ')
            ->whereIn(DB::raw('"POLITICAL3"'), $provinsiList)
            ->get();

        return response()->json($rows);
    }

    public function getProvinsiCqlFilter()
    {
        $provinsiList = DB::connection('mysql')
            ->table('profil_pltu')
            ->whereNotNull('level_3')
            ->distinct()
            ->pluck('level_3')
            ->toArray();

        if (count($provinsiList) === 0) {
            return response()->json([
                'cql' => "POLITICAL3 = '___NONE___'"
            ]);
        }

        $escaped = array_map(function ($p) {
            return str_replace("'", "''", $p);
        }, $provinsiList);

        $cql = "POLITICAL3 IN ('" . implode("','", $escaped) . "')";

        return response()->json([
            'cql' => $cql
        ]);
    }

    public function getProvinsiBounds($nama)
    {
        $row = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_3_dissolved"'))
            ->selectRaw("ST_Extent(geom) as extent")
            ->whereRaw("\"POLITICAL3\" = ?", [$nama])
            ->first();

        if (!$row || !$row->extent) {
            return response()->json(['error' => 'Provinsi tidak ditemukan'], 404);
        }

        preg_match(
            '/BOX\\(([-0-9\\.]+) ([-0-9\\.]+),([-0-9\\.]+) ([-0-9\\.]+)\\)/',
            $row->extent,
            $match
        );

        if (!$match) {
            return response()->json(['error' => 'Format extent tidak valid'], 500);
        }

        return response()->json([
            'bounds' => [
                [(float) $match[2], (float) $match[1]],
                [(float) $match[4], (float) $match[3]],
            ]
        ]);
    }
    public function getBatasPulau()
    {
        // Geometri aslinya ~5 juta titik. Untuk efek hover cukup bentuk kasar: pulau sangat kecil
        // (< ~6 km²) dibuang lalu disederhanakan. Query-nya ~8 detik, jadi hasilnya di-cache
        // (hapus dengan: php artisan cache:clear file).
        $data = $this->cacheBentuk()->rememberForever('batas-pulau-geojson-v2', function () {
            $rows = DB::connection('pgsql')->select('
                select t.id, t."LEVEL_2" as nama, t."POLITICAL2" as nama_en,
                       ST_AsGeoJSON(ST_Collect(ST_SimplifyPreserveTopology(d.geom, 0.02)), 3) as geojson
                from "proteus"."POLITICAL_LEVEL_2_dissolved" t, lateral ST_Dump(t.geom) d
                where ST_Area(d.geom) > 0.0005
                group by t.id, t."LEVEL_2", t."POLITICAL2"
            ');

            return [
                "type" => "FeatureCollection",
                "features" => array_map(fn ($row) => [
                    "type" => "Feature",
                    "geometry" => json_decode($row->geojson, true),
                    "properties" => [
                        "id" => $row->id,
                        "nama" => $row->nama,
                        "nama_en" => $row->nama_en
                    ]
                ], $rows)
            ];
        });

        return response()->json($data);
    }

    // Kolom nama per level: [Indonesia, Inggris]. Level 5 dan 6 hanya punya satu versi nama.
    private const KOLOM_NAMA = [
        3 => ['LEVEL_3', 'POLITICAL3'],
        4 => ['LEVEL_4', 'POLITICAL_'],
        5 => ['LEVEL_5', 'LEVEL_5'],
        6 => ['LEVEL_6', 'LEVEL_6'],
    ];

    // Bentuk wilayah untuk sorotan dan hover disederhanakan sesuai zoom tampilannya; makin kecil
    // wilayahnya makin detail. [toleransi penyederhanaan (derajat), jumlah desimal koordinat,
    // luas minimum pulau kecil yang tetap disertakan (derajat²)]. Pulau kecil (ribuan di Maluku
    // dan Papua) dibuang dari bentuk hover; garis batas yang terlihat tetap lengkap dari tile.
    private const DETAIL = [
        3 => [0.01, 3, 0.0005],
        4 => [0.002, 4, 0.00005],
        5 => [0.0007, 5, 0],
        6 => [0.0001, 5, 0],
    ];

    // Bentuk wilayah bisa ratusan KB (mis. provinsi di Papua), terlalu besar untuk cache database
    // (MySQL menolak: "server has gone away"), jadi disimpan di cache file
    private function cacheBentuk()
    {
        return Cache::store('file');
    }

    private function tabelWilayah(int $level): string
    {
        return "\"proteus\".\"POLITICAL_LEVEL_{$level}_dissolved\"";
    }

    // Panjang diagonal kotak batas suatu wilayah (derajat), untuk menyesuaikan detail bentuk dengan
    // ukurannya. Dihitung lebih dulu dan dikirim ke query berikutnya sebagai angka: kalau ditulis sebagai
    // ekspresi, PostgreSQL menghitungnya ulang untuk tiap pecahan pulau (ribuan di pulau besar).
    private function diagonalWilayah(int $level, int $id): ?float
    {
        $row = DB::connection('pgsql')->selectOne("
            select sqrt(power(ST_XMax(geom) - ST_XMin(geom), 2) + power(ST_YMax(geom) - ST_YMin(geom), 2)) as diagonal
            from {$this->tabelWilayah($level)} where id = ?
        ", [$id]);

        return $row ? (float) $row->diagonal : null;
    }

    // Kolom select untuk satu wilayah (alias tabel: w), hasilnya diubah lewat fiturWilayah()
    private function kolomWilayah(int $level, string $locale, ?float $toleransi = null): string
    {
        $nama = self::KOLOM_NAMA[$level][$locale === 'en' ? 1 : 0];
        [$toleransiBawaan, $desimal, $luasMin] = self::DETAIL[$level];
        $toleransi ??= $toleransiBawaan;

        $bentuk = "ST_SimplifyPreserveTopology(w.geom, {$toleransi})";
        if ($luasMin > 0) {
            $bentuk = "coalesce((
                select ST_Collect(ST_SimplifyPreserveTopology(d.geom, {$toleransi}))
                from ST_Dump(w.geom) d where ST_Area(d.geom) > {$luasMin}
            ), {$bentuk})";
        }

        return "w.id, w.\"{$nama}\" as nama,
            ST_XMin(w.geom) as xmin, ST_YMin(w.geom) as ymin, ST_XMax(w.geom) as xmax, ST_YMax(w.geom) as ymax,
            ST_AsGeoJSON({$bentuk}, {$desimal}) as geojson";
    }

    // Data kecamatan (tabel level 5) kosong atau tidak lengkap di sekitar 200 kabupaten: seluruh Sumatera,
    // sebagian besar Sulawesi Selatan/Tenggara/Barat. Di sana kecamatan dibentuk dari gabungan desa yang
    // nama kecamatannya (kolom LEVEL_5 tabel desa) sama. Id kecamatan turunan negatif, yaitu minus id desa
    // terkecil di dalamnya, supaya tidak bentrok dengan id tabel kecamatan.
    private function kecamatanDariDesa(int $kab): bool
    {
        return $this->cacheBentuk()->rememberForever("kecamatan-dari-desa:{$kab}", function () use ($kab) {
            // Kecamatan dicocokkan lewat nama kabupatennya lebih dulu, karena tabel kecamatan tidak punya
            // indeks spasial (mencocokkan lewat bentuk butuh beberapa detik per kabupaten)
            $row = DB::connection('pgsql')->selectOne("
                select (
                    select coalesce(sum(ST_Area(w.geom)), 0) from {$this->tabelWilayah(5)} w
                    where w.\"LEVEL_4\" = p.\"POLITICAL_\" and ST_Intersects(p.geom, ST_PointOnSurface(w.geom))
                ) / ST_Area(p.geom) as cakupan
                from {$this->tabelWilayah(4)} p where p.id = ?
            ", [$kab]);

            return $row !== null && $row->cakupan < 0.9;
        });
    }

    // Nama kecamatan sebuah desa (alias $desa, kabupatennya alias p). Desa tanpa nama kecamatan
    // dikelompokkan bersama dengan nama kabupatennya.
    private function kunciKecamatan(string $desa): string
    {
        return "coalesce({$desa}.\"LEVEL_5\", p.\"POLITICAL_\")";
    }

    // Kecamatan turunan di satu kabupaten (kolom: id, LEVEL_5, geom)
    private function sqlKecamatanTurunan(): string
    {
        return "
            select -min(d.id) as id, {$this->kunciKecamatan('d')} as \"LEVEL_5\", ST_Union(d.geom) as geom
            from {$this->tabelWilayah(6)} d, {$this->tabelWilayah(4)} p
            where p.id = ? and d.geom && p.geom and ST_Intersects(p.geom, ST_PointOnSurface(d.geom))
            group by 2";
    }

    // Desa-desa dalam satu kecamatan turunan, dicari dari salah satu desanya ($syaratDesa dengan alias x,
    // mis. "x.id = ?"). Kolomnya seperti tabel desa (id, LEVEL_6, geom) ditambah LEVEL_5 = nama kecamatan.
    private function sqlDesaSekecamatan(string $syaratDesa): string
    {
        return "
            with asal as (
                select p.id as kab, {$this->kunciKecamatan('x')} as kec
                from {$this->tabelWilayah(6)} x, {$this->tabelWilayah(4)} p
                where {$syaratDesa} and p.geom && x.geom and ST_Intersects(p.geom, ST_PointOnSurface(x.geom))
                limit 1
            )
            select d.id, d.\"LEVEL_6\", a.kec as \"LEVEL_5\", d.geom
            from {$this->tabelWilayah(6)} d, {$this->tabelWilayah(4)} p, asal a
            where p.id = a.kab and d.geom && p.geom and ST_Intersects(p.geom, ST_PointOnSurface(d.geom))
                and {$this->kunciKecamatan('d')} = a.kec";
    }

    // Satu kecamatan turunan sebagai Feature (seperti fiturWilayah), atau null kalau tidak ada
    private function fiturKecamatanTurunan(int $id): ?array
    {
        return $this->cacheBentuk()->rememberForever("kecamatan-turunan:{$id}", function () use ($id) {
            $row = DB::connection('pgsql')->selectOne("
                select {$this->kolomWilayah(5, 'id')} from (
                    select -min(s.id) as id, min(s.\"LEVEL_5\") as \"LEVEL_5\", ST_Union(s.geom) as geom
                    from ({$this->sqlDesaSekecamatan('x.id = ?')}) s
                ) w
            ", [-$id]);

            return $row && $row->id !== null ? $this->fiturWilayah($row) : null;
        });
    }

    private function fiturWilayah(object $row): array
    {
        return [
            "type" => "Feature",
            "geometry" => json_decode($row->geojson, true),
            "properties" => [
                "id" => $row->id,
                "nama" => $row->nama,
                "bounds" => [
                    [(float) $row->ymin, (float) $row->xmin],
                    [(float) $row->ymax, (float) $row->xmax]
                ]
            ]
        ];
    }

    // Wilayah level 3-6 di suatu titik (untuk klik di peta)
    public function getWilayahDiTitik(Request $request)
    {
        $validated = $request->validate([
            'level' => 'required|integer|between:3,6',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);
        $level = (int) $validated['level'];
        $locale = $request->query('locale') === 'en' ? 'en' : 'id';
        $tabel = $this->tabelWilayah($level);
        $titik = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)';
        $lngLat = [$validated['lng'], $validated['lat']];

        // Kecamatan di kabupaten yang data kecamatannya kosong/tidak lengkap: kecamatan turunan dari desa
        if ($level === 5) {
            $kab = DB::connection('pgsql')->selectOne("
                select id from {$this->tabelWilayah(4)} where ST_Intersects(geom, {$titik}) limit 1
            ", $lngLat);

            if ($kab && $this->kecamatanDariDesa($kab->id)) {
                $row = DB::connection('pgsql')->selectOne("
                    select -min(s.id) as id from ({$this->sqlDesaSekecamatan("ST_Intersects(x.geom, {$titik})")}) s
                ", $lngLat);
                $fitur = $row && $row->id !== null ? $this->fiturKecamatanTurunan((int) $row->id) : null;

                return $fitur ? response()->json($fitur) : response()->json(['error' => 'Wilayah tidak ditemukan'], 404);
            }
        }

        $wilayah = DB::connection('pgsql')->selectOne("
            select id from {$tabel}
            where ST_Intersects(geom, {$titik})
            limit 1
        ", $lngLat);

        if (!$wilayah) {
            return response()->json(['error' => 'Wilayah tidak ditemukan'], 404);
        }

        // Nama, batas, dan bentuk wilayah tidak berubah, jadi cukup dihitung sekali per wilayah
        $data = $this->cacheBentuk()->rememberForever("wilayah-v2:{$level}:{$wilayah->id}:{$locale}", function () use ($tabel, $level, $locale, $wilayah) {
            return $this->fiturWilayah(DB::connection('pgsql')->selectOne("
                select {$this->kolomWilayah($level, $locale)} from {$tabel} w where w.id = ?
            ", [$wilayah->id]));
        });

        return response()->json($data);
    }

    // Garis luar satu wilayah terpilih (level 2-6). Lebih detail dari bentuk hover: toleransinya
    // mengikuti ukuran wilayah itu sendiri (~1 piksel saat wilayah memenuhi peta), jadi tetap rapi
    // untuk kota kecil yang dilihat dari dekat. Pulau yang sangat kecil dibanding wilayahnya dibuang.
    public function getWilayahGaris(Request $request)
    {
        $validated = $request->validate([
            'level' => 'required|integer|between:2,6',
            'id' => 'required|integer|not_in:0',
        ]);
        $level = (int) $validated['level'];
        $id = (int) $validated['id'];

        $data = $this->cacheBentuk()->rememberForever("wilayah-garis:{$level}:{$id}", function () use ($level, $id) {
            // Id negatif = kecamatan turunan dari desa; satu kecamatan cukup kecil untuk dihitung dalam satu query
            if ($id < 0) {
                if ($level !== 5) {
                    return null;
                }
                $row = DB::connection('pgsql')->selectOne("
                    select ST_AsGeoJSON(ST_SimplifyPreserveTopology(g.geom, greatest(0.00003,
                        sqrt(power(ST_XMax(g.geom) - ST_XMin(g.geom), 2) + power(ST_YMax(g.geom) - ST_YMin(g.geom), 2)) / 1500
                    )), 5) as geojson
                    from (select ST_Union(s.geom) as geom from ({$this->sqlDesaSekecamatan('x.id = ?')}) s) g
                ", [-$id]);

                return $row && $row->geojson ? json_decode($row->geojson, true) : null;
            }

            $tabel = $this->tabelWilayah($level);

            $diagonal = $this->diagonalWilayah($level, $id);
            if ($diagonal === null) {
                return null;
            }
            $toleransi = max(0.00003, $diagonal / 1500);
            $luasMin = ($diagonal / 100) ** 2;

            $row = DB::connection('pgsql')->selectOne("
                select ST_AsGeoJSON(coalesce(
                    (select ST_Collect(ST_SimplifyPreserveTopology(d.geom, ?))
                     from ST_Dump(w.geom) d where ST_Area(d.geom) > ?),
                    ST_SimplifyPreserveTopology(w.geom, ?)
                ), 5) as geojson
                from {$tabel} w
                where w.id = ?
            ", [$toleransi, $luasMin, $toleransi, $id]);

            return json_decode($row->geojson, true);
        });

        if (!$data) {
            return response()->json(['error' => 'Wilayah tidak ditemukan'], 404);
        }

        return response()->json($data);
    }

    // Semua provinsi, untuk hover dan klik di tahap provinsi selama belum ada wilayah yang dipilih
    public function getWilayahProvinsi(Request $request)
    {
        $locale = $request->query('locale') === 'en' ? 'en' : 'id';

        $data = $this->cacheBentuk()->rememberForever("wilayah-provinsi:{$locale}", function () use ($locale) {
            $rows = DB::connection('pgsql')->select("select {$this->kolomWilayah(3, $locale)} from {$this->tabelWilayah(3)} w");

            return [
                "type" => "FeatureCollection",
                "features" => array_map(fn ($row) => $this->fiturWilayah($row), $rows)
            ];
        });

        return response()->json($data);
    }

    // Semua sub-wilayah level 3-6 di dalam satu wilayah induk (level di atasnya). Dimuat saat
    // induknya diklik, supaya sub-wilayah langsung bisa di-hover dan diklik tanpa menunggu server.
    // induk_level bisa lebih tinggi dari level di atasnya, mis. desa di satu kabupaten (6 dari 4)
    // untuk daerah yang data kecamatannya belum ada.
    public function getWilayahAnak(Request $request)
    {
        $validated = $request->validate([
            'level' => 'required|integer|between:3,6',
            'induk' => 'required|integer|not_in:0',
            'induk_level' => 'nullable|integer|between:2,5|lt:level',
        ]);
        $level = (int) $validated['level'];
        $induk = (int) $validated['induk'];
        $indukLevel = (int) ($validated['induk_level'] ?? $level - 1);
        $locale = $request->query('locale') === 'en' ? 'en' : 'id';

        $data = $this->cacheBentuk()->rememberForever("wilayah-anak:{$level}:{$indukLevel}-{$induk}:{$locale}", function () use ($level, $indukLevel, $induk, $locale) {
            // Desa satu kabupaten penuh (kabupaten tanpa data kecamatan) bisa ratusan, padahal dilihat
            // dari zoom kabupaten: detailnya mengikuti ukuran kabupaten, ukuran data turun sampai separuhnya
            $toleransi = null;
            if ($level === 6 && $indukLevel === 4) {
                $diagonal = $this->diagonalWilayah(4, $induk);
                $toleransi = $diagonal ? min(max($diagonal / 3000, 0.0001), 0.0005) : null;
            }

            if ($level === 5 && $indukLevel === 4 && $this->kecamatanDariDesa($induk)) {
                // Kecamatan turunan dari desa. Kalau hanya satu (desanya tanpa nama kecamatan), daftarnya
                // dikosongkan supaya kabupaten itu langsung ditampilkan sampai tingkat desa.
                $rows = DB::connection('pgsql')->select("
                    select {$this->kolomWilayah(5, $locale)} from ({$this->sqlKecamatanTurunan()}) w
                ", [$induk]);
                $rows = count($rows) > 1 ? $rows : [];
            } elseif ($induk < 0) {
                // Desa di kecamatan turunan
                $rows = $level === 6 && $indukLevel === 5 ? DB::connection('pgsql')->select("
                    select {$this->kolomWilayah(6, $locale)} from ({$this->sqlDesaSekecamatan('x.id = ?')}) w
                ", [-$induk]) : [];
            } else {
                // Sub-wilayah = wilayah yang titik di dalamnya jatuh di wilayah induk
                $rows = DB::connection('pgsql')->select("
                    select {$this->kolomWilayah($level, $locale, $toleransi)}
                    from {$this->tabelWilayah($level)} w, {$this->tabelWilayah($indukLevel)} p
                    where p.id = ? and w.geom && p.geom and ST_Intersects(p.geom, ST_PointOnSurface(w.geom))
                ", [$induk]);
            }

            return [
                "type" => "FeatureCollection",
                "features" => array_map(fn ($row) => $this->fiturWilayah($row), $rows)
            ];
        });

        return response()->json($data);
    }

    // Kabupaten di area peta yang kecamatannya dibentuk dari desa. Garis kecamatan di tile GeoServer
    // berasal dari tabel kecamatan, jadi di kabupaten ini tidak ada; peta menggambarnya sendiri dari
    // /wilayah-anak (level 5) tiap kabupaten ini.
    public function getKecamatanTurunanDiArea(Request $request)
    {
        $validated = $request->validate([
            'bbox' => ['required', 'regex:/^-?[0-9.]+(,-?[0-9.]+){3}$/'],
        ]);
        [$barat, $selatan, $timur, $utara] = array_map('floatval', explode(',', $validated['bbox']));

        $ids = DB::connection('pgsql')->table(DB::raw($this->tabelWilayah(4)))
            ->whereRaw('geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$barat, $selatan, $timur, $utara])
            ->pluck('id');

        return response()->json([
            'kabupaten' => $ids->filter(fn ($id) => $this->kecamatanDariDesa($id))->values(),
        ]);
    }

    // Tile garis batas wilayah. Route ini hanya terpanggil kalau file tile-nya belum ada di
    // storage/app/public/batas-tiles; setelah tersimpan, permintaan berikutnya langsung dilayani
    // web server sebagai file statis. Bisa disiapkan lebih dulu: php artisan peta:siapkan-batas
    public function getBatasTile(string $lapisan, int $z, int $x, string $y)
    {
        $retina = str_ends_with($y, '@2x.png');
        $parameter = BatasTile::parameter($lapisan, $z, $x, (int) $y, $retina);
        if (!$parameter) {
            abort(404);
        }

        // Saat GeoServer sedang ramai koneksinya kadang timeout, jadi dicoba ulang
        try {
            $res = Http::retry(3, 300, throw: false)->timeout(60)->get(BatasTile::GEOSERVER, $parameter);
        } catch (ConnectionException) {
            abort(502);
        }
        if (!$res->successful() || !str_starts_with((string) $res->header('Content-Type'), 'image/png')) {
            abort(502);
        }

        BatasTile::simpan($lapisan, $z, $x, (int) $y, $retina, $res->body());

        return response($res->body(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    public function getIndonesiaPolygon()
    {
        return response()->json([
            "type" => "FeatureCollection",
            "features" => []
        ]);
    }

    public function getKotaCentroidByProvinsi($provinsi)
    {
        $kotaList = DB::connection('mysql')
            ->table('profil_pltu')
            ->where('level_3', $provinsi)
            ->whereNotNull('level_4')
            ->distinct()
            ->pluck('level_4')
            ->toArray();

        if (count($kotaList) === 0) {
            return response()->json([]);
        }

        $rows = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_4_dissolved"'))
            ->selectRaw('
                "POLITICAL_" as nama,
                "LEVEL_3" as provinsi,
                ST_Y(ST_PointOnSurface(geom)) as latitude,
                ST_X(ST_PointOnSurface(geom)) as longitude
            ')
            ->whereIn(DB::raw('"POLITICAL_"'), $kotaList)
            ->get();

        return response()->json($rows);
    }

    public function getKotaBounds($nama)
    {
        $row = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_4_dissolved"'))
            ->selectRaw("ST_Extent(geom) as extent")
            ->whereRaw("\"POLITICAL_\" = ?", [$nama])
            ->first();

        if (!$row || !$row->extent) {
            return response()->json(['error' => 'Kota tidak ditemukan'], 404);
        }

        preg_match(
            '/BOX\\(([-0-9\\.]+) ([-0-9\\.]+),([-0-9\\.]+) ([-0-9\\.]+)\\)/',
            $row->extent,
            $match
        );

        if (!$match) {
            return response()->json(['error' => 'Format extent tidak valid'], 500);
        }

        return response()->json([
            'bounds' => [
                [(float) $match[2], (float) $match[1]],
                [(float) $match[4], (float) $match[3]],
            ]
        ]);
    }

    public function getKotaCqlFilterByProvinsi($provinsi)
    {
        $kotaList = DB::connection('mysql')
            ->table('profil_pltu')
            ->where('level_3', $provinsi)
            ->whereNotNull('level_4')
            ->distinct()
            ->pluck('level_4')
            ->toArray();

        if (count($kotaList) === 0) {
            return response()->json([
                'cql' => "POLITICAL_ = '___NONE___'"
            ]);
        }

        $escaped = array_map(function ($k) {
            return str_replace("'", "''", $k);
        }, $kotaList);

        $cql = "POLITICAL_ IN ('" . implode("','", $escaped) . "')";

        return response()->json([
            'cql' => $cql
        ]);
    }

    // ==============================
    // DESA CENTROID (LEVEL 6)
    // ==============================
    public function getDesaCentroidByKota($kota)
    {
        $desaList = DB::connection('mysql')
            ->table('profil_pltu')
            ->where('level_4', $kota)
            ->whereNotNull('level_6')
            ->distinct()
            ->pluck('level_6')
            ->toArray();

        if (count($desaList) === 0) {
            return response()->json([]);
        }

        $rows = DB::connection('pgsql')
            ->table(DB::raw('"proteus"."POLITICAL_LEVEL_6_dissolved"'))
            ->selectRaw('
                "LEVEL_6" as nama,
                ST_Y(ST_PointOnSurface(geom)) as latitude,
                ST_X(ST_PointOnSurface(geom)) as longitude
            ')
            ->whereIn(DB::raw('"LEVEL_6"'), $desaList)
            ->get();

        return response()->json($rows);
    }

    // ==============================
    // PLTU POINT (ASLI DARI MYSQL)
    // ==============================
    public function getPltuMarkerByDesa($desa)
    {
        $rows = DB::connection('mysql')
            ->table('profil_pltu')
            ->where('level_6', $desa)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('nama', 'level_6', 'latitude', 'longitude')
            ->get();

        return response()->json($rows);
    }
}
