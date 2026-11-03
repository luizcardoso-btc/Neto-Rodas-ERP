<?php
use App\Auth;

$layout = 'bare';
$title = 'Entrar';
if (Auth::check()) {
    redirect(url('dashboard'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limite simples de tentativas por sessao.
    $_SESSION['_tries'] = ($_SESSION['_tries'] ?? 0) + 1;
    if ($_SESSION['_tries'] > 8 && time() - ($_SESSION['_last_try'] ?? 0) < 60) {
        $error = 'Muitas tentativas. Aguarde um minuto.';
    } elseif (Auth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        unset($_SESSION['_tries']);
        redirect(url('dashboard'));
    } else {
        $error = 'Usuario ou senha incorretos.';
    }
    $_SESSION['_last_try'] = time();
}
?>
<div class="login">
  <form class="card stack" method="post" autocomplete="on">
    <?= App\Csrf::field() ?>
    <img class="logo" src="assets/logo.webp" alt="Neto Rodas e Pneus" width="640" height="390">
    <div style="text-align:center"><h1>ERP Pneus</h1><p class="muted" style="margin:4px 0 0">Acesso ao sistema</p></div>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <div><label class="f" for="u">Usuario</label><input id="u" name="username" required autofocus autocomplete="username"></div>
    <div><label class="f" for="p">Senha</label><input id="p" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn green block" type="submit">Entrar</button>
  </form>
</div>
