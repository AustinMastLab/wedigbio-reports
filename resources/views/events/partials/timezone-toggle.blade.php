@php
    $containerClass = $containerClass ?? 'flex items-center gap-2';
    $labelTextClass = $labelTextClass ?? 'text-xs text-slate-600 dark:text-gray-300';
    $utcClass = $utcClass ?? 'font-semibold text-amber-700 dark:text-amber-300';
    $separatorClass = $separatorClass ?? 'text-slate-500 dark:text-gray-400';
    $localClass = $localClass ?? 'text-slate-500 dark:text-gray-400';
    $labelClass = $labelClass ?? 'relative inline-flex cursor-pointer items-center';
    $inputClass = $inputClass ?? 'peer sr-only';
    $trackClass = $trackClass ?? 'h-6 w-11 rounded-full bg-slate-400 transition-colors peer peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-amber-500/40 peer-checked:bg-amber-600 dark:bg-gray-700';
    $thumbClass = $thumbClass ?? 'absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform peer-checked:translate-x-5';
    $showSeparator = $showSeparator ?? false;
@endphp

<div class="{{ $containerClass }} {{ $labelTextClass }}">
    <span id="{{ $utcId }}" class="{{ $utcClass }}">UTC</span>
    <label class="{{ $labelClass }}" aria-label="{{ $ariaLabel ?? 'Toggle chart timezone' }}">
        <input id="{{ $toggleId }}" type="checkbox" class="{{ $inputClass }}" />
        <span class="{{ $trackClass }}"></span>
        <span class="{{ $thumbClass }}"></span>
    </label>
    @if ($showSeparator)
        <span class="{{ $separatorClass }}"> / </span>
    @endif
    <span id="{{ $localId }}" class="{{ $localClass }}">Local</span>
</div>

