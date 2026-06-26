@props(['label', 'value', 'subtitle' => '', 'color' => 'emerald'])

<div class="bg-white rounded-xl p-5 shadow-sm">
    <div class="flex items-start justify-between mb-3">
        <p class="text-xs font-semibold uppercase tracking-widest text-[#a09080]">{{ $label }}</p>
        @isset($icon)
        <div class="w-8 h-8 rounded-lg bg-{{ $color }}-50 flex items-center justify-center">
            {{ $icon }}
        </div>
        @endisset
    </div>
    <p class="text-3xl font-heading font-semibold text-primary">{{ $value }}</p>
    @if($subtitle)
        <p class="text-xs text-[#a09080] mt-1">{{ $subtitle }}</p>
    @endif
    {{ $slot }}
</div>
