<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PrizeGamePrize;

final class PrizeGameDrawService
{
    /** @param list<PrizeGamePrize> $prizes */
    public function draw(array $prizes, int $ticket): ?PrizeGamePrize
    {
        if ($ticket < 1 || $ticket > 10000) {
            throw new \InvalidArgumentException('The draw ticket must be between 1 and 10000.');
        }

        $cumulative = 0;
        foreach ($prizes as $prize) {
            $cumulative += $prize->getProbabilityBp();
            if ($ticket <= $cumulative) {
                return $prize;
            }
        }

        return null;
    }
}
