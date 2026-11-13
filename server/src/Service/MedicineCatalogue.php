<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class MedicineCatalogue
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function imported(): bool
    {
        return $this->db->fetchOne('SELECT 1 FROM medicine_import WHERE source_key = ?', ['catalogue']) !== false;
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $tenantId): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $query = mb_substr($query, 0, 80);
        $rows = $this->db->fetchAllAssociative(
            "SELECT c.pzn, c.name, c.pack, c.delivery_code, s.stock_qty, s.reserved_qty FROM medicine_catalogue c LEFT JOIN medicine_stock s ON s.pzn = c.pzn AND s.tenant_id = :tenant WHERE c.name_search LIKE :contains ESCAPE '!' OR c.pzn = :pzn ORDER BY CASE WHEN c.name_search = :exact THEN 0 WHEN c.name_search LIKE :prefix ESCAPE '!' THEN 1 ELSE 2 END, c.name_search, c.pzn LIMIT 20",
            ['tenant' => $tenantId, 'contains' => '%'.$this->escapeLike(mb_strtolower($query)).'%', 'prefix' => $this->escapeLike(mb_strtolower($query)).'%', 'pzn' => $query, 'exact' => mb_strtolower($query)],
        );
        $fresh = $this->isFresh($tenantId);
        return array_map(fn (array $row): array => $this->publicRow($row, $fresh), $rows);
    }

    /** @return array<string, mixed>|null */
    public function find(string $pzn, int $tenantId): ?array
    {
        $row = $this->db->fetchAssociative('SELECT c.pzn, c.name, c.pack, c.delivery_code, s.stock_qty, s.reserved_qty FROM medicine_catalogue c LEFT JOIN medicine_stock s ON s.pzn = c.pzn AND s.tenant_id = :tenant WHERE c.pzn = :pzn', ['tenant' => $tenantId, 'pzn' => $pzn]);
        return $row === false ? null : $this->publicRow($row, $this->isFresh($tenantId));
    }

    /** @return array{rows:list<array<string, mixed>>, imports:array<string, mixed>} */
    public function debug(string $query, int $tenantId): array
    {
        $query = mb_substr(trim($query), 0, 80);
        $rows = $query === '' ? [] : $this->db->fetchAllAssociative(
            "SELECT c.pzn, c.name, c.pack, c.delivery_code, c.edition, c.aep_cents, c.kvp_cents, c.avp_cents, s.stock_qty, s.reserved_qty FROM medicine_catalogue c LEFT JOIN medicine_stock s ON s.pzn = c.pzn AND s.tenant_id = :tenant WHERE c.name_search LIKE :contains ESCAPE '!' OR c.pzn = :pzn ORDER BY c.name_search LIMIT 20",
            ['tenant' => $tenantId, 'contains' => '%'.$this->escapeLike(mb_strtolower($query)).'%', 'pzn' => $query],
        );
        $imports = [];
        foreach (['catalogue', 'stock:'.$tenantId] as $source) {
            $imports[$source] = $this->db->fetchAssociative('SELECT imported_at, source_mtime, row_count, sha256 FROM medicine_import WHERE source_key = ?', [$source]) ?: null;
        }
        return ['rows' => $rows, 'imports' => $imports];
    }

    /** @return array<string, mixed> */
    private function publicRow(array $row, bool $fresh): array
    {
        $status = 'unknown';
        if ($fresh) {
            $free = max(0, (int) ($row['stock_qty'] ?? 0) - (int) ($row['reserved_qty'] ?? 0));
            $status = $free > 0 ? 'available' : ($row['delivery_code'] === '0' ? 'unavailable' : 'orderable');
        }
        return ['pzn' => $row['pzn'], 'name' => $row['name'], 'pack' => $row['pack'], 'status' => $status];
    }

    private function isFresh(int $tenantId): bool
    {
        $stock = $this->db->fetchOne('SELECT source_mtime FROM medicine_import WHERE source_key = ?', ['stock:'.$tenantId]);
        $catalogue = $this->db->fetchOne('SELECT imported_at FROM medicine_import WHERE source_key = ?', ['catalogue']);
        if ($catalogue === false || $stock === false) return false;
        $timestamp = (new \DateTimeImmutable((string) $stock, new \DateTimeZone('UTC')))->getTimestamp();
        return $timestamp >= time() - 45 * 60 && $timestamp <= time() + 5 * 60;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
