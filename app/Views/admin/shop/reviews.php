<div class="card">
    <?php if (empty($reviews)): ?>
        <p class="muted">Noch keine Bewertungen.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Produkt</th><th>Bewertung</th><th>Von</th><th>Text</th><th>Status</th><th class="actions-col">Aktionen</th></tr></thead>
            <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td data-label="Produkt"><?= e($products[(int) $r['product_id']] ?? 'Gelöschtes Produkt') ?></td>
                        <td data-label="Bewertung"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></td>
                        <td class="muted" data-label="Von"><?= e($r['name']) ?><br><span class="small"><?= e(format_date_de($r['created_at'])) ?></span></td>
                        <td data-label="Text"><?= e($r['text'] ?? '') ?></td>
                        <td data-label="Status"><?= (int) $r['approved'] ? '<span class="badge badge-green">Freigegeben</span>' : '<span class="badge badge-amber">Ausstehend</span>' ?></td>
                        <td class="actions-col">
                            <?php if (!(int) $r['approved']): ?>
                                <form method="post" action="<?= e(url('/admin/shop/reviews/' . $r['id'] . '/approve')) ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-small btn-primary">Freigeben</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/admin/shop/reviews/' . $r['id'] . '/delete')) ?>" class="inline" data-confirm="Bewertung löschen?" data-confirm-danger data-confirm-ok="Löschen">
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
