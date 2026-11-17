@verbatim
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <style>
        /* Leaflet 1.9 memakai mix-blend-mode: plus-lighter untuk menutup celah antar tile, tapi pada
           zoom pecahan (zoomSnap 0.1) justru memunculkan garis terang. Diganti dengan tile yang
           dibuat sedikit tumpang tindih. */
        .peta-map.leaflet-container img.leaflet-tile {
            mix-blend-mode: normal;
        }

        .peta-basemap img.leaflet-tile {
            width: 256.5px !important;
            height: 256.5px !important;
        }

        .peta-batas img.leaflet-tile {
            width: 512.5px !important;
            height: 512.5px !important;
        }

        .peta-batas {
            transition: opacity .45s ease;
        }

        .peta-batas-info {
            background: rgba(255, 255, 255, .92);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            color: #334155;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .2);
        }

        .peta-batas-memuat {
            margin-left: 4px;
            color: #94a3b8;
        }

        .peta-pulau {
            transition: fill-opacity .2s ease;
        }

        /* Chrome menggambar kotak fokus di sekeliling poligon SVG yang diklik */
        .peta-map path.leaflet-interactive:focus {
            outline: none;
        }

        .peta-pulau-tooltip {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }

        .peta-pulau-elevasi {
            font-size: 12px;
            font-weight: 400;
            color: #475569;
        }

        .peta-sorotan-label {
            font-size: 13px;
            font-weight: 600;
            color: #1e3a8a;
        }

        .peta-pudar {
            opacity: 0 !important;
            transition: opacity .6s ease;
        }

        .peta-pulau-petunjuk {
            margin-top: 2px;
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
        }
    </style>

    <div wire:ignore class="peta-map relative z-0" style="width:100%; height:500px;" x-data='(() => {
        // Objek Leaflet disimpan di variabel biasa, bukan di data reaktif Alpine. Kalau Leaflet
        // menerima campuran proxy Alpine dan objek aslinya, event-nya (mis. tooltip) gagal dilepas
        // dan memicu error saat zoom.
        const lf = {
            map: null,
            markerLayer: null,
            indonesiaLayer: null,
            batasLayer: {},
            hoverSemua: {},
            hover: null,
            anak: null,
            batasIndonesia: null,
            sorotan: null,
            garisTerpilih: null,
            // Kecamatan turunan (dari desa) per kabupaten di tahap kecamatan, lihat aturKecamatanTurunan
            kecTurunan: null,
            kecTurunanKab: {},
            kecTurunanBbox: null,
            kecTurunanGiliran: 0
        };

        return {
            baseZoom: null,
            jenisPltu: "",

            // Batas wilayah dari GeoServer, berganti otomatis sesuai zoom.
            // induk = level di atasnya, digambar lebih tebal sebagai konteks.
            batas: {
                // Tampilan awal langsung level 2 (pulau); klik pulau akan zoom ke tahap provinsi
                tahap: [
                    { level: 2, dariZoom: 0 },
                    { level: 3, dariZoom: 5.5 },
                    { level: 4, dariZoom: 8, induk: 3 },
                    { level: 5, dariZoom: 10, induk: 4 },
                    { level: 6, dariZoom: 12, induk: 5 }
                ],
                teks: {
                    id: {
                        judul: "Batas wilayah", memuat: "memuat...",
                        level: ["Negara", "Pulau", "Provinsi", "Kabupaten/Kota", "Kecamatan", "Desa/Kelurahan"],
                        elevasi: "Ketinggian", dpl: "m dpl", laut: "Kedalaman laut", angka: "id-ID",
                        klikPulau: "Klik untuk memperbesar",
                        klikWilayah: "klik wilayah untuk memperbesar", klikDesa: "klik untuk melihat nama desa"
                    },
                    en: {
                        judul: "Boundaries", memuat: "loading...",
                        level: ["Country", "Island", "Province", "Regency/City", "District", "Village"],
                        elevasi: "Elevation", dpl: "m a.s.l.", laut: "Sea depth", angka: "en-US",
                        klikPulau: "Click to zoom in",
                        klikWilayah: "click an area to zoom in", klikDesa: "click to see the village name"
                    }
                }
            },
            batasLevel: null,
            batasAktif: [],
            batasGiliran: 0,
            batasLocale: "id",
            batasTeks: null,
            batasInfo: null,
            hoverMemuat: {},
            elevasiTile: {},
            elevasiGiliran: 0,
            elevasiInfo: null,
            // Setelah wilayah diklik, level berikutnya dipaksa tampil selama zoom masih di sekitar
            // zoom tujuan, walau ambang zoom level itu belum tercapai (mis. provinsi yang luas)
            fokus: null,
            klikGiliran: 0,
            anakGiliran: 0,
            garisGiliran: 0,
            // Untuk label "memuat...": tile garis batas, sub-wilayah terpilih, dan wilayah yang diklik
            batasMemuat: false,
            anakMemuat: false,
            klikMemuat: false,

            initMap() {
                if (lf.map) return;

                const bounds = L.latLngBounds(
                    [-11, 95],
                    [6.5, 141]
                );


                const mapBounds = bounds.pad(0.0);
                lf.batasIndonesia = mapBounds;

                lf.map = L.map(this.$el, {
                    zoomControl: true,
                    attributionControl: true,
                    minZoom: 4,
                    maxZoom: 20,
                    maxBounds: mapBounds,
                    maxBoundsViscosity: 1.0,
                    bounceAtZoomLimits: false,
                    zoomSnap: 0.1,
                    zoomDelta: 0.1
                });



                lf.map.setView([-2.5, 118], 4.9);

                this.baseZoom = lf.map.getZoom();
                lf.map.setMinZoom(this.baseZoom);


                L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
                    maxZoom: 20,
                    maxNativeZoom: 19,
                    attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors",
                    className: "peta-basemap"
                })
                .addTo(lf.map);

                this.initBatas();
                this.initElevasi();
                this.initKlikWilayah();

                fetch("/geojson-indonesia")
                    .then(res => res.json())
                    .then(geojson => {
                        lf.indonesiaLayer = L.geoJSON(geojson, {
                            style: {
                                color: "#ff0000",
                                weight: 1,
                                fillOpacity: 0.05
                            }
                        }).addTo(lf.map);
                    })
                    .catch(err => console.log("gagal load polygon indonesia", err));

                this.loadMarker();

                window.addEventListener("filter-jenis-pltu", (event) => {
                    this.jenisPltu = event.detail || "";
                    this.loadMarker();
                });
                lf.map.invalidateSize();
            },

            initBatas() {
                this.batasLocale = window.location.pathname.split("/").filter(Boolean)[0] === "en" ? "en" : "id";
                this.batasTeks = this.batas.teks[this.batasLocale];

                const Info = L.Control.extend({
                    onAdd: () => {
                        const el = L.DomUtil.create("div", "peta-batas-info");
                        L.DomEvent.disableClickPropagation(el);
                        return el;
                    }
                });
                this.batasInfo = new Info({ position: "bottomleft" }).addTo(lf.map).getContainer();

                lf.map.on("zoomend", () => this.perbaruiBatas());
                lf.map.on("moveend", () => this.aturKecamatanTurunan(this.batasLevel === 5 && !this.fokus));
                this.perbaruiBatas();
            },

            perbaruiBatas() {
                const map = lf.map;
                const zoom = map.getZoom();
                if (this.fokus && (zoom < this.fokus.min || zoom > this.fokus.max)) this.lepasPilihan();
                const tahap = this.fokus
                    ? this.batas.tahap.find(t => t.level === this.fokus.level)
                    : this.batas.tahap.filter(t => zoom >= t.dariZoom).pop();
                const level = tahap.level;

                // Ada wilayah terpilih: hanya garis wilayah itu dan sub-wilayahnya yang digambar (lihat
                // aturAnak), jadi garis tile yang memuat semua wilayah tidak dipakai
                const kunci = [];
                if (!this.fokus) {
                    kunci.push(level + "-utama");
                    if (tahap.induk) kunci.push(tahap.induk + "-induk");
                }

                this.aturHoverPulau(level === 2);
                this.aturHoverProvinsi(level === 3 && !this.fokus);
                this.aturKecamatanTurunan(level === 5 && !this.fokus);
                this.aturAnak(level);
                if (level === this.batasLevel && kunci.join() === this.batasAktif.join()) return;

                const giliran = ++this.batasGiliran;
                this.batasLevel = level;
                this.batasAktif = kunci;
                this.batasMemuat = kunci.length > 0;
                this.segarkanInfo();

                // Kembali ke tampilan pulau: kunci geser peta dikembalikan ke wilayah Indonesia
                if (level === 2 && map.options.maxBounds !== lf.batasIndonesia) {
                    map.setMaxBounds(lf.batasIndonesia);
                }

                // Layer tahap lama dipudarkan lalu dilepas, sekaligus membatalkan tile yang masih dimuat
                Object.keys(lf.batasLayer).forEach(k => {
                    if (kunci.includes(k)) return;
                    lf.batasLayer[k].setOpacity(0);
                    setTimeout(() => {
                        if (!this.batasAktif.includes(k)) map.removeLayer(lf.batasLayer[k]);
                    }, 450);
                });

                // Tiap layer muncul sendiri begitu tile-nya selesai dimuat
                let sisa = kunci.length;
                kunci.forEach(k => {
                    const layer = this.layerBatas(k);
                    const tampil = () => {
                        if (giliran !== this.batasGiliran) return;
                        layer.setOpacity(k.endsWith("-induk") ? 0.7 : 0.45);
                        if (--sisa === 0) {
                            this.batasMemuat = false;
                            this.segarkanInfo();
                        }
                    };

                    if (map.hasLayer(layer) && !layer.isLoading()) {
                        tampil();
                    } else {
                        layer.once("load", tampil);
                        map.addLayer(layer);
                    }
                });
            },

            layerBatas(kunci) {
                if (!lf.batasLayer[kunci]) {
                    const induk = kunci.endsWith("-induk");

                    // Tile diambil lewat cache Laravel (PltuServiceController::getBatasTile), bukan langsung
                    // dari GeoServer: tiap tile cukup digambar GeoServer sekali. {r} = "@2x" di layar retina.
                    // Tile 512px: biaya GeoServer hampir sama per request, jumlah request jadi 4x lebih sedikit.
                    lf.batasLayer[kunci] = L.tileLayer("/storage/batas-tiles/" + kunci + "/{z}/{x}/{y}{r}.png", {
                        tileSize: 512,
                        bounds: L.latLngBounds([-15, 90], [10, 145]),
                        maxZoom: 20,
                        className: "peta-batas",
                        zIndex: induk ? 3 : 2,
                        opacity: 0,
                        updateWhenZooming: false
                    });
                }
                return lf.batasLayer[kunci];
            },

            // Di tahap pulau dan tahap provinsi (selama belum ada wilayah yang dipilih), semua wilayahnya
            // bisa di-hover dan diklik. Garisnya sendiri sudah tampil dari tile.
            aturHoverPulau(aktif) {
                this.aturHoverSemua(2, "/batas-pulau", aktif);
            },

            aturHoverProvinsi(aktif) {
                this.aturHoverSemua(3, "/wilayah-provinsi?locale=" + this.batasLocale, aktif);
            },

            aturHoverSemua(level, url, aktif) {
                if (!aktif) {
                    this.lepasLayerHover(lf.hoverSemua[level]);
                    return;
                }
                if (lf.hoverSemua[level]) {
                    lf.map.addLayer(lf.hoverSemua[level]);
                    return;
                }
                if (this.hoverMemuat[level]) return;

                this.hoverMemuat[level] = true;
                fetch(url, { headers: { Accept: "application/json" } })
                    .then(res => res.json())
                    .then(geojson => {
                        // Data pulau menyimpan nama Inggris di kolom terpisah
                        geojson.features.forEach(f => {
                            if (this.batasLocale === "en" && f.properties.nama_en) f.properties.nama = f.properties.nama_en;
                        });
                        lf.hoverSemua[level] = this.buatLayerHover(geojson, level, false);
                        if (this.batasLevel === level && !this.fokus) lf.map.addLayer(lf.hoverSemua[level]);
                    })
                    .catch(err => console.log("gagal memuat batas wilayah", err))
                    .finally(() => this.hoverMemuat[level] = false);
            },

            // Tahap kecamatan tanpa wilayah terpilih. Tile GeoServer tidak punya garis kecamatan di kabupaten
            // yang kecamatannya dibentuk dari desa (seluruh Sumatera, sebagian besar Sulawesi Selatan/Tenggara/
            // Barat), jadi kecamatan kabupaten itu di area peta digambar sendiri, sekaligus bisa di-hover dan diklik.
            async aturKecamatanTurunan(aktif) {
                if (!lf.kecTurunan) lf.kecTurunan = L.featureGroup();
                if (!aktif) {
                    this.lepasLayerHover(lf.kecTurunan);
                    return;
                }
                lf.map.addLayer(lf.kecTurunan);

                // zoomend dan moveend terpanggil berurutan untuk tampilan yang sama
                const b = lf.map.getBounds();
                const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map(n => n.toFixed(2)).join(",");
                if (bbox === lf.kecTurunanBbox) return;
                lf.kecTurunanBbox = bbox;
                const giliran = ++lf.kecTurunanGiliran;

                try {
                    const res = await fetch("/kecamatan-turunan?bbox=" + bbox, { headers: { Accept: "application/json" } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (giliran !== lf.kecTurunanGiliran) return;
                    data.kabupaten.forEach(kab => this.muatKecamatanTurunan(kab));
                } catch (err) {
                    console.log("gagal mencari kabupaten di area peta", err);
                }
            },

            async muatKecamatanTurunan(kab) {
                if (lf.kecTurunanKab[kab]) return;
                lf.kecTurunanKab[kab] = true;
                try {
                    const params = new URLSearchParams({ level: 5, induk: kab, induk_level: 4, locale: this.batasLocale });
                    const res = await fetch("/wilayah-anak?" + params.toString(), { headers: { Accept: "application/json" } });
                    if (!res.ok) throw new Error("status " + res.status);
                    lf.kecTurunan.addLayer(this.buatLayerHover(await res.json(), 5, true));
                } catch (err) {
                    // Dicoba lagi saat peta digeser berikutnya
                    delete lf.kecTurunanKab[kab];
                    lf.kecTurunanBbox = null;
                    console.log("gagal memuat kecamatan", err);
                }
            },

            // Sub-wilayah dari wilayah yang terakhir diklik (mis. kabupaten di provinsi itu), dimuat
            // selagi peta zoom supaya langsung bisa di-hover dan diklik tanpa menunggu server
            async muatAnak(level, indukId, indukLevel) {
                const giliran = ++this.anakGiliran;
                this.anakMemuat = true;
                this.segarkanInfo();
                if (lf.anak) this.lepasLayerHover(lf.anak.layer);
                lf.anak = null;
                indukLevel = indukLevel || level - 1;

                try {
                    const params = new URLSearchParams({ level: level, induk: indukId, induk_level: indukLevel, locale: this.batasLocale });
                    const res = await fetch("/wilayah-anak?" + params.toString(), { headers: { Accept: "application/json" } });
                    if (!res.ok) return;
                    const geojson = await res.json();
                    if (giliran !== this.anakGiliran) return;

                    if (!geojson.features.length) {
                        // Data kecamatan belum ada (mis. seluruh Sumatera, sebagian Sulawesi): kabupaten
                        // ini langsung ditampilkan sampai tingkat desa
                        if (level === 5) {
                            if (this.fokus && this.fokus.level === 5) {
                                this.fokus.level = 6;
                                // Kalau peta sudah mendarat, tahapnya diganti sekarang; kalau masih terbang,
                                // diganti saat mendarat
                                const zoom = lf.map.getZoom();
                                if (zoom >= this.fokus.min && zoom <= this.fokus.max) this.perbaruiBatas();
                            }
                            this.muatAnak(6, indukId, 4);
                        }
                        return;
                    }

                    lf.anak = { level: level, layer: this.buatLayerHover(geojson, level, true) };
                    this.aturAnak(this.batasLevel);
                } catch (err) {
                    console.log("gagal memuat sub-wilayah", err);
                } finally {
                    if (giliran === this.anakGiliran) {
                        this.anakMemuat = false;
                        this.segarkanInfo();
                    }
                }
            },

            // Sub-wilayah hanya tampil selama wilayahnya masih dipilih dan tahapnya sesuai levelnya
            aturAnak(level) {
                if (lf.garisTerpilih) {
                    if (this.fokus) {
                        lf.map.addLayer(lf.garisTerpilih);
                    } else {
                        lf.map.removeLayer(lf.garisTerpilih);
                    }
                }
                if (!lf.anak) return;
                if (this.fokus && level === lf.anak.level) {
                    lf.map.addLayer(lf.anak.layer);
                } else {
                    this.lepasLayerHover(lf.anak.layer);
                }
            },

            // Zoom keluar dari sekitar wilayah terpilih: pilihan selesai, tampilan kembali sesuai zoom
            lepasPilihan() {
                this.fokus = null;
                this.anakGiliran++;
                this.anakMemuat = false;
                if (lf.anak) this.lepasLayerHover(lf.anak.layer);
                lf.anak = null;
                if (lf.garisTerpilih) lf.map.removeLayer(lf.garisTerpilih);
                lf.garisTerpilih = null;
                this.garisGiliran++;
            },

            // Area yang bisa di-hover (disorot, nama dan ketinggian muncul) dan diklik. Dengan garis = true
            // batasnya ikut digambar (sub-wilayah dari wilayah terpilih); tanpa garis, areanya tak terlihat.
            buatLayerHover(geojson, level, garis) {
                return L.geoJSON(geojson, {
                    style: {
                        className: "peta-pulau",
                        stroke: garis,
                        color: "#1f2937",
                        weight: 1,
                        opacity: 0.5,
                        fillColor: "#2563eb",
                        fillOpacity: 0
                    },
                    onEachFeature: (fitur, layer) => {
                        const nama = fitur.properties.nama;
                        layer.bindTooltip(this.isiTooltip(nama, null, level), {
                            sticky: true,
                            direction: "top",
                            offset: [0, -8],
                            className: "peta-pulau-tooltip"
                        });
                        layer.on("mouseover", () => {
                            layer.setStyle({ fillOpacity: 0.15 });
                            lf.hover = { layer: layer, nama: nama, level: level };
                        });
                        layer.on("mouseout", () => {
                            layer.setStyle({ fillOpacity: 0 });
                            lf.hover = null;
                        });
                        layer.on("click", e => {
                            // Sudah ditangani di sini, jadi klik peta tidak perlu mencari wilayah ke server
                            L.DomEvent.stopPropagation(e);
                            this.pilihWilayah(fitur, level, e.latlng, layer.getBounds(), true);
                        });
                    }
                });
            },

            lepasLayerHover(layer) {
                if (!layer || !lf.map.hasLayer(layer)) return;
                // mouseout tidak terpanggil saat layer dilepas, jadi sorotan direset manual
                layer.setStyle({ fillOpacity: 0 });
                lf.map.removeLayer(layer);
                lf.hover = null;
            },

            isiTooltip(nama, elevasi, level) {
                const el = document.createElement("div");
                el.textContent = nama;
                const tambah = (teks, kelas) => {
                    const baris = document.createElement("div");
                    baris.className = kelas;
                    baris.textContent = teks;
                    el.appendChild(baris);
                };
                if (elevasi) tambah(elevasi, "peta-pulau-elevasi");
                if (level < 6) tambah(this.batasTeks.klikPulau, "peta-pulau-petunjuk");
                return el;
            },

            // Wilayah diklik: disorot, lalu zoom ke wilayah itu dan muat sub-wilayahnya. Desa hanya disorot.
            // levelBerikut 6 untuk kabupaten tanpa data kecamatan: langsung tampilkan desanya.
            pilihWilayah(fitur, level, latlng, bounds, dariHover, levelBerikut = level + 1) {
                // Desa yang diklik dari area hover sudah bernama di tooltip, jadi label sorotan tidak perlu
                this.sorotWilayah(fitur, latlng, !(dariHover && level >= 6));
                if (level >= 6) return;

                // Garis luar wilayah terpilih, tebal seperti garis induk; wilayah lain tidak bergaris.
                // Sementara memakai bentuk hover, lalu diganti garis yang lebih detail dari server.
                if (lf.garisTerpilih) lf.map.removeLayer(lf.garisTerpilih);
                lf.garisTerpilih = L.geoJSON(fitur.geometry, {
                    interactive: false,
                    style: { color: "#111827", weight: 2, opacity: 0.75, fill: false }
                });

                this.terbangKe(bounds || L.latLngBounds(fitur.properties.bounds), levelBerikut, level, fitur.properties.id);
                this.muatAnak(levelBerikut, fitur.properties.id, level);
                this.muatGarisTerpilih(level, fitur.properties.id);
            },

            async muatGarisTerpilih(level, id) {
                const giliran = ++this.garisGiliran;
                try {
                    const params = new URLSearchParams({ level: level, id: id });
                    const res = await fetch("/wilayah-garis?" + params.toString(), { headers: { Accept: "application/json" } });
                    if (!res.ok) return;
                    const geometry = await res.json();
                    if (giliran !== this.garisGiliran || !lf.garisTerpilih) return;
                    lf.garisTerpilih.clearLayers();
                    lf.garisTerpilih.addData(geometry);
                } catch (err) {
                    console.log("gagal memuat garis wilayah", err);
                }
            },

            // Zoom pas ke wilayah terpilih (pulau, provinsi, ...) lalu tampilkan levelBerikut di dalamnya
            terbangKe(bounds, levelBerikut, levelTerpilih, idTerpilih) {
                const map = lf.map;
                const pusat = bounds.getCenter();
                const zoom = Math.min(map.getBoundsZoom(bounds, false, L.point(40, 40)), 16);
                this.fokus = { level: levelBerikut, terpilih: levelTerpilih, id: idTerpilih, min: zoom - 0.5, max: zoom + 1.5 };

                // Peta dikunci ke wilayah Indonesia, jadi wilayah di pinggir (mis. Sumatera, Papua) akan
                // tergeser ke tepi. Kuncinya dilonggarkan selebar tampilan tujuan supaya wilayah tepat di tengah.
                // Diisi langsung ke options (bukan setMaxBounds) karena setMaxBounds memicu animasi geser
                // yang memotong flyTo di tengah jalan.
                const tengah = map.project(pusat, zoom);
                const setengah = map.getSize().divideBy(2);
                const tampilan = L.latLngBounds(
                    map.unproject(tengah.subtract(setengah), zoom),
                    map.unproject(tengah.add(setengah), zoom)
                );
                map.options.maxBounds = L.latLngBounds(lf.batasIndonesia.getSouthWest(), lf.batasIndonesia.getNorthEast()).extend(tampilan);

                map.flyTo(pusat, zoom, { duration: 1 });
            },

            // Klik di luar sub-wilayah yang sudah dimuat (mis. setelah zoom manual): wilayahnya dicari di
            // server (PostGIS), karena bentuk semua wilayah terlalu banyak untuk dimuat di browser.
            initKlikWilayah() {
                let tunda = null;
                lf.map.on("click", e => {
                    // Tahap pulau ditangani layer pulau
                    if (this.batasLevel < 3) return;
                    // Ditunda sebentar supaya double-click (zoom bawaan) tidak ikut dianggap klik wilayah
                    clearTimeout(tunda);
                    tunda = setTimeout(() => this.klikWilayah(e.latlng), 250);
                });
                lf.map.on("dblclick", () => clearTimeout(tunda));
            },

            async klikWilayah(latlng) {
                // Ada wilayah terpilih: klik di luarnya memilih wilayah setingkat (mis. provinsi tetangga),
                // bukan sub-wilayahnya. Pulau tidak bisa dicari di server, jadi paling tidak provinsi.
                const level = this.fokus ? Math.max(3, this.fokus.terpilih) : this.batasLevel;
                const giliran = ++this.klikGiliran;
                const container = lf.map.getContainer();
                container.style.cursor = "progress";
                this.klikMemuat = true;
                this.segarkanInfo();

                try {
                    // Sekitar 200 kabupaten (seluruh Sumatera, sebagian besar Sulawesi Selatan/Tenggara/Barat)
                    // belum punya data kecamatan, jadi kabupatennya ikut dicari sekaligus
                    const [fitur, kab] = await Promise.all([
                        this.cariWilayah(level, latlng),
                        level === 5 ? this.cariWilayah(4, latlng) : null
                    ]);
                    if (giliran !== this.klikGiliran) return;

                    const f = this.fokus;
                    if (fitur && f && f.terpilih === level && f.id === fitur.properties.id) {
                        // Klik di dalam kabupaten terpilih tapi di luar kecamatannya: data kecamatannya hanya
                        // sebagian (Kab. Bone), jadi kabupaten itu ditampilkan sampai desa
                        if (level === 4 && f.level === 5 && !this.anakMemuat) this.pilihWilayah(fitur, 4, latlng, null, false, 6);
                    } else if (fitur) {
                        this.pilihWilayah(fitur, level, latlng);
                    } else if (kab) {
                        // Tidak ada kecamatan di titik itu: kabupatennya dipilih dan langsung ditampilkan sampai desa
                        this.pilihWilayah(kab, 4, latlng, null, false, 6);
                    }
                } catch (err) {
                    console.log("gagal mencari wilayah", err);
                } finally {
                    if (giliran === this.klikGiliran) {
                        container.style.cursor = "";
                        this.klikMemuat = false;
                        this.segarkanInfo();
                    }
                }
            },

            async cariWilayah(level, latlng) {
                const params = new URLSearchParams({ level: level, lat: latlng.lat, lng: latlng.lng, locale: this.batasLocale });
                const res = await fetch("/wilayah-di-titik?" + params.toString(), { headers: { Accept: "application/json" } });
                return res.ok ? res.json() : null;
            },

            // Wilayah yang diklik disorot dan diberi nama sebentar, lalu memudar
            sorotWilayah(fitur, latlng, denganLabel = true) {
                const map = lf.map;
                if (lf.sorotan) map.removeLayer(lf.sorotan);

                const sorotan = L.featureGroup([
                    L.geoJSON(fitur.geometry, {
                        interactive: false,
                        style: { color: "#2563eb", weight: 2, fillColor: "#2563eb", fillOpacity: 0.12 }
                    })
                ]);
                if (denganLabel) {
                    const label = document.createElement("span");
                    label.textContent = fitur.properties.nama;
                    sorotan.addLayer(
                        L.tooltip({ permanent: true, direction: "center", className: "peta-sorotan-label" })
                            .setLatLng(latlng)
                            .setContent(label)
                    );
                }
                lf.sorotan = sorotan.addTo(map);

                setTimeout(() => {
                    if (lf.sorotan !== sorotan) return;
                    map.getContainer().querySelectorAll(".peta-sorotan-label").forEach(el => el.classList.add("peta-pudar"));
                    sorotan.eachLayer(l => l.eachLayer && l.eachLayer(p => p.getElement() && p.getElement().classList.add("peta-pudar")));
                    setTimeout(() => {
                        if (lf.sorotan !== sorotan) return;
                        map.removeLayer(sorotan);
                        lf.sorotan = null;
                    }, 600);
                }, 2500);
            },

            // Ketinggian (m dpl) di bawah kursor, dibaca dari AWS Terrain Tiles format terrarium:
            // tiap piksel menyimpan tinggi = R * 256 + G + B / 256 - 32768
            initElevasi() {
                lf.map.attributionControl.addAttribution(this.batasTeks.elevasi + ": <a href=\"https://registry.opendata.aws/terrain-tiles/\">AWS Terrain Tiles</a>");

                const Info = L.Control.extend({
                    onAdd: () => {
                        const el = L.DomUtil.create("div", "peta-batas-info");
                        L.DomEvent.disableClickPropagation(el);
                        return el;
                    }
                });
                this.elevasiInfo = new Info({ position: "bottomleft" }).addTo(lf.map).getContainer();
                this.elevasiInfo.style.display = "none";

                // Dibatasi sekali per frame supaya gerakan mouse tetap ringan
                let posisi = null;
                let dijadwalkan = false;
                lf.map.on("mousemove", e => {
                    posisi = e.latlng;
                    if (dijadwalkan) return;
                    dijadwalkan = true;
                    requestAnimationFrame(() => {
                        dijadwalkan = false;
                        this.tampilkanElevasi(posisi);
                    });
                });
                // Perangkat sentuh tidak punya hover, jadi ketinggian muncul saat peta diketuk
                lf.map.on("click", e => this.tampilkanElevasi(e.latlng));
                lf.map.on("mouseout", () => {
                    this.elevasiGiliran++;
                    this.elevasiInfo.style.display = "none";
                });
            },

            async tampilkanElevasi(latlng) {
                const giliran = ++this.elevasiGiliran;
                const tinggi = await this.elevasiDi(latlng);
                if (giliran !== this.elevasiGiliran || tinggi === null) return;

                const t = this.batasTeks;
                const angka = Math.abs(Math.round(tinggi)).toLocaleString(t.angka);
                const nilai = tinggi >= 0 ? angka + " " + t.dpl : angka + " m";

                this.elevasiInfo.innerHTML = (tinggi >= 0 ? t.elevasi : t.laut) + ": <b>" + nilai + "</b>";
                this.elevasiInfo.style.display = "";

                if (lf.hover) {
                    lf.hover.layer.setTooltipContent(
                        this.isiTooltip(lf.hover.nama, tinggi >= 0 ? nilai : t.laut + " " + nilai, lf.hover.level)
                    );
                }
            },

            async elevasiDi(latlng) {
                // Terrain tiles tersedia sampai zoom 15
                const z = Math.min(15, Math.max(0, Math.round(lf.map.getZoom())));
                const titik = lf.map.project(latlng, z);
                const x = Math.floor(titik.x / 256);
                const y = Math.floor(titik.y / 256);

                const gambar = await this.tileElevasi(z, x, y);
                if (!gambar) return null;

                const i = (Math.floor(titik.y - y * 256) * 256 + Math.floor(titik.x - x * 256)) * 4;
                const d = gambar.data;
                return d[i] * 256 + d[i + 1] + d[i + 2] / 256 - 32768;
            },

            tileElevasi(z, x, y) {
                const kunci = z + "/" + x + "/" + y;
                if (!this.elevasiTile[kunci]) {
                    this.elevasiTile[kunci] = new Promise(resolve => {
                        const img = new Image();
                        img.crossOrigin = "anonymous";
                        img.onload = () => {
                            const kanvas = document.createElement("canvas");
                            kanvas.width = kanvas.height = 256;
                            const ctx = kanvas.getContext("2d", { willReadFrequently: true });
                            ctx.drawImage(img, 0, 0);
                            resolve(ctx.getImageData(0, 0, 256, 256));
                        };
                        img.onerror = () => {
                            delete this.elevasiTile[kunci];
                            resolve(null);
                        };
                        img.src = "https://s3.amazonaws.com/elevation-tiles-prod/terrarium/" + kunci + ".png";
                    });

                    // Simpan paling banyak 64 tile terakhir
                    const semua = Object.keys(this.elevasiTile);
                    if (semua.length > 64) delete this.elevasiTile[semua[0]];
                }
                return this.elevasiTile[kunci];
            },

            segarkanInfo() {
                if (this.batasLevel === null) return;
                this.tulisInfoBatas(this.batasLevel, this.batasMemuat || this.klikMemuat || (!!this.fokus && this.anakMemuat));
            },

            tulisInfoBatas(level, memuat) {
                const t = this.batasTeks;
                const petunjuk = level === 6 ? t.klikDesa : level >= 3 ? t.klikWilayah : "";
                this.batasInfo.innerHTML = t.judul + ": <b>" + t.level[level - 1] + "</b>" +
                    (memuat ? "<span class=\"peta-batas-memuat\">" + t.memuat + "</span>" : "") +
                    (!memuat && petunjuk ? "<span class=\"peta-batas-memuat\">· " + petunjuk + "</span>" : "");
            },

            async loadMarker() {
                try {
                    const params = new URLSearchParams();
                    if (this.jenisPltu) {
                        params.set("jenis_pltu", this.jenisPltu);
                    }

                    const url = params.toString() ? "/get-data?" + params.toString() : "/get-data";
                    const res = await fetch(url);
                    if (!res.ok) {
                        console.log("response error", res.status);
                        return;
                    }
                    const geojson = await res.json();
                    console.log("jumlah feature:", geojson.features ? geojson.features.length : 0);

                    if (lf.markerLayer) {
                        lf.map.removeLayer(lf.markerLayer);
                    }

                    if (geojson.features && geojson.features.length === 1) {
                        lf.markerLayer = L.layerGroup();
                    } else {
                        lf.markerLayer = L.markerClusterGroup({
                            spiderfyOnMaxZoom: false,
                            showCoverageOnHover: false,
                            zoomToBoundsOnClick: true,
                            disableClusteringAtZoom: 14
                        });
                    }

                    const geoLayer = L.geoJSON(geojson, {

                        pointToLayer: (feature, latlng) => {
                            const jenis = feature.properties ? feature.properties.jenis_pltu : null;
                            const color = jenis === "captive" ? "#dc2626" : jenis === "non captive" ? "#2563eb" : "#111827";
                            const icon = L.divIcon({
                                className: "",
                                html: "<div style=\"width:18px;height:18px;border-radius:999px;background:" + color + ";border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,.35);\"></div>",
                                iconSize: [18, 18],
                                iconAnchor: [9, 9],
                                popupAnchor: [0, -9]
                            });

                            return L.marker(latlng, { icon: icon });
                        },

                        onEachFeature: (feature, layer) => {
                            const p = feature.properties || {};
                            const locale = window.location.pathname.split("/").filter(Boolean)[0] || "id";
                            const detailUrl = "/" + locale + "/data/profil-pltu?id=" + (p.id ?? "");

                            const html = [
                                "<div style=\"font-size:12px; line-height:1.4;\">",
                                "<div style=\"font-weight:bold; font-size:14px; margin-bottom:6px;\">" + (p.nama ?? "-") + "</div>",
                                "<hr style=\"margin:6px 0;\">",
                                "<div><b>Jenis PLTU:</b> " + (p.jenis_pltu ?? "-") + "</div>",
                                "<div><b>Luas:</b> " + (p.luas ?? "-") + "</div>",
                                "<div><b>Pulau:</b> " + (p.level_2 ?? "-") + "</div>",
                                "<div><b>Provinsi:</b> " + (p.level_3 ?? "-") + "</div>",
                                "<div><b>Kabupaten/Kota:</b> " + (p.level_4 ?? "-") + "</div>",
                                "<div><b>Kecamatan:</b> " + (p.level_5 ?? "-") + "</div>",
                                "<div style=\"margin-top:10px;\">" +
                                    "<a href=\"" + detailUrl + "\" " +
                                    "style=\"display:inline-block; padding:6px 10px; background:#2563eb; color:white; text-decoration:none;\">" +
                                    "Informasi lebih lanjut</a>" +
                                "</div>",
                                "</div>"
                            ].join("");

                            layer.bindPopup(html);

                            const mapInstance = lf.map;
                            layer.on("click", function (e) {
                                mapInstance.flyTo(e.latlng, 13, {
                                    duration: 0.8
                                });
                            });
                        }
                    });

                    lf.markerLayer.addLayer(geoLayer);
                    lf.map.addLayer(lf.markerLayer);


                                } catch (e) {
                    console.log("gagal load marker");
                }
            }
        };
    })()' x-init="initMap()"></div>
@endverbatim
