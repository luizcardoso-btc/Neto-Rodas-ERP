<?php
declare(strict_types=1);

namespace App;

/** Configuracoes da loja (cabecalho do orcamento, validade, condicoes) guardadas na tabela settings. */
final class Settings
{
    public const DEFAULTS = [
        'store_name' => 'NETO RODAS',
        'store_doc' => '',
        'store_phone' => '',
        'store_address' => '',
        'quote_validity_days' => '7',
        'tax_rate' => '0',
        'card_fee_rate' => '0',
        'creditor_name' => 'CREDOR',
        'creditor_monthly_goal' => '0',
        'wa_group_link' => '',
        'reactivate_message' => \App\Marketing::DEFAULT_REACTIVATE,
        'quote_terms' => 'ORCAMENTO SUJEITO A DISPONIBILIDADE DE ESTOQUE. VALORES PODEM SER ALTERADOS APOS A VALIDADE.',
    ];

    /** @return array<string,string> */
    public static function all(): array
    {
        $rows = Database::all('SELECT k, v FROM settings');
        $out = self::DEFAULTS;
        foreach ($rows as $r) {
            if (array_key_exists($r['k'], self::DEFAULTS)) {
                $out[$r['k']] = (string) $r['v'];
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data): void
    {
        foreach (self::DEFAULTS as $k => $_) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = match ($k) {
                'quote_validity_days' => (string) max(1, min(365, (int) $data[$k])),
                'tax_rate', 'card_fee_rate' => (string) max(0, min(100, parse_money((string) $data[$k]))),
                'creditor_monthly_goal' => (string) max(0, parse_money((string) $data[$k])),
                'wa_group_link', 'reactivate_message' => trim((string) $data[$k]),
                default => upper((string) $data[$k]),
            };
            Database::run('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
        }
    }
}
