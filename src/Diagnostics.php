<?php
declare(strict_types=1);

namespace App;

/** Pagina amigavel quando o banco nao esta pronto, em vez de erro generico do servidor. */
final class Diagnostics
{
    public static function dbProblem(\PDOException $e): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log('[ERP] ' . $e->getMessage());
        $tablesPending = $e->getCode() === '42S02';
        http_response_code(503);
        header('Retry-After: 10');
        header('Content-Type: text/html; charset=utf-8');

        $connectionErrors = [2002, 2003, 2006, 2013, 1044, 1045, 1049];
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $isConnection = in_array($driverCode, $connectionErrors, true) || str_starts_with((string) $e->getCode(), '08')
            || stripos($e->getMessage(), 'SQLSTATE[HY000] [') === 0;
        $configured = Database::isConfigured();
        $code = e((string) $e->getCode());
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>ERP Pneus</title><style>body{font:16px/1.6 system-ui,sans-serif;background:#070707;color:#e2e8f0;margin:0;display:grid;place-items:center;min-height:100vh;padding:20px}'
            . 'main{max-width:560px;background:#151515;border-radius:20px;padding:30px}h1{margin:0 0 8px;font-size:24px}code{background:#2a2a2a;padding:2px 8px;border-radius:8px;color:#ffd21a}'
            . 'li{margin:6px 0}</style></head><body><main>';
        if ($tablesPending) {
            echo '<h1>Preparando o sistema...</h1><p>O banco esta criando as tabelas pela primeira vez. Aguarde alguns segundos e recarregue a pagina.</p>'
                . '<meta http-equiv="refresh" content="8">';
        } elseif (!$isConnection) {
            echo '<h1>Erro ao consultar o banco</h1><p>O banco esta conectado, mas uma consulta falhou (codigo <code>' . $code
                . '</code>). O detalhe foi registrado nos logs do servico. Tente recarregar; se persistir, avise o suporte.</p>'
                . '<p><a href="?page=dashboard" style="color:#ffd21a">Voltar ao inicio</a></p>';
        } else {
            echo '<h1>Banco de dados nao conectado</h1>';
            if (!$configured) {
                echo '<p>Nenhuma configuracao de banco foi encontrada. No Railway:</p><ol>'
                    . '<li>Confirme que existe um servico <b>MySQL</b> neste projeto (<b>+ New &gt; Database &gt; MySQL</b>).</li>'
                    . '<li>No servico do ERP, aba <b>Variables</b>, crie <code>MYSQL_URL</code> com o valor <code>${{MySQL.MYSQL_URL}}</code> '
                    . '(troque <code>MySQL</code> pelo nome do seu servico de banco).</li>'
                    . '<li>Aguarde o novo deploy e recarregue.</li></ol>';
            } else {
                echo '<p>A configuracao existe, mas a conexao falhou (codigo <code>' . $code . '</code>). Confira se a senha e o host da variavel '
                    . '<code>MYSQL_URL</code> estao corretos e se o servico MySQL esta <b>Online</b>.</p>';
            }
        }
        echo '</main></body></html>';
        exit;
    }
}
