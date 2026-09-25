@props(['variant' => 'primary', 'type' => 'button'])
@php
$variants = [
    'primary' => 'bg-primary-600 text-white hover:bg-primary-700',
    'secondary' => 'bg-white dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700',
    'danger' => 'bg-red-600 text-white hover:bg-red-700',
];
$variantClass = $variants[$variant] ?? $variants['primary'];
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => "h-12 w-full rounded-lg text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed $variantClass"]) }}>
    {{ $slot }}
</button>
