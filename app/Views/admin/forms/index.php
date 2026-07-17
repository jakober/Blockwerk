<div class="card">
    <?php if (empty($entries)): ?>
        <p class="muted">Noch keine Formular-Einsendungen. Sobald jemand ein Kontaktformular auf deiner Website abschickt, erscheint die Nachricht hier – zusätzlich zur E-Mail.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Eingegangen</th><th>Seite</th><th>Nachricht</th><th class="actions-col">Aktionen</th></tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php $data = json_decode((string) $entry['data'], true) ?: []; ?>
                    <tr>
                        <td class="muted" style="white-space:nowrap">
                            <?= e(format_date_de($entry['created_at'], true)) ?>
                            <?= (int) $entry['is_read'] === 0 ? '<span class="badge badge-amber">Neu</span>' : '' ?>
                        </td>
                        <td class="muted"><?= e($entry['page_title'] ?? '–') ?></td>
                        <td>
                            <details class="entry-details">
                                <summary>
                                    <strong><?= e($data['Name'] ?? $data['E-Mail'] ?? 'Einsendung') ?></strong>
                                    <span class="muted small"> – <?= e(mb_substr($data['Nachricht'] ?? '', 0, 60)) ?><?= mb_strlen($data['Nachricht'] ?? '') > 60 ? '…' : '' ?></span>
                                </summary>
                                <dl class="entry-data">
                                    <?php foreach ($data as $key => $value): ?>
                                        <dt><?= e((string) $key) ?></dt>
                                        <dd><?= nl2br(e((string) $value)) ?></dd>
                                    <?php endforeach; ?>
                                </dl>
                                <?php if (!empty($data['E-Mail'])): ?>
                                    <a class="btn btn-small" href="mailto:<?= e($data['E-Mail']) ?>">Antworten per E-Mail</a>
                                <?php endif; ?>
                            </details>
                        </td>
                        <td class="actions-col">
                            <form method="post" action="<?= e(url('/admin/forms/' . $entry['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Einsendung wirklich löschen?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-small btn-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
