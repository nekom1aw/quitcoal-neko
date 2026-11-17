<?php

namespace App\Livewire\User;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class PageAbout extends Component
{
    public $about;

    public function mount()
    {
        $this->about = DB::table('about')->first();
    }

    // Isi sesuai bahasa halaman; kolom English yang masih kosong memakai versi Indonesia
    private function isi(string $kolom): string
    {
        $id = (string) ($this->about->{$kolom . '_id'} ?? '');
        if (app()->getLocale() === 'id') {
            return $id;
        }

        $en = (string) ($this->about->{$kolom . '_en'} ?? '');
        $kosong = $kolom === 'content'
            ? trim(html_entity_decode(strip_tags($en), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '' && !preg_match('/<(img|iframe|video)/i', $en)
            : trim($en) === '';

        return $kosong ? $id : $en;
    }

    public function render()
    {
        return view('livewire.user.page-about', $this->about ? [
            'judul' => $this->isi('title'),
            'konten' => $this->isi('content'),
        ] : []);
    }
}
