{{-- Carita del score: $face = happy | neutral | sad --}}
@php
    $colores = ['happy' => '#16a34a', 'neutral' => '#f59e0b', 'sad' => '#dc2626'];
    $c = $colores[$face] ?? '#6b7280';
@endphp
<svg viewBox="0 0 40 40" width="100%" height="100%" aria-hidden="true">
    <circle cx="20" cy="20" r="18" fill="{{ $c }}" fill-opacity=".14" stroke="{{ $c }}" stroke-width="2"/>
    <circle cx="14" cy="16.5" r="2.2" fill="{{ $c }}"/>
    <circle cx="26" cy="16.5" r="2.2" fill="{{ $c }}"/>
    @if ($face === 'happy')
        <path d="M12 24c2.4 3.4 5 5 8 5s5.6-1.6 8-5" fill="none" stroke="{{ $c }}" stroke-width="2.6" stroke-linecap="round"/>
    @elseif ($face === 'neutral')
        <path d="M13 26h14" fill="none" stroke="{{ $c }}" stroke-width="2.6" stroke-linecap="round"/>
    @else
        <path d="M12 29c2.4-3.4 5-5 8-5s5.6 1.6 8 5" fill="none" stroke="{{ $c }}" stroke-width="2.6" stroke-linecap="round"/>
    @endif
</svg>
