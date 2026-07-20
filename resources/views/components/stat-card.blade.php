@props([
    'title',
    'value',
    'subtitle',
    'icon' => 'chart-bar',
    'gradient' => 'coral',
])

<div {{ $attributes->merge(['class' => 'stat-card card-' . $gradient]) }}>
    <div class="card-title">
        <span>{{ $title }}</span>
        <i class="ph ph-{{ $icon }}"></i>
    </div>
    <div class="card-value">{{ $value }}</div>
    <div class="card-subtitle">{{ $subtitle }}</div>
</div>
