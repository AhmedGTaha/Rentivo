<?php
/**
 * Icon.
 *
 * A small inline SVG set (Lucide-style geometry, drawn here rather than
 * pulled from a CDN so the app has no external asset dependency).
 *
 * @var string $name
 * @var string $class
 * @var int    $size
 * @var string $label  Accessible name; when empty the icon is decorative.
 */

$name = $name ?? 'circle';
$class = $class ?? '';
$size = $size ?? 20;
$label = $label ?? '';

$paths = [
    'car'        => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/><path d="M9.5 17h5"/>',
    'search'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'filter'     => '<path d="M3 5h18M6 12h12M10 19h4"/>',
    'heart'      => '<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1l8.8 8.8 8.8-8.8a5 5 0 0 0 0-7.1Z"/>',
    'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'users'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 5.6M17.5 20a6.5 6.5 0 0 0-1.6-4.3"/>',
    'calendar'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'map-pin'    => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    'building'   => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
    'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
    'bell'       => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
    'file-text'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
    'chart'      => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'activity'   => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
    'check'      => '<path d="m5 12 5 5L20 7"/>',
    'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
    'x'          => '<path d="M18 6 6 18M6 6l12 12"/>',
    'x-circle'   => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
    'alert'      => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
    'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
    'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
    'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
    'arrow-right'   => '<path d="M4 12h16M14 6l6 6-6 6"/>',
    'arrow-left'    => '<path d="M20 12H4M10 18l-6-6 6-6"/>',
    'plus'       => '<path d="M12 5v14M5 12h14"/>',
    'minus'      => '<path d="M5 12h14"/>',
    'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'upload'     => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
    'image'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 2-2 5 5"/>',
    'trash'      => '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 13h10l1-13"/>',
    'edit'       => '<path d="M4 20h4l10-10-4-4L4 16Z"/><path d="m14 6 4 4"/>',
    'star'       => '<path d="m12 3 2.7 5.7 6.3.9-4.5 4.4 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.6l6.3-.9Z"/>',
    'shield'     => '<path d="M12 3 5 6v5.5c0 4.5 3 8 7 9.5 4-1.5 7-5 7-9.5V6Z"/><path d="m9 12 2 2 4-4"/>',
    'key'        => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8 2 2-2 2 2 2-2 2-2-2-2 2"/>',
    'log-out'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h10"/>',
    'external'   => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
    'inbox'      => '<path d="M3 12h5l1.5 3h5L16 12h5"/><path d="M4.5 6h15l1.5 6v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6Z"/>',
    'fuel'       => '<path d="M4 20V5a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v15"/><path d="M3 20h11"/><path d="M13 10h3a2 2 0 0 1 2 2v4a1.5 1.5 0 0 0 3 0V8l-3-3"/>',
    'gauge'      => '<path d="M12 14 16 9"/><path d="M4.5 18a9 9 0 1 1 15 0"/><circle cx="12" cy="14" r="1.5"/>',
    'seat'       => '<path d="M6 4h3a3 3 0 0 1 3 3v6H8a2 2 0 0 1-2-2Z"/><path d="M6 17h10a3 3 0 0 0 3-3v-1"/><path d="M4 21h14"/>',
    'door'       => '<path d="M4 21V4a1 1 0 0 1 1-1h11a1 1 0 0 1 1 1v17"/><path d="M3 21h18"/><circle cx="13.5" cy="12" r="1"/>',
    'gear-shift' => '<path d="M6 4v16M12 4v16M18 4v10"/><path d="M6 8h12"/>',
    'palette'    => '<path d="M12 3a9 9 0 1 0 0 18h1.5a2 2 0 0 0 1.4-3.4 2 2 0 0 1 1.4-3.4H18a3 3 0 0 0 3-3 9 9 0 0 0-9-8.2Z"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16" cy="10" r="1"/>',
    'credit-card' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/>',
    'phone'      => '<path d="M6 3h3l2 5-2.5 1.5a12 12 0 0 0 5 5L15 12l5 2v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/>',
    'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
    'circle'     => '<circle cx="12" cy="12" r="9"/>',
];

$path = $paths[$name] ?? $paths['circle'];
$decorative = $label === '';
?>
<svg class="<?= e($class) ?>" width="<?= (int) $size ?>" height="<?= (int) $size ?>"
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
     stroke-linecap="round" stroke-linejoin="round"
     <?= $decorative ? 'aria-hidden="true" focusable="false"' : 'role="img" aria-label="' . e($label) . '"' ?>>
    <?= $path /* Static markup from the table above; never user input. */ ?>
</svg>
