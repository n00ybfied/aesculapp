<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Entity\PrizeGame;
use App\Entity\PrizeGamePrize;
use App\Entity\Tenant;
use App\Service\PrizeGameDrawService;

$tenant = new Tenant('Testapotheke', 'test');
$game = new PrizeGame($tenant, 'Glücksrad', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
$points = new PrizeGamePrize($game, 'Punkte', '100 Punkte', 'points', 100, 3000, null, 0);
$coupon = new PrizeGamePrize($game, 'Gutschein', 'Gutschein', 'coupon', 0, 2000, 2, 1);
$draw = new PrizeGameDrawService();

foreach ([1 => $points, 3000 => $points, 3001 => $coupon, 5000 => $coupon, 5001 => null, 10000 => null] as $ticket => $expected) {
    if ($draw->draw([$points, $coupon], $ticket) !== $expected) {
        throw new RuntimeException('Falscher Gewinn für Losnummer '.$ticket);
    }
}

foreach ([0, 10001] as $invalid) {
    try { $draw->draw([$points, $coupon], $invalid); }
    catch (InvalidArgumentException) { continue; }
    throw new RuntimeException('Ungültige Losnummer wurde akzeptiert.');
}

echo "OK: Gewinnspiel-Grenzen und Restwahrscheinlichkeit\n";
