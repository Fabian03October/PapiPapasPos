@props(['id'])
<div id="{{ $id }}" {{ $attributes->merge(['class' => 'flex justify-center gap-3']) }}>
    @for ($i = 0; $i < 4; $i++)
        <div class="w-3.5 h-3.5 rounded-full border-2 border-neutral-300 dark:border-neutral-600 dot"></div>
    @endfor
</div>
