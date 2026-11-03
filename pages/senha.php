<?php
use App\Auth;
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = Database::one('SELECT id, password_hash FROM users WHERE id = ?', [Auth::user()['id']]);
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    if (!$row || !password_verify($current, $row['password_hash'])) {
        flash('err', 'Senha atual incorreta.');
    } elseif (mb_strlen($new) < 8) {
        flash('err', 'A nova senha precisa ter pelo menos 8 caracteres.');
    } elseif ($new !== (string) ($_POST['confirm'] ?? '')) {
        flash('err', 'A confirmacao nao confere com a nova senha.');
    } else {
        Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $row['id']]);
        session_regenerate_id(true);
        flash('ok', 'Senha alterada com sucesso.');
    }
    redirect(url('senha'));
}
?>
<section class="hero slim"><div><h1>Minha senha</h1><p>Troque a senha de acesso do usuario <b><?= e(Auth::user()['username']) ?></b>. Use pelo menos 8 caracteres, de preferencia misturando letras e numeros.</p></div></section>
<form class="card stack" method="post" style="max-width:460px">
  <?= App\Csrf::field() ?>
  <div><label class="f" for="current">Senha atual</label><input id="current" type="password" name="current" required autocomplete="current-password"></div>
  <div><label class="f" for="new">Nova senha</label><input id="new" type="password" name="new" required minlength="8" autocomplete="new-password"></div>
  <div><label class="f" for="confirm">Repita a nova senha</label><input id="confirm" type="password" name="confirm" required minlength="8" autocomplete="new-password"></div>
  <button class="btn green" type="submit">Salvar nova senha</button>
</form>
