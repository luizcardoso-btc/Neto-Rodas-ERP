<?php
use App\Database;
use App\Marketing;

$action = (string) $_POST['action'];
$back = (string) ($_POST['back'] ?? url('crm', ['tab' => 'reativar']));
if (!str_starts_with($back, 'index.php?')) {
    $back = url('crm');
}
$adminOnly = ['mk_import', 'mk_settings', 'mk_offer_delete'];
if (in_array($action, $adminOnly, true) && !App\Auth::isAdmin()) {
    flash('err', 'Somente o administrador pode fazer isso.');
    redirect($back);
}
try {
    if ($action === 'mk_contact') {
        // Marca como chamado e abre o WhatsApp com a mensagem pronta (o formulario abre em nova aba).
        $c = Database::one('SELECT * FROM customers WHERE id = ?', [(int) $_POST['id']]);
        if (!$c) {
            throw new DomainException('Cliente nao encontrado.');
        }
        $s = App\Settings::all();
        $offerId = (int) ($_POST['offer_id'] ?? 0);
        if (!empty($_POST['birthday'])) {
            $text = Marketing::render($s['birthday_message'], $c, $s['wa_group_link']);
            Database::run('UPDATE customers SET birthday_sent_year = ? WHERE id = ?', [(int) ($_POST['year'] ?? date('Y')), $c['id']]);
        } elseif ($offerId) {
            $o = Database::one('SELECT * FROM offers WHERE id = ?', [$offerId]);
            if (!$o) {
                throw new DomainException("Oferta nao encontrada.");
            }
            $text = Marketing::render((string) $o['message'], $c, $s['wa_group_link'], (string) $o['measure']);
            Database::run('INSERT IGNORE INTO offer_contacts (offer_id, customer_id) VALUES (?, ?)', [$offerId, $c['id']]);
        } else {
            $text = Marketing::render($s['reactivate_message'], $c, $s['wa_group_link']);
        }
        if (empty($_POST['birthday'])) {
            Database::run('UPDATE customers SET contacted_at = NOW() WHERE id = ?', [$c['id']]);
        }
        $wa = Marketing::waUrl($c['phone'], $text);
        if ($wa === '') {
            throw new DomainException('Este cliente nao tem telefone.');
        }
        redirect($wa);
    } elseif ($action === 'mk_optout') {
        Database::run('UPDATE customers SET opt_out = ? WHERE id = ?', [empty($_POST['undo']) ? 1 : 0, (int) $_POST['id']]);
        flash('ok', empty($_POST['undo']) ? 'Cliente marcado como "nao quer receber mensagens".' : 'Cliente voltou a receber mensagens.');
    } elseif ($action === 'mk_settings') {
        $link = trim((string) ($_POST['wa_group_link'] ?? ''));
        if ($link !== '' && !preg_match('#^https://(chat\.whatsapp\.com|wa\.me|whatsapp\.com)/#i', $link)) {
            throw new DomainException('O link do grupo deve comecar com https://chat.whatsapp.com/');
        }
        App\Settings::save(['wa_group_link' => $link, 'reactivate_message' => trim((string) ($_POST['reactivate_message'] ?? '')) ?: Marketing::DEFAULT_REACTIVATE, 'birthday_message' => trim((string) ($_POST['birthday_message'] ?? '')) ?: Marketing::DEFAULT_BIRTHDAY]);
        flash('ok', 'Mensagem e link do grupo salvos.');
    } elseif ($action === 'mk_offer_save') {
        $title = upper((string) ($_POST['title'] ?? ''));
        $msg = trim((string) ($_POST['message'] ?? ''));
        if ($title === '' || $msg === '') {
            throw new DomainException('Informe o titulo e a mensagem da oferta.');
        }
        $id = Database::insert('INSERT INTO offers (title, message, measure, car_filter) VALUES (?,?,?,?)', [
            mb_substr($title, 0, 120), $msg, upper((string) ($_POST['measure'] ?? '')) ?: null, upper((string) ($_POST['car_filter'] ?? '')) ?: null,
        ]);
        flash('ok', 'Oferta criada. Agora envie para os clientes da lista.');
        $back = url('crm', ['tab' => 'ofertas', 'offer' => $id]);
    } elseif ($action === 'mk_offer_toggle') {
        Database::run('UPDATE offers SET active = 1 - active WHERE id = ?', [(int) $_POST['id']]);
    } elseif ($action === 'mk_offer_delete') {
        Database::run('DELETE FROM offers WHERE id = ?', [(int) $_POST['id']]);
        flash('ok', 'Oferta excluida.');
        $back = url('crm', ['tab' => 'ofertas']);
    } elseif ($action === 'mk_import') {
        $f = $_FILES['file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) {
            throw new DomainException('Envie um arquivo CSV de ate 10 MB.');
        }
        $r = Marketing::importCsv((string) file_get_contents($f['tmp_name']), (string) ($_POST['source'] ?? 'SISTEMA ANTIGO'));
        flash('ok', sprintf('Importacao concluida: %d novo(s), %d atualizado(s), %d ignorado(s) (repetidos ou sem nome/telefone).', $r['inserted'], $r['updated'], $r['skipped']));
    }
} catch (DomainException $ex) {
    flash('err', $ex->getMessage());
}
redirect($back);
