<div class="mx-auto max-w-3xl px-6 py-10 sm:px-10">

    @if($about)

        <header class="border-b-[3px] border-black pb-8">
            <div class="text-black text-xs mb-3">
                {{ app()->getLocale() === 'id' ? 'Tentang' : 'About' }}
            </div>
            <h1 class="text-3xl sm:text-4xl font-bold leading-tight text-black">
                {{ $judul }}
            </h1>
        </header>

        {{-- Isi dari editor CMS; subjudul (h2) diberi garis tebal seperti judul bagian di beranda --}}
        <div class="prose prose-neutral mt-8 max-w-none text-black/80 leading-relaxed
            prose-headings:text-black prose-strong:text-black prose-a:text-green-700
            prose-h2:mt-12 prose-h2:mb-4 prose-h2:border-t-[3px] prose-h2:border-black prose-h2:pt-4 prose-h2:text-xl
            prose-li:my-2 prose-ol:marker:font-semibold prose-ol:marker:text-black">
            {!! $konten !!}
        </div>

    @else
        <div class="text-gray-500">
            Data about belum tersedia.
        </div>
    @endif

</div>
