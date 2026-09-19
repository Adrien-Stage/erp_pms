@props([
    // 'lockup' : la marque et le mot « Wetchah ERP ».
    // 'mark'   : la marque seule, pour les espaces étroits — barre latérale,
    //            en-tête réduit, pastille.
    'variant' => 'lockup',
    'alt' => 'Wetchah ERP',
])

@php
    $fichier = $variant === 'mark' ? 'images/logo-erp-mark.png' : 'images/logo-erp.png';
@endphp

<img src="{{ asset($fichier) }}"
     alt="{{ $alt }}"
     {{ $attributes->merge(['class' => 'object-contain select-none']) }}
     draggable="false">
