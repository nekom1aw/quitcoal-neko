{{-- Dibuka di jendela "+ Tambah Grafik" pada editor TinyMCE (lihat components/tinymce). Grafik yang diklik
     dikirim ke editor lewat postMessage, lalu editor menyisipkan iframe-nya. --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sisipkan grafik</title>
    @vite(['resources/css/app.css', 'resources/js/grafik.js'])
</head>

<body class="bg-gray-50 text-gray-800"
    x-data="{
        cari: '',
        cocok(teks) {
            return teks.includes(this.cari.trim().toLowerCase());
        },
        get adaHasil() {
            return [...this.$root.querySelectorAll('[data-cari]')].some(k => this.cocok(k.dataset.cari));
        },
        pilih(grafik) {
            window.parent.postMessage({ mceAction: 'pilihGrafik', grafik }, window.location.origin);
        },
    }">

    <div class="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-gray-200 bg-white px-5 py-3">
        <div class="relative min-w-60 flex-1">
            <x-cms.ikon nama="cari" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="text" x-model="cari" placeholder="Cari judul grafik..." autofocus
                class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm shadow-xs placeholder:text-gray-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20">
        </div>
        <a href="{{ route('cms.grafik.insert', ['locale' => app()->getLocale()]) }}" target="_blank"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50">
            <x-cms.ikon nama="tambah" /> Buat grafik baru
        </a>
    </div>

    <main class="p-5">
        @if ($daftar->isEmpty())
            <div class="flex flex-col items-center rounded-xl border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-600">
                    <x-cms.ikon nama="grafik" class="h-6 w-6" />
                </span>
                <h2 class="mt-3 text-sm font-semibold text-gray-900">Belum ada grafik yang diterbitkan</h2>
                <p class="mt-1 max-w-sm text-sm text-gray-500">
                    Buat grafik di menu Data & Grafik, lalu terbitkan. Setelah itu muat ulang jendela ini.
                </p>
            </div>
        @else
            <p class="text-xs text-gray-500">
                Klik grafik untuk menyisipkannya ke konten. Hanya grafik yang sudah <b class="font-medium text-gray-700">diterbitkan</b> yang tampil di sini.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $item)
                    <button type="button"
                        data-cari="{{ mb_strtolower($item->judul . ' ' . $item->subjudul) }}"
                        x-show="cocok($el.dataset.cari)"
                        @click="pilih(@js(['id' => $item->id, 'judul' => $item->judul, 'tinggi' => $item->tinggiEmbed]))"
                        class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-green-500 hover:shadow-md focus-visible:outline-2 focus-visible:outline-green-600">
                        <div class="h-32 w-full border-b border-gray-100 bg-gray-50 px-4 pt-4 pb-2">
                            <div class="relative h-full" x-data="grafikMini(@js($item->spek))">
                                <canvas x-ref="kanvas"></canvas>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-3">
                            <h2 class="text-sm font-medium leading-snug text-gray-900 group-hover:text-green-700">{{ $item->judul }}</h2>
                            <p class="mt-auto pt-2 text-xs text-gray-400">
                                {{ $pilihanJenis[$item->jenis] ?? $item->jenis }}
                                · {{ \Illuminate\Support\Carbon::parse($item->updated_at)->format('d/m/Y') }}
                            </p>
                        </div>
                    </button>
                @endforeach
            </div>

            <p x-show="!adaHasil" x-cloak class="mt-4 rounded-xl border border-gray-200 bg-white py-10 text-center text-sm text-gray-500">
                Tidak ada grafik yang cocok dengan "<span x-text="cari"></span>".
            </p>
        @endif
    </main>

    @livewireScripts
</body>

</html>
