<?php

namespace App\Livewire\Cms;

use App\Support\ImporFlourish;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

// Daftar dataset di menu Data & Grafik
class PageIndexGrafik extends Component
{
    public $search = '';
    public $page = 1;
    // 8 + kartu "Buat grafik baru" = 3 baris penuh di halaman pertama
    public $perPage = 8;

    public function updatedSearch()
    {
        $this->page = 1;
    }

    public function nextPage()
    {
        $this->page++;
    }

    public function prevPage()
    {
        if ($this->page > 1) {
            $this->page--;
        }
    }

    public function delete($id)
    {
        DB::table('grafik')->where('id', $id)->delete();
    }

    // Salinan grafik sebagai draf baru, lalu langsung dibuka di editor
    public function duplikat($id)
    {
        $asal = DB::table('grafik')->where('id', $id)->first();
        if (!$asal) {
            return;
        }

        $baru = DB::table('grafik')->insertGetId([
            'judul' => Str::limit($asal->judul, 240, '') . ' (salinan)',
            'subjudul' => $asal->subjudul,
            'jenis' => $asal->jenis,
            'data' => $asal->data,
            'pengaturan' => $asal->pengaturan,
            'terbit' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.grafik.edit', ['locale' => app()->getLocale(), 'id' => $baru]);
    }

    // Tautan atau kode embed Flourish yang ditempel di jendela "Impor dari Flourish"
    public $tautanFlourish = '';

    // Grafik Flourish dijadikan draf baru, lalu langsung dibuka di editor untuk diperiksa
    public function imporFlourish()
    {
        $this->validate(
            ['tautanFlourish' => 'required|string|max:2000'],
            ['tautanFlourish.required' => 'Tempel tautan atau kode embed Flourish dulu.']
        );

        try {
            $isi = ImporFlourish::dariTautan($this->tautanFlourish);
        } catch (RuntimeException $e) {
            $this->addError('tautanFlourish', $e->getMessage());
            return;
        }

        $simpan = PageFormGrafik::rapikan($isi + ['terbit' => false]);
        if (is_string($simpan)) {
            $this->addError('tautanFlourish', $simpan);
            return;
        }
        $id = DB::table('grafik')->insertGetId($simpan + ['created_at' => now()]);

        return redirect()->route('cms.grafik.edit', ['locale' => app()->getLocale(), 'id' => $id]);
    }

    public function render()
    {
        $query = DB::table('grafik')
            ->when($this->search, function ($q) {
                $q->where('judul', 'like', '%' . $this->search . '%')
                    ->orWhere('subjudul', 'like', '%' . $this->search . '%');
            });

        $total = $query->count();
        $lastPage = (int) ceil($total / $this->perPage);

        $daftar = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get();

        // Bahan grafik kecil di kartunya (format sama dengan editor dan halaman publik)
        foreach ($daftar as $item) {
            $item->data = json_decode($item->data, true);
            $item->spek = [
                'jenis' => $item->jenis,
                'data' => $item->data,
                'pengaturan' => PageFormGrafik::bersihkanPengaturan(json_decode($item->pengaturan, true)),
            ];
        }

        return view('livewire.cms.page-index-grafik', [
            'daftar' => $daftar,
            'lastPage' => $lastPage,
            'pilihanJenis' => PageFormGrafik::JENIS,
        ]);
    }
}
