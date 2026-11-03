<?php
declare(strict_types=1);

namespace App;

use DomainException;

/**
 * Padrao unico de rodas: "JOGO DE RODA <MARCA> R<ARO> - <ACABAMENTO>" (ex.: JOGO DE RODA KS R18 - DIAMANTADA PRATA).
 * Roda e cadastrada, estocada e vendida por JOGO (4 unidades): custo e preco sao do jogo fechado.
 */
final class Wheel
{
    /** Acabamentos permitidos (para acrescentar um, basta incluir na lista). */
    public const FINISHES = ['PRATA', 'PRETA', 'GRAFITE', 'DIAMANTADA PRATA', 'DIAMANTADA PRETA'];
    public const RIM_MIN = 13;
    public const RIM_MAX = 22;
    public const BRANDS = ['KS', 'KR'];

    public static function name(string $brand, int|string $rim, string $finish, bool $used = false): string
    {
        return sprintf('JOGO DE RODA%s %s R%s - %s', $used ? ' USADA' : '', upper(trim($brand)), $rim, upper(trim($finish)));
    }

    /** @return array{brand:string,rim:string,finish:string,name:string} dados validados e normalizados */
    public static function normalize(string $brand, string $rim, string $finish, bool $used): array
    {
        $brand = upper(trim($brand));
        $finish = upper(trim($finish));
        $rimN = (int) digits($rim);
        if ($brand === '' || mb_strlen($brand) > 40) {
            throw new DomainException('Roda: informe a MARCA (ex.: KS).');
        }
        if ($rimN < self::RIM_MIN || $rimN > self::RIM_MAX) {
            throw new DomainException(sprintf('Roda: escolha o ARO (de %d a %d).', self::RIM_MIN, self::RIM_MAX));
        }
        if (!in_array($finish, self::FINISHES, true)) {
            throw new DomainException('Roda: escolha o ACABAMENTO na lista (' . implode(', ', self::FINISHES) . ').');
        }
        return ['brand' => $brand, 'rim' => (string) $rimN, 'finish' => $finish, 'name' => self::name($brand, $rimN, $finish, $used)];
    }

    /** @return list<string> marcas ja cadastradas + padrao, para sugestao. */
    public static function brands(): array
    {
        $db = array_column(Database::all("SELECT DISTINCT brand FROM products WHERE category = 'roda' AND brand IS NOT NULL AND brand <> '' ORDER BY brand"), 'brand');
        return array_values(array_unique(array_merge(self::BRANDS, array_map('upper', $db))));
    }
}
