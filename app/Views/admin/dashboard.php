<div class="cards">
    <a class="card stat" href="<?= e(url('/admin/pages')) ?>">
        <div class="stat-value"><?= (int) $counts['pages'] ?></div>
        <div class="stat-label">Seiten</div>
    </a>
    <a class="card stat" href="<?= e(url('/admin/layouts')) ?>">
        <div class="stat-value"><?= (int) $counts['layouts'] ?></div>
        <div class="stat-label">Layouts</div>
    </a>
    <a class="card stat" href="<?= e(url('/admin/templates')) ?>">
        <div class="stat-value"><?= (int) $counts['templates'] ?></div>
        <div class="stat-label">Templates</div>
    </a>
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
