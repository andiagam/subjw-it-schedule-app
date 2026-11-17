@props(['name', 'filled' => false])

<span {{ $attributes->merge(['class' => 'material-symbols-rounded'.($filled ? ' icon-fill' : '')]) }} aria-hidden="true">{{ $name }}</span>
