<!DOCTYPE html>
<html lang="{{ $p['bahasa'] }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $grafik->judul }}</title>
    @vite(['resources/css/app.css', 'resources/js/grafik.js'])
</head>

<body class="bg-white text-gray-800">
    <figure class="max-w-5xl mx-auto p-4 sm:p-6">
        <header class="{{ ['tengah' => 'text-center', 'kanan' => 'text-right', 'rata' => 'text-justify [text-align-last:justify]'][$p['rataJudul']] ?? '' }}">
            <h1 class="text-xl font-semibold text-gray-900">{{ $grafik->judul }}</h1>
            @if ($grafik->subjudul)
                <p class="text-sm text-gray-500 mt-1">{{ $grafik->subjudul }}</p>
            @endif
        </header>

        <div class="relative mt-4" style="height: {{ (int) $p['tinggi'] }}px">
            <canvas id="grafik" role="img" aria-label="{{ $grafik->judul }}"></canvas>
        </div>

        @if ($p['sumber'] || $p['catatan'])
            <figcaption class="mt-4 text-xs text-gray-500">
                @if ($p['sumber'])
                    {{ $p['bahasa'] === 'en' ? 'Source:' : 'Sumber:' }}
                    @if ($p['sumberUrl'])
                        <a href="{{ $p['sumberUrl'] }}" target="_blank" rel="noopener" class="underline hover:text-gray-800">{{ $p['sumber'] }}</a>
                    @else
                        {{ $p['sumber'] }}
                    @endif
                @endif
                @if ($p['sumber'] && $p['catatan'])
                    &middot;
                @endif
                {{ $p['catatan'] }}
            </figcaption>
        @endif
    </figure>

    <script>
        // grafik.js dimuat sebagai module, jadi baru tersedia menjelang DOMContentLoaded
        document.addEventListener("DOMContentLoaded", () => {
            GrafikQC.pasang(document.getElementById("grafik"), @json($spek));
        });

        // Saat di-embed, tinggi isi dikirim ke halaman induk supaya iframe-nya bisa menyesuaikan
        // (misalnya judul yang turun ke baris kedua di layar sempit)
        if (window.parent !== window) {
            const isi = document.querySelector("figure");
            new ResizeObserver(() => {
                window.parent.postMessage({ grafikQC: { tinggi: Math.ceil(isi.getBoundingClientRect().height) } }, "*");
            }).observe(isi);
        }
    </script>
</body>

</html>
