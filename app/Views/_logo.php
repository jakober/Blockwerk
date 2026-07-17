<?php
/**
 * Blockwerk-Logo als Inline-SVG: drei Blöcke in der aktuellen Textfarbe,
 * der vierte (Akzent) wird gerade "eingesetzt" – Drag & Drop.
 * Größe über $logoSize (px) steuerbar.
 */
$logoSize = (int) ($logoSize ?? 28);
?>
<svg class="bw-logo" width="<?= $logoSize ?>" height="<?= $logoSize ?>" viewBox="0 0 48 48" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
  <rect x="4" y="4" width="18" height="18" rx="4.5" fill="currentColor"/>
  <rect x="4" y="26" width="18" height="18" rx="4.5" fill="currentColor"/>
  <rect x="26" y="26" width="18" height="18" rx="4.5" fill="currentColor"/>
  <rect x="28" y="2" width="18" height="18" rx="4.5" fill="#f59e0b"/>
</svg>
