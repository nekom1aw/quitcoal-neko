{{-- Bagian panel pengaturan editor grafik yang bisa dibuka-tutup (state di terbuka.{kunci}) --}}
@props(['kunci', 'judul', 'ikon'])

<section {{ $attributes }}>
    <button type="button" @click="terbuka.{{ $kunci }} = !terbuka.{{ $kunci }}"
        class="group w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-100 text-gray-500 group-hover:text-gray-700"
            :class="terbuka.{{ $kunci }} && 'bg-green-50! text-green-700!'">
            <x-cms.ikon :nama="$ikon" />
        </span>
        <span class="flex-1 text-sm font-medium text-gray-800">{{ $judul }}</span>
        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="terbuka.{{ $kunci }} && 'rotate-180'" fill="none"
            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="terbuka.{{ $kunci }}" x-collapse.duration.200ms x-cloak class="px-4 pb-4 pt-1 space-y-3">
        {{ $slot }}
    </div>
</section>
