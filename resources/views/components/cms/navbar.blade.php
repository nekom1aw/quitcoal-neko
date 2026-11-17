@php
    $akun = session('account_id')
        ? DB::table('accounts')->where('id', session('account_id'))->first(['nama', 'email', 'role'])
        : null;

    // [label, nama route] atau [label, [[label, nama route], ...]] untuk menu dropdown
    $menu = [
        ['Dashboard', 'cms.index'],
        ['About', 'cms.about'],
        ['Background', [
            ['Coalcrowd', 'cms.background.coalcrowd.index'],
            ['Coal Permit', 'cms.background.coal-permit.index'],
            ['Regulation', 'cms.background.regulation.index'],
            ['Benchmark Price', 'cms.background.benchmark-price.index'],
            ['Coal Production', 'cms.background.coal-production.index'],
            ['Coal Consumption', 'cms.background.coal-consumption.index'],
            ['Mining & Deforestation', 'cms.background.mining-and-deforestation.index'],
        ]],
        ['Coal-Ruption', [
            ['Cases', 'cms.coal-ruption.cases.index'],
        ]],
        ['Action', 'cms.action.index'],
        ['Data', [
            ['Resource', 'cms.data.resource.index'],
            ['Check PLTU', 'cms.data.check-pltu.index'],
        ]],
        ['Data & Grafik', 'cms.grafik.index'],
        ['Account', 'cms.account.index'],
        ['Activity', 'cms.activity.index'],
        ['Ngopini', 'cms.ngopini.index'],
    ];

    // Menu aktif juga di halaman turunannya (insert, edit, ...): cms.ngopini.index -> cms.ngopini.*
    $aktif = function ($route) use (&$aktif) {
        if (is_array($route)) {
            return collect($route)->contains(fn ($sub) => $aktif($sub[1]));
        }

        return request()->routeIs($route !== 'cms.index' && str_ends_with($route, '.index') ? substr($route, 0, -5) . '*' : $route);
    };
@endphp

<nav class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b border-gray-200" x-data="{ mobileOpen: false }">
    <div class="container mx-auto px-6 h-16 flex items-center gap-6">

        <a href="{{ route('cms.index') }}" class="flex items-center gap-2 shrink-0">
            <span class="w-8 h-8 rounded-lg bg-green-600 text-white grid place-items-center text-sm font-semibold">QC</span>
            <span class="font-semibold text-gray-900">QuitCoal</span>
            <span class="text-[11px] font-medium uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">CMS</span>
        </a>

        {{-- Menu desktop --}}
        <ul class="hidden xl:flex flex-1 items-center justify-center gap-1 text-sm whitespace-nowrap">
            @foreach ($menu as [$label, $tujuan])
                @if (is_array($tujuan))
                    <li class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                        <button type="button" @click="open = !open"
                            class="px-3 py-2 rounded-md flex items-center gap-1 transition-colors {{ $aktif($tujuan) ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                            {{ $label }}
                            <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div x-show="open" x-transition.origin.top x-cloak
                            class="absolute left-0 mt-2 w-60 py-1 rounded-lg bg-white border border-gray-200 shadow-lg z-50">
                            @foreach ($tujuan as [$sub, $route])
                                <a href="{{ route($route) }}"
                                    class="block px-4 py-2 {{ $aktif($route) ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                    {{ $sub }}
                                </a>
                            @endforeach
                        </div>
                    </li>
                @else
                    <li>
                        <a href="{{ route($tujuan) }}"
                            class="block px-3 py-2 rounded-md transition-colors {{ $aktif($tujuan) ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}">
                            {{ $label }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>

        <div class="ml-auto xl:ml-0 flex items-center gap-2">

            {{-- Profil --}}
            <div class="relative" x-data="{ openProfile: false }" @click.outside="openProfile = false" @keydown.escape="openProfile = false">
                <button type="button" @click="openProfile = !openProfile"
                    class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 transition-colors">
                    <img src="{{ asset('img/icon-admin.png') }}" alt="" class="w-8 h-8 rounded-full object-contain bg-gray-100 ring-1 ring-gray-200">
                    @if ($akun)
                        {{-- Di 1280-1535px menu desktop sudah memenuhi navbar, jadi cukup avatar --}}
                        <span class="hidden sm:block xl:hidden 2xl:block text-left leading-tight">
                            <span class="block text-sm font-medium text-gray-900">{{ $akun->nama }}</span>
                            <span class="block text-xs text-gray-500">{{ Str::headline($akun->role) }}</span>
                        </span>
                    @endif
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="openProfile && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="openProfile" x-transition.origin.top.right x-cloak
                    class="absolute right-0 mt-2 w-60 py-1 rounded-lg bg-white border border-gray-200 shadow-lg text-sm z-50">
                    @if ($akun)
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="font-medium text-gray-900 truncate">{{ $akun->nama }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $akun->email }}</p>
                        </div>
                    @endif

                    <a href="{{ url(app()->getLocale()) }}" target="_blank" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">
                        Lihat situs
                    </a>

                    <form action="{{ route('cms.logout', ['locale' => app()->getLocale()]) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>

            {{-- Tombol menu layar kecil --}}
            <button type="button" @click="mobileOpen = !mobileOpen" class="xl:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100" aria-label="Menu">
                <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Menu layar kecil --}}
    <div x-show="mobileOpen" x-transition x-cloak class="xl:hidden border-t border-gray-200 bg-white max-h-[calc(100vh-4rem)] overflow-y-auto">
        <ul class="container mx-auto px-6 py-3 text-sm space-y-1">
            @foreach ($menu as [$label, $tujuan])
                @if (is_array($tujuan))
                    <li class="pt-2">
                        <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</p>
                        @foreach ($tujuan as [$sub, $route])
                            <a href="{{ route($route) }}"
                                class="block px-3 py-2 rounded-md {{ $aktif($route) ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                                {{ $sub }}
                            </a>
                        @endforeach
                    </li>
                @else
                    <li>
                        <a href="{{ route($tujuan) }}"
                            class="block px-3 py-2 rounded-md {{ $aktif($tujuan) ? 'bg-green-50 text-green-700 font-medium' : 'text-gray-700 hover:bg-gray-100' }}">
                            {{ $label }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</nav>
