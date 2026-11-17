@php
    // Ikon template (viewBox 0 0 48 32)
    $ikonTemplate = [
        'bar' => '<rect x="6" y="14" width="8" height="14" rx="1.5"/><rect x="20" y="6" width="8" height="22" rx="1.5"/><rect x="34" y="10" width="8" height="18" rx="1.5"/>',
        'bar-horizontal' => '<rect x="6" y="4" width="30" height="6" rx="1.5"/><rect x="6" y="13" width="20" height="6" rx="1.5"/><rect x="6" y="22" width="36" height="6" rx="1.5"/>',
        'bar-stacked' => '<rect x="6" y="17" width="8" height="11"/><rect x="6" y="10" width="8" height="7" opacity=".4"/><rect x="20" y="12" width="8" height="16"/><rect x="20" y="4" width="8" height="8" opacity=".4"/><rect x="34" y="19" width="8" height="9"/><rect x="34" y="11" width="8" height="8" opacity=".4"/>',
        'bar-100' => '<rect x="6" y="4" width="8" height="24"/><rect x="6" y="4" width="8" height="10" opacity=".4"/><rect x="20" y="4" width="8" height="24"/><rect x="20" y="4" width="8" height="15" opacity=".4"/><rect x="34" y="4" width="8" height="24"/><rect x="34" y="4" width="8" height="7" opacity=".4"/>',
        'bar-horizontal-stacked' => '<rect x="6" y="4" width="18" height="6"/><rect x="24" y="4" width="12" height="6" opacity=".4"/><rect x="6" y="13" width="12" height="6"/><rect x="18" y="13" width="10" height="6" opacity=".4"/><rect x="6" y="22" width="24" height="6"/><rect x="30" y="22" width="12" height="6" opacity=".4"/>',
        'area-stacked' => '<path d="M4 28 L4 20 L14 15 L24 17 L34 10 L44 12 L44 28 Z" opacity=".35"/><path d="M4 28 L4 25 L14 22 L24 23 L34 19 L44 21 L44 28 Z"/>',
        'line' => '<polyline points="4,24 14,16 24,19 34,8 44,12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>',
        'area' => '<path d="M4 24 L14 16 L24 19 L34 8 L44 12 L44 28 L4 28 Z" opacity=".35"/><polyline points="4,24 14,16 24,19 34,8 44,12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>',
        'pie' => '<circle cx="24" cy="16" r="12" opacity=".35"/><path d="M24 16 L24 4 A12 12 0 0 1 35.4 19.7 Z"/>',
        'donut' => '<circle cx="24" cy="16" r="10" fill="none" stroke="currentColor" stroke-width="5" opacity=".35"/><path d="M24 6 A10 10 0 0 1 33.5 19" fill="none" stroke="currentColor" stroke-width="5"/>',
    ];

    $input = 'w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900 shadow-xs placeholder:text-gray-400 focus:border-green-600 focus:ring-2 focus:ring-green-600/20 focus:outline-none';
    $label = 'block text-xs font-medium text-gray-600';
    $tombol = 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 disabled:opacity-50';
    $tombolKecil = 'inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50 disabled:opacity-50';
    $segmen = 'inline-flex items-center rounded-lg bg-gray-100 p-1';
    $segmenItem = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition';
    $segmenAktif = "'bg-white text-gray-900 shadow-sm'";
    $segmenPasif = "'text-gray-500 hover:text-gray-800'";
@endphp

<div>
    <div wire:ignore x-data="editorGrafik(@js($awal), @js($opsi))" class="space-y-5"
        @keydown.window.ctrl.s.prevent="simpan()" @keydown.window.meta.s.prevent="simpan()"
        @beforeunload.window="if (berubah) { $event.preventDefault(); $event.returnValue = '' }">

        {{-- Judul dan aksi --}}
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ $opsi['urlDaftar'] }}" title="Kembali ke daftar grafik"
                class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-xs hover:bg-gray-50 hover:text-gray-900">
                <x-cms.ikon nama="kembali" />
            </a>

            <div class="flex-1 min-w-64">
                <p class="px-2 text-xs text-gray-400">Data & Grafik <span class="mx-1">/</span> <span x-text="id ? 'Ubah grafik' : 'Grafik baru'"></span></p>
                <input type="text" x-model="judul" placeholder="Tulis judul grafik..."
                    class="w-full rounded-md border border-transparent px-2 py-0.5 text-2xl font-semibold text-gray-900 placeholder:text-gray-300 hover:border-gray-200 focus:border-gray-300 focus:outline-none">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="berubah ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'">
                    <span class="h-1.5 w-1.5 rounded-full" :class="berubah ? 'bg-amber-500' : 'bg-green-500'"></span>
                    <span x-text="berubah ? 'Belum disimpan' : 'Tersimpan'"></span>
                </span>
                <span x-show="id" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="terbit ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'"
                    x-text="terbit ? 'Terbit' : 'Draf'"></span>

                <button type="button" @click="unduhPng()" class="{{ $tombol }}">
                    <x-cms.ikon nama="unduh" /> Unduh PNG
                </button>
                <button type="button" @click="simpan()" :disabled="menyimpan" title="Simpan (Ctrl+S)"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-60">
                    <x-cms.ikon nama="centang" />
                    <span x-text="menyimpan ? 'Menyimpan...' : 'Simpan'"></span>
                    <kbd class="hidden xl:inline rounded bg-green-700/60 px-1.5 py-0.5 text-[10px] font-normal text-green-50">Ctrl S</kbd>
                </button>
            </div>
        </div>

        <template x-if="masihContoh">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                <x-cms.ikon nama="info" class="mt-0.5 h-5 w-5 text-amber-500" />
                <p class="flex-1">
                    Grafik ini masih berisi <b>contoh data</b>. Ganti di tab Data: ketik langsung, tempel dari Excel/Google Sheets, atau unggah CSV.
                </p>
                <button type="button" @click="tab = 'data'" class="shrink-0 font-medium text-amber-900 underline underline-offset-2 hover:no-underline">
                    Buka tab Data
                </button>
            </div>
        </template>

        {{-- Tab dan ukuran pratinjau --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="{{ $segmen }}">
                <button type="button" @click="tab = 'pratinjau'" class="{{ $segmenItem }}"
                    :class="tab === 'pratinjau' ? {{ $segmenAktif }} : {{ $segmenPasif }}">
                    <x-cms.ikon nama="mata" /> Pratinjau
                </button>
                <button type="button" @click="tab = 'data'" class="{{ $segmenItem }}"
                    :class="tab === 'data' ? {{ $segmenAktif }} : {{ $segmenPasif }}">
                    <x-cms.ikon nama="tabel" /> Data
                    <span class="rounded-full bg-gray-200 px-1.5 text-[11px] text-gray-600" x-text="baris.length"></span>
                </button>
            </div>

            <div x-show="tab === 'pratinjau'" class="flex items-center gap-3">
                <span class="hidden sm:inline text-xs text-gray-400" x-text="perangkat === 'desktop' ? 'Lebar penuh' : lebarPratinjau"></span>
                <div class="{{ $segmen }}">
                    @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'ponsel' => 'Ponsel'] as $kunci => $nama)
                        <button type="button" @click="perangkat = '{{ $kunci }}'" title="{{ $nama }}" class="{{ $segmenItem }} px-2.5!"
                            :class="perangkat === '{{ $kunci }}' ? {{ $segmenAktif }} : {{ $segmenPasif }}">
                            <x-cms.ikon :nama="$kunci" />
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem] gap-6 items-start">

            <div>
                {{-- Pratinjau: tampilan seperti saat diterbitkan, di atas kanvas bertitik --}}
                <div x-show="tab === 'pratinjau'"
                    class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-50 bg-[radial-gradient(#d1d5db_1px,transparent_1px)] bg-size-[18px_18px] p-4 sm:p-10">
                    <figure class="mx-auto rounded-lg bg-white p-6 sm:p-8 shadow-sm ring-1 ring-gray-900/5 transition-all duration-300"
                        :style="{ maxWidth: lebarPratinjau }">
                        <h2 class="text-xl font-semibold leading-snug"
                            :class="[judul ? 'text-gray-900' : 'text-gray-300', kelasRataJudul]"
                            x-text="judul || 'Judul grafik'"></h2>
                        <p x-show="subjudul" class="mt-1 text-sm text-gray-500" :class="kelasRataJudul" x-text="subjudul"></p>

                        <div class="relative mt-5" :style="{ height: pengaturan.tinggi + 'px' }" x-effect="gambar()">
                            <canvas x-ref="kanvas"></canvas>
                            <div x-show="kosong" class="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 text-center">
                                <x-cms.ikon nama="grafik" class="h-8 w-8 text-gray-300" />
                                <p class="text-sm text-gray-500">Belum ada angka yang bisa digambar.</p>
                                <button type="button" @click="tab = 'data'" class="{{ $tombolKecil }}">Isi data</button>
                            </div>
                        </div>

                        <figcaption x-show="pengaturan.sumber || pengaturan.catatan" class="mt-5 border-t border-gray-100 pt-3 text-xs text-gray-500">
                            <span x-show="pengaturan.sumber">
                                <span x-text="pengaturan.bahasa === 'en' ? 'Source:' : 'Sumber:'"></span>
                                <a x-show="pengaturan.sumberUrl" :href="pengaturan.sumberUrl" target="_blank" rel="noopener"
                                    class="underline underline-offset-2 hover:text-gray-800" x-text="pengaturan.sumber"></a>
                                <span x-show="!pengaturan.sumberUrl" x-text="pengaturan.sumber"></span>
                            </span>
                            <span x-show="pengaturan.sumber && pengaturan.catatan" class="mx-1">&middot;</span>
                            <span x-text="pengaturan.catatan"></span>
                        </figcaption>
                    </figure>
                </div>

                {{-- Data: tabel seperti spreadsheet --}}
                <div x-show="tab === 'data'" x-cloak class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50/80 px-3 py-2.5">
                        <button type="button" @click="tambahBaris()" class="{{ $tombolKecil }}">
                            <x-cms.ikon nama="tambah" class="h-3.5 w-3.5" /> Baris
                        </button>
                        <button type="button" @click="tambahKolom()" :disabled="kolom.length >= {{ $opsi['maksKolom'] }}" class="{{ $tombolKecil }}">
                            <x-cms.ikon nama="tambah" class="h-3.5 w-3.5" /> Kolom
                        </button>
                        <span class="mx-1 h-5 w-px bg-gray-200"></span>
                        <label class="{{ $tombolKecil }} cursor-pointer">
                            <x-cms.ikon nama="unggah" class="h-3.5 w-3.5" /> Unggah CSV
                            <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="unggahCsv($event)">
                        </label>
                        <span class="ml-auto inline-flex items-center gap-1.5 text-xs text-gray-500">
                            <x-cms.ikon nama="info" class="h-3.5 w-3.5" />
                            Tempel dari Excel/Google Sheets: klik sel, lalu
                            <kbd class="rounded border border-gray-300 bg-white px-1 text-[10px]">Ctrl V</kbd>
                        </span>
                    </div>

                    <div class="max-h-[65vh] overflow-auto">
                        <table class="min-w-full border-collapse text-sm">
                            <thead class="sticky top-0 z-10 bg-gray-50">
                                <tr>
                                    <th class="w-10 border-b border-r border-gray-200"></th>
                                    <template x-for="(nama, c) in kolom" :key="c">
                                        <th class="min-w-40 border-b border-r border-gray-200 p-0 text-left align-top">
                                            <div class="flex items-center gap-1.5 px-2 pt-1.5">
                                                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-gray-200 px-1 text-[10px] font-semibold text-gray-600"
                                                    :class="c === 0 && 'bg-green-100! text-green-700!'" x-text="hurufKolom(c)"></span>
                                                <span class="text-[11px] font-normal text-gray-400" x-text="c === 0 ? 'Label' : 'Angka'"></span>
                                                <button type="button" x-show="c > 0 && kolom.length > 2" @click="hapusKolom(c)" title="Hapus kolom"
                                                    class="ml-auto rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                    <x-cms.ikon nama="silang" class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                            <input type="text" x-model="kolom[c]" @paste="tempel($event, -1, c)"
                                                :placeholder="c === 0 ? 'Nama label' : 'Nama seri'"
                                                class="w-full bg-transparent px-2 py-1.5 font-semibold text-gray-900 placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-600">
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(isi, i) in baris" :key="i">
                                    <tr class="group hover:bg-gray-50/70">
                                        <td class="relative border-b border-r border-gray-200 bg-gray-50 text-center text-xs text-gray-400">
                                            <span class="group-hover:invisible" x-text="i + 1"></span>
                                            <button type="button" @click="hapusBaris(i)" title="Hapus baris"
                                                class="absolute inset-0 hidden items-center justify-center text-red-500 hover:bg-red-50 group-hover:flex">
                                                <x-cms.ikon nama="silang" class="h-3.5 w-3.5" />
                                            </button>
                                        </td>
                                        <template x-for="(_, c) in kolom" :key="c">
                                            <td class="border-b border-r border-gray-200 p-0">
                                                <input type="text" x-model="baris[i][c]" :data-sel="i + '-' + c"
                                                    @paste="tempel($event, i, c)" @keydown.enter.prevent="turun(i, c)"
                                                    :class="c > 0 && 'text-right tabular-nums'"
                                                    class="w-full bg-transparent px-2 py-1.5 text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-green-600">
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50/80 px-3 py-2 text-xs text-gray-500">
                        <p>
                            Kolom <b>A</b> berisi label (mis. tahun atau kategori). Kolom <b>B</b> dan seterusnya berisi angka, satu kolom = satu seri.
                            <span x-show="lingkaran">Lingkaran dan donat hanya memakai kolom B.</span>
                        </p>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-1.5">
                                Pemisah desimal di tabel
                                <select x-model="pengaturan.formatData"
                                    class="rounded-md border border-gray-300 bg-white py-0.5 pl-1.5 pr-6 text-xs text-gray-700 focus:border-green-600 focus:outline-none">
                                    <option value="otomatis" x-text="'Otomatis: ' + (pemisahTebakan === 'titik' ? 'titik (1,234.5)' : 'koma (1.234,5)')"></option>
                                    <option value="koma">Koma (1.234,5)</option>
                                    <option value="titik">Titik (1,234.5)</option>
                                </select>
                            </label>
                            <span class="tabular-nums" x-text="baris.length + ' baris × ' + kolom.length + ' kolom'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel pengaturan --}}
            <aside class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">

                <x-cms.panel kunci="template" judul="Template" ikon="template">
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($pilihanJenis as $kunci => $nama)
                            <button type="button" @click="jenis = '{{ $kunci }}'"
                                :class="jenis === '{{ $kunci }}' ? 'border-green-600 bg-green-50 text-green-700 ring-1 ring-green-600' : 'border-gray-200 text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800'"
                                class="flex flex-col items-center gap-1.5 rounded-lg border px-1 py-2.5 text-[11px] font-medium leading-tight transition">
                                <svg viewBox="0 0 48 32" class="h-7 w-11" fill="currentColor">{!! $ikonTemplate[$kunci] !!}</svg>
                                {{ $nama }}
                            </button>
                        @endforeach
                    </div>
                </x-cms.panel>

                <x-cms.panel kunci="kombinasi" judul="Kombinasi batang & garis" ikon="garis" x-show="jenis === 'bar' || jenis === 'bar-stacked'">
                    <p class="text-xs text-gray-500">Seri yang dinyalakan digambar sebagai garis di atas batang, mis. total produksi dibanding rincian ekspor dan DMO.</p>
                    <template x-for="(nama, i) in namaWarna" :key="i">
                        <label class="flex items-center justify-between gap-3 text-sm text-gray-700 cursor-pointer">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ background: warnaSeri(i) }"></span>
                                <span class="truncate" x-text="nama"></span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="text-xs text-gray-400" x-text="pengaturan.seriGaris[i] ? 'Garis' : 'Batang'"></span>
                                <input type="checkbox" x-model="pengaturan.seriGaris[i]" class="sr-only peer">
                                <span class="relative w-9 h-5 shrink-0 rounded-full bg-gray-300 transition-colors peer-checked:bg-green-600 after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4"></span>
                            </span>
                        </label>
                    </template>
                </x-cms.panel>

                <x-cms.panel kunci="warna" judul="Warna" ikon="warna">
                    <div class="space-y-1.5">
                        <template x-for="(p, kunci) in palet" :key="kunci">
                            <button type="button" @click="pilihPalet(kunci)"
                                :class="paletAktif(kunci) ? 'border-green-600 bg-green-50 ring-1 ring-green-600' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                                class="flex w-full items-center gap-3 rounded-lg border px-2.5 py-2 transition">
                                <span class="flex -space-x-1">
                                    <template x-for="w in p.warna.slice(0, 6)" :key="w">
                                        <span class="h-5 w-5 rounded-full ring-2 ring-white" :style="{ background: w }"></span>
                                    </template>
                                </span>
                                <span class="flex-1 text-left text-xs font-medium text-gray-700" x-text="p.nama"></span>
                                <x-cms.ikon nama="centang" class="h-4 w-4 text-green-600" x-show="paletAktif(kunci)" />
                            </button>
                        </template>
                    </div>

                    <div class="space-y-1.5 border-t border-gray-100 pt-3">
                        <p class="{{ $label }}" x-text="lingkaran ? 'Warna per potongan' : 'Warna per seri'"></p>
                        <p x-show="adaWarnaKhusus" class="text-xs text-gray-400">Warna diatur satu per satu. Pilih palet di atas untuk kembali ke warna palet.</p>
                        <template x-for="(nama, i) in namaWarna" :key="i">
                            <div class="flex items-center gap-2.5">
                                <input type="color" :value="warnaSeri(i)" @input="aturWarna(i, $event.target.value)"
                                    class="h-7 w-9 shrink-0 cursor-pointer rounded-md border border-gray-300 bg-white p-0.5">
                                <span class="flex-1 truncate text-xs text-gray-700" x-text="nama"></span>
                                <button type="button" x-show="pengaturan.warnaKhusus?.[i]" @click="aturWarna(i, '')"
                                    title="Kembali ke warna palet" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                    <x-cms.ikon nama="silang" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </template>
                    </div>
                </x-cms.panel>

                <x-cms.panel kunci="teks" judul="Teks & sumber" ikon="teks">
                    <div>
                        <p class="{{ $label }}">Rata judul</p>
                        {{-- Seperti tombol perataan paragraf di Word --}}
                        <div class="{{ $segmen }} grid! grid-cols-4 w-full mt-1">
                            @foreach ([
                                'kiri' => ['Kiri', 'Rata kiri', 'rata-kiri'],
                                'tengah' => ['Tengah', 'Rata tengah', 'rata-tengah'],
                                'kanan' => ['Kanan', 'Rata kanan', 'rata-kanan'],
                                'rata' => ['Justify', 'Rata kiri-kanan (justify)', 'rata-penuh'],
                            ] as $kunci => [$nama, $keterangan, $ikon])
                                <button type="button" @click="pengaturan.rataJudul = '{{ $kunci }}'" title="{{ $keterangan }}"
                                    class="flex flex-col items-center gap-0.5 rounded-md py-1.5 text-[11px] font-medium transition"
                                    :class="pengaturan.rataJudul === '{{ $kunci }}' ? {{ $segmenAktif }} : {{ $segmenPasif }}">
                                    <x-cms.ikon :nama="$ikon" class="h-4 w-4" />
                                    {{ $nama }}
                                </button>
                            @endforeach
                        </div>
                        <p x-show="pengaturan.rataJudul === 'rata'" x-cloak class="mt-1 text-xs text-gray-400">
                            Setiap baris judul dan subjudul dibuat penuh dari tepi kiri sampai tepi kanan.
                        </p>
                    </div>
                    <div>
                        <p class="{{ $label }}">Bahasa grafik</p>
                        <div class="{{ $segmen }} grid! grid-cols-2 w-full mt-1">
                            @foreach (['id' => 'Indonesia', 'en' => 'English'] as $kunci => $nama)
                                <button type="button" @click="pengaturan.bahasa = '{{ $kunci }}'" class="rounded-md py-1 text-xs font-medium transition"
                                    :class="pengaturan.bahasa === '{{ $kunci }}' ? {{ $segmenAktif }} : {{ $segmenPasif }}">{{ $nama }}</button>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Untuk tulisan bawaan di bawah grafik: "Sumber:" atau "Source:".</p>
                    </div>
                    <label class="{{ $label }}">Subjudul
                        <textarea x-model="subjudul" rows="2" placeholder="Keterangan singkat di bawah judul" class="{{ $input }} mt-1"></textarea>
                    </label>
                    <label class="{{ $label }}">Sumber data
                        <input type="text" x-model="pengaturan.sumber" placeholder="mis. Kementerian ESDM" class="{{ $input }} mt-1">
                    </label>
                    <label class="{{ $label }}">Tautan sumber
                        <input type="url" x-model="pengaturan.sumberUrl" placeholder="https://..." class="{{ $input }} mt-1">
                    </label>
                    <label class="{{ $label }}">Catatan kaki
                        <textarea x-model="pengaturan.catatan" rows="2" class="{{ $input }} mt-1"></textarea>
                    </label>
                </x-cms.panel>

                <x-cms.panel kunci="legenda" judul="Legenda" ikon="legenda">
                    <div class="{{ $segmen }} grid! grid-cols-4 w-full">
                        @foreach (['atas' => 'Atas', 'bawah' => 'Bawah', 'kanan' => 'Kanan', 'sembunyi' => 'Tidak'] as $kunci => $nama)
                            <button type="button" @click="pengaturan.legenda = '{{ $kunci }}'" class="rounded-md py-1 text-xs font-medium transition"
                                :class="pengaturan.legenda === '{{ $kunci }}' ? {{ $segmenAktif }} : {{ $segmenPasif }}">{{ $nama }}</button>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400">Legenda tampil kalau ada lebih dari satu seri, atau untuk lingkaran dan donat.</p>
                </x-cms.panel>

                <x-cms.panel kunci="angka" judul="Angka" ikon="angka">
                    <label class="{{ $label }}">Format tampilan angka
                        <select x-model="pengaturan.formatAngka" class="{{ $input }} mt-1">
                            <option value="id">Indonesia (1.234,5)</option>
                            <option value="en">Internasional (1,234.5)</option>
                        </select>
                    </label>
                    <p class="text-xs text-gray-400">Hanya mengubah cara angka ditampilkan di grafik. Cara angka di tabel dibaca diatur di tab Data.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="{{ $label }}">Awalan
                            <input type="text" x-model="pengaturan.awalan" placeholder="mis. Rp " class="{{ $input }} mt-1">
                        </label>
                        <label class="{{ $label }}">Akhiran
                            <input type="text" x-model="pengaturan.akhiran" placeholder="mis. %" class="{{ $input }} mt-1">
                        </label>
                    </div>
                    <p class="text-xs text-gray-400">Awalan/akhiran lebih dari 3 huruf (mis. " juta ton") hanya tampil di tooltip, supaya sumbu dan label tidak penuh. Tulis satuannya di judul sumbu nilai.</p>
                    <label class="{{ $label }}">Angka desimal
                        <select x-model.number="pengaturan.desimal" class="{{ $input }} mt-1">
                            <option value="-1">Otomatis</option>
                            @foreach (range(0, 4) as $d)
                                <option value="{{ $d }}">{{ $d }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-cms.sakelar model="pengaturan.labelNilai" label="Tampilkan label nilai" />
                </x-cms.panel>

                <x-cms.panel kunci="sumbu" judul="Sumbu" ikon="sumbu" x-show="!lingkaran">
                    <label class="{{ $label }}">Judul sumbu label
                        <input type="text" x-model="pengaturan.judulSumbuLabel" placeholder="mis. Tahun" class="{{ $input }} mt-1">
                    </label>
                    <label class="{{ $label }}">Judul sumbu nilai
                        <input type="text" x-model="pengaturan.judulSumbuNilai" placeholder="mis. Juta ton" class="{{ $input }} mt-1">
                    </label>
                    <x-cms.sakelar model="pengaturan.garisBantu" label="Garis bantu" />
                    <div x-show="garis">
                        <x-cms.sakelar model="pengaturan.mulaiNol" label="Sumbu nilai mulai dari nol" />
                    </div>
                </x-cms.panel>

                <x-cms.panel kunci="garis" judul="Garis" ikon="garis" x-show="garis">
                    <x-cms.sakelar model="pengaturan.halus" label="Garis melengkung halus" />
                    <x-cms.sakelar model="pengaturan.titik" label="Tampilkan titik" />
                    <div x-show="pengaturan.titik">
                        <x-cms.sakelar model="pengaturan.titikBerongga" label="Titik berongga" />
                    </div>
                </x-cms.panel>

                <x-cms.panel kunci="ukuran" judul="Ukuran" ikon="ukuran">
                    <label class="{{ $label }}">
                        <span class="flex justify-between">Tinggi grafik <span class="font-normal tabular-nums text-gray-800" x-text="pengaturan.tinggi + ' px'"></span></span>
                        <input type="range" min="240" max="900" step="10" x-model.number="pengaturan.tinggi" class="mt-2 w-full accent-green-600">
                    </label>
                </x-cms.panel>

                <x-cms.panel kunci="bagikan" judul="Terbitkan & embed" ikon="bagikan">
                    <template x-if="!id">
                        <p class="rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-500">Simpan grafik dulu, lalu terbitkan untuk mendapatkan tautan dan kode embed.</p>
                    </template>
                    <template x-if="id && !terbit">
                        <div class="space-y-2.5">
                            <p class="text-sm text-gray-500">Grafik ini masih <b class="font-medium text-gray-700">draf</b> dan belum bisa dilihat publik.</p>
                            <button type="button" @click="aturTerbit(true)" :disabled="menyimpan"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-60">
                                <x-cms.ikon nama="bagikan" /> Terbitkan
                            </button>
                        </div>
                    </template>
                    <template x-if="id && terbit">
                        <div class="space-y-2.5">
                            <p class="flex items-center gap-1.5 text-sm text-green-700">
                                <x-cms.ikon nama="centang" /> Diterbitkan. Perubahan tampil setelah disimpan.
                            </p>
                            <a :href="urlTampil" target="_blank"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-blue-600 hover:bg-gray-50">
                                <span class="flex-1 truncate" x-text="urlTampil"></span>
                                <x-cms.ikon nama="tautan" />
                            </a>
                            <label class="{{ $label }}">Kode embed
                                <textarea readonly rows="4" x-text="kodeEmbed" @focus="$event.target.select()"
                                    class="{{ $input }} mt-1 bg-gray-50 font-mono text-[11px] text-gray-600"></textarea>
                            </label>
                            <div class="flex gap-2">
                                <button type="button" @click="salinEmbed()" class="{{ $tombol }} flex-1">
                                    <x-cms.ikon nama="salin" x-show="!disalin" />
                                    <x-cms.ikon nama="centang" class="h-4 w-4 text-green-600" x-show="disalin" />
                                    <span x-text="disalin ? 'Tersalin' : 'Salin kode'"></span>
                                </button>
                                <button type="button" @click="aturTerbit(false)" :disabled="menyimpan"
                                    class="{{ $tombol }} text-red-600! hover:bg-red-50!">Batalkan terbit</button>
                            </div>
                        </div>
                    </template>
                </x-cms.panel>
            </aside>
        </div>

        {{-- Pemberitahuan --}}
        <div x-show="pesan.tampil" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-lg bg-gray-900 px-4 py-3 text-sm text-white shadow-lg ring-1 ring-black/5">
            <span :class="pesan.galat ? 'text-red-400' : 'text-green-400'">
                <x-cms.ikon nama="peringatan" class="h-5 w-5" x-show="pesan.galat" />
                <x-cms.ikon nama="centang" class="h-5 w-5" x-show="!pesan.galat" />
            </span>
            <span x-text="pesan.teks"></span>
        </div>
    </div>
</div>
