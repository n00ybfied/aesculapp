<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class MedicineFileImporter
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function importCatalogue(string $file, bool $force = false): int
    {
        $hash = $this->hash($file);
        if (!$force && $this->same('catalogue', $hash)) return 0;
        $seen = [];
        $edition = null;
        $count = 0;
        foreach ($this->catalogueRecords($file) as $row) {
            if (isset($seen[$row[0]])) throw new \RuntimeException('Duplicate PZN '.$row[0]);
            if ($edition !== null && $row[8] !== $edition) throw new \RuntimeException('Mixed catalogue editions.');
            $edition = $row[8];
            $seen[$row[0]] = true;
            ++$count;
        }
        unset($seen);
        if ($count < 1000) throw new \RuntimeException('Catalogue is incomplete.');
        $this->db->transactional(function () use ($count, $hash, $file): void {
            $this->db->executeStatement('DELETE FROM medicine_catalogue');
            foreach ($this->catalogueRecords($file) as $row) {
                $this->db->executeStatement('INSERT INTO medicine_catalogue (pzn, name, name_search, pack, delivery_code, aep_cents, kvp_cents, avp_cents, edition) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $row);
            }
            if ($hash !== $this->hash($file)) throw new \RuntimeException('Catalogue changed during import.');
            $this->mark('catalogue', $hash, $file, $count);
        });
        return $count;
    }

    /** @return \Generator<int, array{string, string, string, string, string, ?int, ?int, ?int, string}> */
    private function catalogueRecords(string $file): \Generator
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) throw new \RuntimeException('Cannot open catalogue.');
        try {
            $number = 0;
            while (($line = fgets($handle)) !== false) {
                ++$number;
                $line = rtrim($line, "\r\n");
                if (strlen($line) !== 128 || !ctype_digit(substr($line, 1, 7))) throw new \RuntimeException('Invalid APGDA record at line '.$number);
                $name = preg_replace('/\s+/u', ' ', trim(iconv('CP850', 'UTF-8', substr($line, 66, 28)) ?: ''));
                if ($name === '') throw new \RuntimeException('Empty article name at line '.$number);
                $pack = trim(iconv('CP850', 'UTF-8', substr($line, 94, 7)) ?: '');
                yield [substr($line, 1, 7), $name, mb_strtolower($name), $pack, substr($line, 105, 1), $this->cents(substr($line, 28, 9)), $this->cents(substr($line, 37, 9)), $this->cents(substr($line, 46, 9)), substr($line, 19, 4)];
            }
        } finally {
            fclose($handle);
        }
    }

    public function importStockLog(string $file, int $tenantId, bool $force = false): int
    {
        $hash = $this->hash($file);
        $key = 'stock:'.$tenantId;
        if (!$force && $this->same($key, $hash)) return 0;
        $handle = fopen($file, 'rb');
        if ($handle === false) throw new \RuntimeException('Cannot open stock log.');
        $rows = [];
        $expected = null;
        $complete = false;
        $inUpdate = false;
        $lastTimestamp = null;
        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) begin UpdateFull/', $line, $match)) {
                    if ($expected !== null && !$complete && count($rows) !== $expected) throw new \RuntimeException('Incomplete stock full snapshot.');
                    $rows = []; $expected = null; $complete = false; $inUpdate = true; $lastTimestamp = $match[1];
                } elseif (preg_match('/SQL fertig: (\d+) Artikel/', $line, $match) && !$complete) {
                    $expected = (int) $match[1];
                } elseif (preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) \[Artikel \d+-\d+\]: (.*)$/', trim($line), $match)) {
                    if ($expected === null && !$complete) continue;
                    $lastTimestamp = $match[1];
                    foreach (explode(' | ', $match[2]) as $part) {
                        if (!preg_match('/^(\d{7});(-?\d+);(\d+)$/D', trim($part), $fields)) throw new \RuntimeException('Invalid stock record.');
                        $rows[$fields[1]] = [(int) $fields[2], (int) $fields[3]];
                    }
                } elseif (str_contains($line, 'begin UpdateDelta')) {
                    if ($expected === null || (!$complete && count($rows) !== $expected)) throw new \RuntimeException('Incomplete stock full snapshot before delta.');
                    $complete = true; $inUpdate = true;
                } elseif (preg_match('/^(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d) end Update(?:Full|Delta)/', $line, $match)) {
                    if ($expected === null || (!$complete && count($rows) !== $expected)) throw new \RuntimeException('Incomplete stock update.');
                    $lastTimestamp = $match[1];
                    $inUpdate = false;
                }
            }
        } finally {
            fclose($handle);
        }
        if ($inUpdate || $expected === null || (!$complete && count($rows) !== $expected) || count($rows) < 1000) throw new \RuntimeException('No complete stock snapshot.');
        if ($hash !== $this->hash($file)) throw new \RuntimeException('Stock log changed during import.');
        $this->db->transactional(function () use ($rows, $tenantId, $key, $hash, $file, $lastTimestamp): void {
            $this->db->executeStatement('DELETE FROM medicine_stock WHERE tenant_id = ?', [$tenantId]);
            foreach ($rows as $pzn => [$stock, $reserved]) {
                $this->db->executeStatement('INSERT INTO medicine_stock (tenant_id, pzn, stock_qty, reserved_qty) VALUES (?, ?, ?, ?)', [$tenantId, $pzn, $stock, $reserved]);
            }
            $this->mark($key, $hash, $file, count($rows), $lastTimestamp);
        });
        return count($rows);
    }

    private function cents(string $value): ?int
    {
        $value = trim($value);
        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    private function hash(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) throw new \RuntimeException('Import file unavailable: '.$file);
        return hash_file('sha256', $file);
    }

    private function same(string $key, string $hash): bool
    {
        return $this->db->fetchOne('SELECT sha256 FROM medicine_import WHERE source_key = ?', [$key]) === $hash;
    }

    private function mark(string $key, string $hash, string $file, int $count, ?string $sourceTime = null): void
    {
        $mtime = $sourceTime === null
            ? gmdate('Y-m-d H:i:s', (int) filemtime($file))
            : (new \DateTimeImmutable($sourceTime, new \DateTimeZone('Europe/Vienna')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->db->executeStatement('INSERT INTO medicine_import (source_key, sha256, imported_at, source_mtime, row_count) VALUES (?, ?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE sha256 = VALUES(sha256), imported_at = VALUES(imported_at), source_mtime = VALUES(source_mtime), row_count = VALUES(row_count)', [$key, $hash, $mtime, $count]);
    }
}
