@props(['label'])

<label class="relative block">
    <input placeholder=" " {{ $attributes->merge(['class' => 'peer m3-field']) }}>
    <span class="m3-float">{{ $label }}</span>
</label>
