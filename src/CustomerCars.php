<?php
declare(strict_types=1);

namespace App;

use DomainException;

/**
 * Carros dos clientes fotografados no provador de rodas.
 * Ficam salvos (com as fotos ja sem fundo) para mostrar de novo numa proxima venda.
 */
final class CustomerCars
{
    public const MAX_IMG = 6 * 1024 * 1024;
    private const MIMES = ['image/png', 'image/webp', 'image/jpeg'];

    /** Acha o cliente pelo telefone ou cria um novo. Sem nome e sem telefone, nao vincula. */
    public static function customerFor(string $name, string $phone): ?int
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $d = digits($phone);
        if ($d !== '' && strlen($d) >= 8) {
            $id = Database::value(
                "SELECT id FROM customers WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') LIKE ? ORDER BY id LIMIT 1",
                ['%' . substr($d, -8)]
            );
            if ($id) {
                return (int) $id;
            }
        }
        if ($name === '') {
            return null;
        }
        $parts = explode(' ', upper($name), 2);
        return Database::insert(
            'INSERT INTO customers (first_name, last_name, phone) VALUES (?, ?, ?)',
            [$parts[0], $parts[1] ?? '', $d !== '' ? format_phone($d) : null]
        );
    }

    /**
     * @param array $d campos: owner_name, phone, brand, model, color, plate, tire
     * @param array $images lista de ['data' => bytes, 'mime' => ..., 'angle' => ..., 'wheels' => array|null]
     */
    public static function create(array $d, array $images, ?int $userId = null): int
    {
        $brand = upper(trim((string) ($d['brand'] ?? '')));
        $model = upper(trim((string) ($d['model'] ?? '')));
        if ($brand === '' || $model === '') {
            throw new DomainException('Informe a marca e o modelo do carro.');
        }
        if (!$images) {
            throw new DomainException('Tire a foto do carro antes de salvar.');
        }
        foreach ($images as $im) {
            if (!in_array($im['mime'] ?? '', self::MIMES, true) || strlen((string) $im['data']) < 100 || strlen((string) $im['data']) > self::MAX_IMG) {
                throw new DomainException('Foto do carro invalida ou grande demais.');
            }
        }
        $tire = self::tire((string) ($d['tire'] ?? ''));
        $owner = upper(trim((string) ($d['owner_name'] ?? '')));
        $phone = digits((string) ($d['phone'] ?? ''));
        $customer = self::customerFor($owner, $phone);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert(
                'INSERT INTO customer_cars (customer_id, owner_name, phone, brand, model, color, plate, tire, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$customer, $owner, $phone !== '' ? format_phone($phone) : null, $brand, $model, self::color($d['color'] ?? null), self::plate($d['plate'] ?? null), $tire, $userId]
            );
            foreach (array_values($images) as $i => $im) {
                Database::insert(
                    'INSERT INTO customer_car_images (car_id, angle, sort, mime, data, wheels) VALUES (?,?,?,?,?,?)',
                    [$id, mb_substr((string) ($im['angle'] ?? 'Lateral direita'), 0, 40), $i, $im['mime'], $im['data'], isset($im['wheels']) ? self::wheels($im['wheels']) : null]
                );
            }
            if ($customer) {
                Database::run("UPDATE customers SET car = ? WHERE id = ? AND COALESCE(car, '') = ''", [trim("$brand $model"), $customer]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        $car = Database::one('SELECT * FROM customer_cars WHERE id = ?', [$id]);
        if (!$car) {
            throw new DomainException('Carro do cliente nao encontrado.');
        }
        Database::run(
            'UPDATE customer_cars SET brand = ?, model = ?, color = ?, plate = ?, tire = ? WHERE id = ?',
            [
                upper(trim((string) ($d['brand'] ?? ''))) ?: $car['brand'],
                upper(trim((string) ($d['model'] ?? ''))) ?: $car['model'],
                array_key_exists('color', $d) ? self::color($d['color']) : $car['color'],
                array_key_exists('plate', $d) ? self::plate($d['plate']) : $car['plate'],
                !empty($d['tire']) ? self::tire((string) $d['tire']) : $car['tire'],
                $id,
            ]
        );
    }

    public static function setWheels(int $carId, int $imgId, array $w): void
    {
        $n = Database::run('UPDATE customer_car_images SET wheels = ? WHERE id = ? AND car_id = ?', [self::wheels($w), $imgId, $carId]);
        if ($n < 0) {
            throw new DomainException('Foto nao encontrada.');
        }
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM customer_cars WHERE id = ?', [$id]);
    }

    /** Lista para o provador (sem os bytes das fotos). */
    public static function all(): array
    {
        $imgs = [];
        foreach (Database::all('SELECT id, car_id, angle, wheels FROM customer_car_images ORDER BY car_id, sort, id') as $im) {
            $imgs[(int) $im['car_id']][] = ['id' => (int) $im['id'], 'angulo' => $im['angle'], 'src' => url('provador', ['cimg' => $im['id']]), 'rodas' => $im['wheels'] ? json_decode($im['wheels'], true) : null];
        }
        return array_map(fn ($c) => [
            'id' => 'k' . $c['id'], 'cid' => (int) $c['id'], 'cliente' => true, 'marca' => $c['brand'], 'modelo' => $c['model'], 'tipo' => 'hatch',
            'pneu' => $c['tire'], 'cor' => (string) $c['color'], 'placa' => (string) $c['plate'], 'dono' => (string) $c['owner_name'],
            'fone' => (string) $c['phone'], 'customer_id' => $c['customer_id'] ? (int) $c['customer_id'] : null, 'data' => substr((string) $c['created_at'], 0, 10),
            'fotos' => $imgs[(int) $c['id']] ?? [],
        ], Database::all('SELECT * FROM customer_cars ORDER BY updated_at DESC, id DESC LIMIT 500'));
    }

    public static function image(int $id): ?array
    {
        return Database::one('SELECT mime, data, created_at FROM customer_car_images WHERE id = ?', [$id]);
    }

    public static function tire(string $t): string
    {
        $t = preg_replace('#\s*R#i', ' R', strtoupper(trim($t))) ?? '';
        return preg_match('#^\d{3}/\d{2} R\d{2}$#', $t) ? $t : '185/60 R15';
    }

    private static function color(mixed $c): ?string
    {
        $c = upper(trim((string) $c));
        return $c === '' ? null : mb_substr($c, 0, 40);
    }

    private static function plate(mixed $p): ?string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $p) ?? '');
        return $p === '' ? null : substr($p, 0, 10);
    }

    private static function wheels(array $w): string
    {
        $list = array_slice(array_values((array) ($w['list'] ?? [])), 0, 4);
        return json_encode(['on' => !empty($w['on']), 'list' => array_map(fn ($r) => ['x' => (float) ($r['x'] ?? 0), 'y' => (float) ($r['y'] ?? 0), 'r' => (float) ($r['r'] ?? 0), 'sq' => (float) ($r['sq'] ?? 1)], $list)]);
    }
}
