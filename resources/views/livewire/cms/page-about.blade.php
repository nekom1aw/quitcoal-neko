@php
    $bahasa = ['id' => 'Bahasa Indonesia', 'en' => 'English'];
    $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-xs placeholder:text-gray-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20';
    $label = 'flex items-baseline justify-between gap-3 text-sm font-medium text-gray-700';
    $tombol = 'inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 disabled:opacity-50';
    $kartu = 'rounded-xl border border-gray-200 bg-white shadow-sm';
@endphp

<div class="mx-auto max-w-6xl py-12"
    x-data="{
        lang: 'id',
        berubah: false,
        pesan: { tampil: false, teks: '' },
        urlPublik: @js(['id' => route('about', ['locale' => 'id']), 'en' => route('about', ['locale' => 'en'])]),

        init() {
            // Editor konten dibuat oleh x-tinymce di dalam halaman ini; perubahan isinya ditandai lewat event 'dirty'
            tinymce.on('AddEditor', ({ editor }) => {
                editor.on('dirty', () => this.berubah = true);
                editor.addShortcut('meta+s', 'Simpan', () => this.simpan());
            });
        },

        // HTML dianggap berisi kalau ada teksnya, atau ada gambar/video/embed
        ada(html) {
            return !!html && (/<(img|iframe|video)/i.test(html) || html.replace(/<[^>]*>|&nbsp;/g, '').trim() !== '');
        },
        isi(kolom, l) {
            return kolom === 'content' ? this.ada($wire['content_' + l]) : !!($wire[kolom + '_' + l] || '').trim();
        },
        lengkap(l) {
            return this.isi('title', l) && this.isi('content', l);
        },

        simpan() {
            // Pastikan isi editor terbaru ikut terkirim walaupun belum sempat tersinkron
            for (const l of ['id', 'en']) {
                const editor = tinymce.get('about_content_' + l);
                if (editor) $wire['content_' + l] = editor.getContent();
            }
            $wire.save();
        },
        tersimpan() {
            this.berubah = false;
            tinymce.get().forEach(e => e.setDirty(false));
            this.beriPesan('Halaman About tersimpan');
        },

        // Kolom English yang masih kosong diisi dari versi Indonesia sebagai bahan terjemahan
        salinDariIndonesia() {
            if (!this.isi('title', 'en')) $wire.title_en = $wire.title_id || '';
            if (!this.isi('content', 'en')) {
                $wire.content_en = $wire.content_id || '';
                tinymce.get('about_content_en')?.setContent($wire.content_en);
            }
            this.berubah = true;
            this.beriPesan('Isi Bahasa Indonesia disalin ke kolom English yang kosong');
        },

        beriPesan(teks) {
            this.pesan = { tampil: true, teks };
            clearTimeout(this.pesanTimer);
            this.pesanTimer = setTimeout(() => this.pesan.tampil = false, 2500);
        },
    }"
    @keydown.window.ctrl.s.prevent="simpan()" @keydown.window.meta.s.prevent="simpan()"
    @about-tersimpan.window="tersimpan()"
    @beforeunload.window="if (berubah) { $event.preventDefault(); $event.returnValue = '' }">

    {{-- Judul dan aksi --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Halaman About</h1>
            <p class="mt-1 text-sm text-gray-500">
                Isi halaman "Tentang" di situs.
                @if ($waktuSimpan)
                    <span class="text-gray-400">Terakhir disimpan {{ $waktuSimpan }}.</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                :class="berubah ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'">
                <span class="h-1.5 w-1.5 rounded-full" :class="berubah ? 'bg-amber-500' : 'bg-green-500'"></span>
                <span x-text="berubah ? 'Belum disimpan' : 'Tersimpan'">Tersimpan</span>
            </span>

            <a :href="urlPublik[lang]" href="{{ route('about', ['locale' => 'id']) }}" target="_blank" class="{{ $tombol }}"
                :title="berubah ? 'Perubahan yang belum disimpan belum tampil di situs' : ''">
                <x-cms.ikon nama="tautan" /> Lihat di situs
            </a>
            <button type="button" @click="simpan()" wire:loading.attr="disabled" wire:target="save" title="Simpan (Ctrl+S)"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-60">
                <x-cms.ikon nama="centang" />
                <span wire:loading.remove wire:target="save">Simpan</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
                <kbd class="hidden xl:inline rounded bg-green-700/60 px-1.5 py-0.5 text-[10px] font-normal text-green-50">Ctrl S</kbd>
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
            <x-cms.ikon nama="peringatan" class="mt-0.5 h-5 w-5 text-red-500" />
            <div>
                <p class="font-medium">Belum tersimpan. Periksa dulu:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $pesanGalat)
                        <li>{{ $pesanGalat }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">

        {{-- Isi per bahasa --}}
        <div class="{{ $kartu }}">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                <div class="inline-flex items-center rounded-lg bg-gray-100 p-1">
                    @foreach ($bahasa as $kode => $nama)
                        <button type="button" @click="lang = '{{ $kode }}'"
                            class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition"
                            :class="lang === '{{ $kode }}' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'">
                            <span class="rounded bg-gray-200/80 px-1 text-[10px] font-semibold uppercase text-gray-600">{{ $kode }}</span>
                            {{ $nama }}
                            <span class="h-1.5 w-1.5 rounded-full" :class="lengkap('{{ $kode }}') ? 'bg-green-500' : 'bg-amber-400'"
                                :title="lengkap('{{ $kode }}') ? 'Judul dan konten sudah diisi' : 'Judul atau konten masih kosong'"></span>
                        </button>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400">Kedua bahasa disimpan sekaligus.</p>
            </div>

            @foreach ($bahasa as $kode => $nama)
                <div x-show="lang === '{{ $kode }}'" @if ($kode !== 'id') x-cloak @endif class="space-y-6 p-5">

                    @if ($kode === 'en')
                        <div x-show="!lengkap('en')"
                            class="flex flex-wrap items-start gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                            <x-cms.ikon nama="info" class="mt-0.5 h-5 w-5 text-amber-500" />
                            <p class="min-w-60 flex-1">
                                Versi English belum lengkap. Selama kosong, halaman English menampilkan isi Bahasa Indonesia.
                            </p>
                            <button type="button" @click="salinDariIndonesia()"
                                class="shrink-0 font-medium text-amber-900 underline underline-offset-2 hover:no-underline">
                                Salin dari Bahasa Indonesia
                            </button>
                        </div>
                    @endif

                    <div>
                        <label for="judul_{{ $kode }}" class="{{ $label }}">
                            <span>Judul @if ($kode === 'id')<span class="text-red-500">*</span>@endif</span>
                        </label>
                        <input id="judul_{{ $kode }}" type="text" wire:model="title_{{ $kode }}" @input="berubah = true"
                            placeholder="{{ $kode === 'id' ? 'Contoh: Tentang QuitCoal' : 'Example: About QuitCoal' }}"
                            class="{{ $input }} mt-1.5 text-base! font-medium @error('title_' . $kode) border-red-400! @enderror">
                        @error('title_' . $kode)
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <p class="{{ $label }}">Konten</p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            Gunakan tombol <b class="font-medium text-gray-500">+ Tambah Image</b> / <b class="font-medium text-gray-500">+ Tambah Video</b> untuk media,
                            <b class="font-medium text-gray-500">+ Tambah Grafik</b> untuk grafik dari Data & Grafik, dan menu
                            <b class="font-medium text-gray-500">Insert</b> untuk tautan atau tabel.
                        </p>
                        <div class="mt-2">
                            <x-tinymce id="about_content_{{ $kode }}" model="content_{{ $kode }}" />
                        </div>
                        @error('content_' . $kode)
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Samping --}}
        <div class="space-y-6 lg:sticky lg:top-24">

            {{-- Kelengkapan --}}
            <div class="{{ $kartu }} p-5">
                <h2 class="text-sm font-semibold text-gray-900">Kelengkapan</h2>
                <div class="mt-3 space-y-4">
                    @foreach ($bahasa as $kode => $nama)
                        <div>
                            <button type="button" @click="lang = '{{ $kode }}'"
                                class="text-xs font-medium uppercase tracking-wide text-gray-400 hover:text-gray-700">{{ $nama }}</button>
                            <ul class="mt-1.5 space-y-1 text-sm">
                                @foreach (['title' => 'Judul', 'content' => 'Konten'] as $kolom => $namaKolom)
                                    <li class="flex items-center gap-2" :class="isi('{{ $kolom }}', '{{ $kode }}') ? 'text-gray-700' : 'text-gray-400'">
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full"
                                            :class="isi('{{ $kolom }}', '{{ $kode }}') ? 'bg-green-500 text-white' : 'border border-gray-300'">
                                            <x-cms.ikon nama="centang" class="h-2.5 w-2.5 stroke-3" x-show="isi('{{ $kolom }}', '{{ $kode }}')" />
                                        </span>
                                        {{ $namaKolom }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Pesan singkat --}}
    <div x-show="pesan.tampil" x-transition.opacity x-cloak
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-3 text-sm text-white shadow-lg">
        <x-cms.ikon nama="centang" class="h-4 w-4 text-green-400" />
        <span x-text="pesan.teks"></span>
    </div>
</div>
