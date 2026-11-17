<div class="mx-auto flex min-h-screen max-w-6xl flex-col py-12" x-data="{ impor: {{ request()->has('impor') ? 'true' : 'false' }} }">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Data & Grafik</h1>
            <p class="mt-1 text-sm text-gray-500">Buat grafik dari data sendiri, lalu terbitkan dan embed di halaman mana pun.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="impor = true; $nextTick(() => $refs.tautan.focus())"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                <x-cms.ikon nama="unduh" /> Impor dari Flourish
            </button>
            <a href="{{ route('cms.grafik.insert', ['locale' => app()->getLocale()]) }}"
                class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                <x-cms.ikon nama="tambah" /> Buat grafik
            </a>
        </div>
    </div>

    {{-- Jendela impor dari Flourish --}}
    <div x-show="impor" x-transition.opacity x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-[2px]">
        <form wire:submit="imporFlourish" @click.outside="impor = false" @keydown.escape.window="impor = false"
            class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <div class="flex gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-600">
                    <x-cms.ikon nama="unduh" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Impor dari Flourish</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Data, judul, warna, dan pengaturan grafik Flourish disalin menjadi draf grafik baru yang bisa diubah di sini.
                    </p>
                </div>
            </div>

            <label class="mt-5 block text-xs font-medium text-gray-600">Tautan atau kode embed Flourish
                <input type="text" x-ref="tautan" wire:model="tautanFlourish"
                    placeholder="https://flo.uri.sh/visualisation/4242524/embed"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-xs placeholder:text-gray-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20">
            </label>
            @error('tautanFlourish')
                <p class="mt-2 flex items-start gap-1.5 text-sm text-red-600">
                    <x-cms.ikon nama="peringatan" class="mt-0.5 h-4 w-4" /> {{ $message }}
                </p>
            @enderror
            <p class="mt-2 text-xs text-gray-400">
                Grafiknya harus sudah dipublikasikan di Flourish. Saat ini yang didukung template grafik garis, batang, area, lingkaran, dan donat.
            </p>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="impor = false"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="imporFlourish"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="imporFlourish">Impor</span>
                    <span wire:loading wire:target="imporFlourish">Mengimpor...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="relative mt-6">
        <x-cms.ikon nama="cari" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari judul atau subjudul..."
            class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm shadow-xs placeholder:text-gray-400 focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20">
    </div>

    @if ($daftar->isEmpty() && !$search)
        {{-- Belum ada grafik sama sekali --}}
        <div class="mt-6 flex flex-col items-center rounded-xl border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-green-50 text-green-600">
                <x-cms.ikon nama="grafik" class="h-7 w-7" />
            </span>
            <h2 class="mt-4 text-base font-semibold text-gray-900">Belum ada grafik</h2>
            <p class="mt-1 max-w-sm text-sm text-gray-500">Mulai dari contoh data, lalu ganti dengan data sendiri: ketik, tempel dari Excel, atau unggah CSV.</p>
            <a href="{{ route('cms.grafik.insert', ['locale' => app()->getLocale()]) }}"
                class="mt-5 inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                <x-cms.ikon nama="tambah" /> Buat grafik pertama
            </a>
            <button type="button" @click="impor = true; $nextTick(() => $refs.tautan.focus())"
                class="mt-3 text-sm font-medium text-gray-500 underline underline-offset-2 hover:text-gray-800">
                atau impor dari Flourish
            </button>
        </div>
    @else
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @if (!$search && $page == 1)
                <a href="{{ route('cms.grafik.insert', ['locale' => app()->getLocale()]) }}"
                    class="group flex min-h-72 flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-gray-200 bg-white text-gray-500 transition hover:border-green-400 hover:bg-green-50/40 hover:text-green-700">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 transition group-hover:bg-green-100">
                        <x-cms.ikon nama="tambah" class="h-6 w-6" />
                    </span>
                    <span class="text-sm font-medium">Buat grafik baru</span>
                </a>
            @endif

            @forelse ($daftar as $item)
                <div wire:key="grafik-{{ $item->id }}-{{ $item->updated_at }}"
                    class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-gray-300 hover:shadow-md">

                    <a href="{{ route('cms.grafik.edit', ['locale' => app()->getLocale(), 'id' => $item->id]) }}" class="flex flex-1 flex-col">
                        <div class="relative h-36 border-b border-gray-100 bg-gray-50 px-5 pt-5 pb-3" wire:ignore>
                            <div class="relative h-full" x-data="grafikMini(@js($item->spek))">
                                <canvas x-ref="kanvas"></canvas>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="font-medium leading-snug text-gray-900 group-hover:text-green-700">{{ $item->judul }}</h2>
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $item->terbit ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $item->terbit ? 'bg-blue-500' : 'bg-gray-400' }}"></span>
                                    {{ $item->terbit ? 'Terbit' : 'Draf' }}
                                </span>
                            </div>
                            @if ($item->subjudul)
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $item->subjudul }}</p>
                            @endif

                            <p class="mt-auto pt-3 text-xs text-gray-400">
                                {{ $pilihanJenis[$item->jenis] ?? $item->jenis }}
                                · {{ count($item->data['baris'] ?? []) }} baris
                                · {{ \Illuminate\Support\Carbon::parse($item->updated_at)->format('d/m/Y') }}
                            </p>
                        </div>
                    </a>

                    <div class="flex items-center gap-1 border-t border-gray-100 px-2 py-1.5" x-data="{ open: false }">
                        <a href="{{ route('cms.grafik.edit', ['locale' => app()->getLocale(), 'id' => $item->id]) }}"
                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                            <x-cms.ikon nama="pensil" class="h-3.5 w-3.5" /> Ubah
                        </a>
                        @if ($item->terbit)
                            <a href="{{ route('grafik.tampil', ['id' => $item->id]) }}" target="_blank"
                                class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                                <x-cms.ikon nama="tautan" class="h-3.5 w-3.5" /> Lihat
                            </a>
                        @endif

                        <button type="button" wire:click="duplikat({{ $item->id }})" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                            <x-cms.ikon nama="salin" class="h-3.5 w-3.5" /> Duplikat
                        </button>

                        <button type="button" @click="open = true"
                            class="ml-auto inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">
                            <x-cms.ikon nama="hapus" class="h-3.5 w-3.5" /> Hapus
                        </button>

                        <div x-show="open" x-transition.opacity style="display:none"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-[2px]">
                            <div @click.outside="open = false" @keydown.escape.window="open = false"
                                class="w-full max-w-sm rounded-xl bg-white p-5 text-left shadow-xl">
                                <div class="flex gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                                        <x-cms.ikon nama="hapus" class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900">Hapus grafik?</h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            "{{ $item->judul }}" akan dihapus permanen. Embed grafik ini di halaman lain ikut berhenti tampil.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 flex justify-end gap-2">
                                    <button type="button" @click="open = false"
                                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Batal
                                    </button>
                                    <button type="button" wire:click="delete({{ $item->id }})" @click="open = false"
                                        class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                                        Ya, hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-full rounded-xl border border-gray-200 bg-white py-12 text-center text-sm text-gray-500">
                    Tidak ada grafik yang cocok dengan "{{ $search }}".
                </div>
            @endforelse
        </div>
    @endif

    <div class="mt-auto pt-8">
        @include('components.pagination', [
            'page' => $page,
            'lastPage' => $lastPage
        ])
    </div>

</div>
