<?php

declare(strict_types=1);

namespace App\Service;

final class LoyaltyReceiptQrParser
{
    /** @return array{receiptNumber:string,issuedAt:\DateTimeImmutable,eligibleCents:int} */
    public function parse(string $rawQrValue): array
    {
        $parts = explode('_', trim($rawQrValue));
        $timestampIndex = null;

        foreach ($parts as $index => $part) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $part) === 1) {
                $timestampIndex = $index;
                break;
            }
        }

        if ($timestampIndex === null || $timestampIndex < 1 || !isset($parts[$timestampIndex + 5])) {
            throw new \InvalidArgumentException('Unsupported loyalty QR code.');
        }

        $eligibleAmount = $parts[$timestampIndex + 5];
        if (preg_match('/^\d+,\d{2}$/', $eligibleAmount) !== 1) {
            throw new \InvalidArgumentException('Invalid eligible amount.');
        }

        try {
            $issuedAt = new \DateTimeImmutable($parts[$timestampIndex]);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid receipt timestamp.');
        }

        [$euros, $cents] = explode(',', $eligibleAmount);
        return ['receiptNumber' => $parts[$timestampIndex - 1], 'issuedAt' => $issuedAt, 'eligibleCents' => ((int) $euros * 100) + (int) $cents];
    }
}
