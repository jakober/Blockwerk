<?php
$stats = [
    ['/admin/pages', $counts['pages'], 'Seiten'],
    ['/admin/news', $counts['news'], 'News'],
    ['/admin/events', $counts['events'], 'Events'],
    ['/admin/media', $counts['media'], 'Medien'],
];
if (\Core\Auth::isAdmin()) {
    $stats[] = ['/admin/layouts', $counts['layouts'], 'Layouts'];
    $stats[] = ['/admin/templates', $counts['templates'], 'Templates'];
}
?>
<div class="cards">
    <?php foreach ($stats as [$href, $count, $label]): ?>
        <a class="card stat" href="<?= e(url($href)) ?>">
            <div class="stat-value"><?= (int) $count ?></div>
            <div class="stat-label"><?= e($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Schnellstart</h2>
    <ol class="quickstart">
        <li><strong>Layout</strong> anpassen oder neu anlegen – das HTML-Grundgerüst mit Platzhaltern wie <code>{{content}}</code> und <code>{{template:main-menu}}</code>.</li>
        <li><strong>Templates</strong> pflegen – wiederverwendbare Bausteine (z.&nbsp;B. das Hauptmenü mit <code>{{menu}}</code>), die in Layouts eingebettet werden.</li>
        <li><strong>Seite</strong> anlegen, festlegen ob sie im Menü erscheint, und im <strong>Inhalts-Editor</strong> Zeilen mit flexiblen Spalten füllen – per Drag &amp; Drop.</li>
    </ol>
    <a class="btn btn-primary" href="<?= e(url('/admin/pages/new')) ?>">+ Neue Seite</a>
</div>
