<?php

namespace App\Livewire\Cms;

use Livewire\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PageAbout extends Component
{
    public $aboutId;

    public $title_id;
    public $title_en;
    public $content_id;
    public $content_en;

    public $diperbarui;

    public function mount()
    {
        $about = DB::table('about')->first();

        if (!$about) {
            $this->aboutId = DB::table('about')->insertGetId([
                'title_id' => '',
                'title_en' => '',
                'content_id' => '',
                'content_en' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $about = DB::table('about')->where('id', $this->aboutId)->first();
        }

        $this->aboutId = $about->id;
        $this->title_id = $about->title_id;
        $this->title_en = $about->title_en;
        $this->content_id = $about->content_id;
        $this->content_en = $about->content_en;
        $this->diperbarui = $about->updated_at;
    }

    public function save()
    {
        $this->validate(
            [
                'title_id' => 'required|string|max:255',
                'title_en' => 'nullable|string|max:255',
                'content_id' => 'nullable|string',
                'content_en' => 'nullable|string',
            ],
            [
                'title_id.required' => 'Judul (Bahasa Indonesia) wajib diisi.',
                'title_id.max' => 'Judul (Bahasa Indonesia) maksimal 255 karakter.',
                'title_en.max' => 'Judul (English) maksimal 255 karakter.',
            ]
        );

        // Kolom konten bertipe TEXT (maks. 65.535 byte); gambar yang ditempel langsung ke editor
        // ikut tersimpan sebagai teks base64 dan cepat melewati batas ini
        foreach (['content_id' => 'Bahasa Indonesia', 'content_en' => 'English'] as $kolom => $bahasa) {
            if (strlen((string) $this->$kolom) > 65535) {
                $this->addError($kolom, "Konten ({$bahasa}) terlalu panjang. Kalau ada gambar yang ditempel langsung, masukkan lewat tombol gambar di editor.");
                return;
            }
        }

        $this->diperbarui = now()->toDateTimeString();

        DB::table('about')
            ->where('id', $this->aboutId)
            ->update([
                'title_id' => $this->title_id,
                'title_en' => $this->title_en,
                'content_id' => $this->content_id,
                'content_en' => $this->content_en,
                'updated_at' => $this->diperbarui,
            ]);

        $this->dispatch('about-tersimpan');
    }

    public function render()
    {
        return view('livewire.cms.page-about', [
            'waktuSimpan' => $this->diperbarui
                ? Carbon::parse($this->diperbarui)->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') . ' WIB'
                : null,
        ]);
    }
}
