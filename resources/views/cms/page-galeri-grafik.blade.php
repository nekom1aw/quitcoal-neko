@extends('layouts.admin')
@section('content')
    @php
        // [kunci => [kelompok, nama, keterangan, jenis, data, pengaturan]] dikelompokkan per kelompok
        $kelompok = collect($contoh)->groupBy(fn ($c) => $c[0], preserveKeys: true);
        $urlDaftar = route('cms.grafik.index', ['locale' => app()->getLocale()]);
    @endphp

    <div class="mx-auto max-w-6xl py-12">
        <a href="{{ $urlDaftar }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900">
            <x-cms.ikon nama="kembali" /> Data & Grafik
        </a>

        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Pilih jenis grafik</h1>
                <p class="mt-1 text-sm text-gray-500">Setiap template sudah berisi contoh data. Pilih yang paling mirip, lalu ganti datanya di tab Data.</p>
            </div>
            <a href="{{ $urlDaftar }}?impor=1"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50">
                <x-cms.ikon nama="unduh" /> Impor dari Flourish
            </a>
        </div>

        @foreach ($kelompok as $namaKelompok => $daftar)
            <h2 class="mt-10 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $namaKelompok }}</h2>
            <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($daftar as $kunci => [$_, $nama, $keterangan, $jenis, $data, $pengaturan])
                    <a href="{{ route('cms.grafik.insert', ['locale' => app()->getLocale(), 'template' => $kunci]) }}"
                        class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-green-500 hover:shadow-md">
                        <div class="h-40 border-b border-gray-100 bg-gray-50 px-5 pt-5 pb-3">
                            <div class="relative h-full"
                                x-data="grafikMini(@js(['jenis' => $jenis, 'data' => $data, 'pengaturan' => \App\Livewire\Cms\PageFormGrafik::bersihkanPengaturan(array_merge(\App\Livewire\Cms\PageFormGrafik::PENGATURAN, $pengaturan))]))">
                                <canvas x-ref="kanvas"></canvas>
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium text-gray-900 group-hover:text-green-700">{{ $nama }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $keterangan }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    @vite('resources/js/grafik.js')
@endpush
