@props(['id', 'maxWidth' => 'md', 'bodyClass' => 'px-5 py-4'])
@php
$maxWidthClass = match ($maxWidth) {
    'sm' => 'max-w-sm lg:max-w-md',
    'md' => 'max-w-md lg:max-w-lg xl:max-w-xl',
    'lg' => 'max-w-lg lg:max-w-xl xl:max-w-2xl',
    default => 'max-w-md lg:max-w-lg xl:max-w-xl',
};
@endphp
<div id="{{ $id }}" {{ $attributes->merge(['class' => 'fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4']) }}>
    <div class="bg-white dark:bg-neutral-900 rounded-2xl w-full {{ $maxWidthClass }} max-h-[90vh] flex flex-col overflow-hidden">

        @isset($header)
        <div class="flex justify-between items-start gap-3 p-5 pb-4 shrink-0">
            {{ $header }}
        </div>
        @endisset

        <div class="{{ $bodyClass }} overflow-y-auto flex-1 min-h-0">
            {{ $slot }}
        </div>

        @isset($footer)
        <div class="p-5 pt-4 shrink-0 border-t border-neutral-200 dark:border-neutral-800">
            {{ $footer }}
        </div>
        @endisset

    </div>
</div>
