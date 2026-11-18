<?php
// Acoes das contas a receber (usado no PDV e no Financeiro). Apagar e reabrir: so administrador.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string) ($_POST['action'] ?? ''), 'ar_')) {
    $a = (string) $_POST['action'];
    $id = (int) ($_POST['id'] ?? 0);
    $back = str_starts_with((string) ($_POST['back'] ?? ''), 'index.php?') ? (string) $_POST['back'] : url('financeiro', ['tab' => 'receber']);
    try {
        if (in_array($a, ['ar_delete', 'ar_reopen'], true) && !App\Auth::isAdmin()) {
            throw new DomainException('Somente o administrador pode apagar ou reabrir contas a receber.');
        }
        match ($a) {
            'ar_edit' => App\Receivables::update($id, $_POST),
            'ar_receive' => App\Receivables::receive($id, $_POST['partial'] ?? null, $_POST['new_due'] ?? null),
            'ar_reopen' => App\Receivables::reopen($id),
            'ar_delete' => App\Receivables::delete($id),
            default => null,
        };
        flash('ok', ['ar_edit' => 'Conta a receber atualizada.', 'ar_receive' => 'Recebimento registrado.', 'ar_reopen' => 'Recebimento desfeito: a conta voltou a ficar em aberto.', 'ar_delete' => 'Conta a receber apagada.'][$a] ?? 'Ok.');
    } catch (DomainException $ex) {
        flash('err', $ex->getMessage());
    }
    redirect($back);
}
