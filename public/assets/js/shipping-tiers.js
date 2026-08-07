/**
 * Gewichtsstaffeln der Versandarten (admin/shop/settings.php): jede Versandart
 * hat ihren eigenen Zeilen-Tabelle-Block, aber ein gemeinsames Skript per
 * Event-Delegation bedient sie alle (kein Bedarf für Einzelregistrierung).
 */
(function () {
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-tier-toggle]')) return;
        var target = document.querySelector(e.target.dataset.target);
        if (target) target.hidden = !e.target.checked;
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('tier-add')) {
            var body = document.querySelector(e.target.dataset.target);
            if (!body) return;
            var tr = document.createElement('tr');
            tr.className = 'tier-row';
            tr.innerHTML = '<td><input type="number" name="tier_max_kg[]" min="0.01" step="0.01" placeholder="5"></td>'
                + '<td><input type="text" name="tier_price[]" inputmode="decimal" placeholder="20,00"></td>'
                + '<td><button type="button" class="btn btn-small btn-ghost tier-del">✕</button></td>';
            body.appendChild(tr);
        } else if (e.target.classList.contains('tier-del')) {
            e.target.closest('tr').remove();
        }
    });
})();
