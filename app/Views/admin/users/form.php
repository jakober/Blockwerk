<?php
$isEdit = $user !== null;
$action = $isEdit ? '/admin/users/' . $user['id'] : '/admin/users';
?>
<div class="card narrow">
    <form method="post" action="<?= e(url($action)) ?>">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" value="<?= e($user['username'] ?? '') ?>" required autofocus autocomplete="username">
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label for="password"><?= $isEdit ? 'Neues Passwort (leer = unverändert)' : 'Passwort (min. 8 Zeichen)' ?></label>
                <input type="password" id="password" name="password" minlength="8" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
            </div>
            <div class="form-group grow">
                <label for="password_repeat">Passwort wiederholen</label>
                <input type="password" id="password_repeat" name="password_repeat" minlength="8" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
            </div>
        </div>
        <?php if (\Core\Auth::isAdmin() && empty($isSelf)): ?>
            <div class="form-group">
                <label for="role">Rolle</label>
                <select id="role" name="role">
                    <option value="admin" <?= ($user['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Administrator (voller Zugriff)</option>
                    <option value="editor" <?= ($user['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Redakteur (nur Inhalte: Seiten, News, Events, Formulare, Medien, globale Blöcke)</option>
                </select>
            </div>
        <?php endif; ?>
        <?php if (!empty($isSelf)): ?>
            <p class="muted small">Das ist dein eigenes Konto – nach einer Passwort-Änderung meldest du dich beim nächsten Mal mit dem neuen Passwort an, die aktuelle Sitzung bleibt bestehen.</p>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Benutzer anlegen' ?></button>
            <a class="btn btn-ghost" href="<?= e(url('/admin/users')) ?>">Abbrechen</a>
        </div>
    </form>
</div>
