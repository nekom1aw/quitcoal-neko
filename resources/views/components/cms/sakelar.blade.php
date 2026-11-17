{{-- Sakelar on/off untuk pengaturan boolean Alpine (model = ekspresi Alpine, mis. pengaturan.halus) --}}
@props(['model', 'label'])

<label class="flex items-center justify-between gap-3 text-sm text-gray-700 cursor-pointer">
    <span>{{ $label }}</span>
    <input type="checkbox" x-model="{{ $model }}" class="sr-only peer">
    <span class="relative w-9 h-5 shrink-0 rounded-full bg-gray-300 transition-colors peer-checked:bg-green-600 after:absolute after:top-0.5 after:left-0.5 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4"></span>
</label>
