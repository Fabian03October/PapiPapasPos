@props(['keyClass' => 'pin-key', 'backspaceId' => 'pin-backspace'])
@php
$keyBtn = "$keyClass h-14 rounded-lg bg-neutral-50 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700 text-lg font-medium text-neutral-900 dark:text-neutral-100 hover:bg-neutral-100 dark:hover:bg-neutral-700 active:bg-neutral-200 dark:active:bg-neutral-600";
@endphp
<div class="grid grid-cols-3 gap-2.5">
    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
        <button type="button" class="{{ $keyBtn }}">{{ $n }}</button>
    @endforeach
    <div></div>
    <button type="button" class="{{ $keyBtn }}">0</button>
    <button type="button" id="{{ $backspaceId }}" class="h-14 rounded-lg text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">⌫</button>
</div>
