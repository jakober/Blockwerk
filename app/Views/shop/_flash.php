<?php $f = flash(); if ($f): ?>
<div class="shop-flash shop-flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endif; ?>
