@props(['padding' => 'p-5'])
<div {{ $attributes->merge(['class' => "bg-white dark:bg-neutral-900 rounded-2xl $padding"]) }}>
    {{ $slot }}
</div>
