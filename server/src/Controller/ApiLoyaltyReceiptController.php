<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LoyaltyReceiptRedemption;
use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\User;
use App\Service\ActiveTenantProvider;
use App\Service\LoyaltyReceiptQrParser;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiLoyaltyReceiptController
{
    public function __construct(
        private readonly ActiveTenantProvider $activeTenant,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoyaltyReceiptQrParser $parser,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/v1/receipts/import', name: 'api_v1_loyalty_receipt_import', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $rawQrValue = $request->toArray()['rawQrValue'] ?? null;
        } catch (\Throwable) {
            $rawQrValue = null;
        }
        if (!is_string($rawQrValue) || trim($rawQrValue) === '') {
            return new JsonResponse(['message' => 'Invalid QR code.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $rawQrValue = trim($rawQrValue);
            $receipt = $this->parser->parse($rawQrValue);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Unsupported loyalty QR code.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tenant = $this->activeTenant->get();
        $qrHash = hash('sha256', $rawQrValue);
        if ($receipt['eligibleCents'] < 1) {
            return new JsonResponse(['message' => 'Dieser Beleg enthält keinen punktefähigen Betrag.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$tenant->allowsDuplicateReceiptImports() && $this->entityManager->getRepository(LoyaltyReceiptRedemption::class)->findOneBy(['tenant' => $tenant, 'qrHash' => $qrHash]) instanceof LoyaltyReceiptRedemption) {
            return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
        }

        try {
            return $this->entityManager->wrapInTransaction(function () use ($tenant, $user, $qrHash, $receipt): JsonResponse {
                $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy(['tenant' => $tenant, 'owner' => $user]);
                if (!$account instanceof PointAccount) {
                    return new JsonResponse(['message' => 'Point account not found.'], Response::HTTP_NOT_FOUND);
                }

                $points = intdiv($receipt['eligibleCents'] * $tenant->getPointsPerEuro(), 100);
                $existing = $this->entityManager->getRepository(LoyaltyReceiptRedemption::class)->findOneBy(['tenant' => $tenant, 'qrHash' => $qrHash]);
                if ($existing instanceof LoyaltyReceiptRedemption && !$tenant->allowsDuplicateReceiptImports()) {
                    return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
                }
                if (!$existing instanceof LoyaltyReceiptRedemption) {
                    $this->entityManager->persist(new LoyaltyReceiptRedemption($tenant, $account, $qrHash, $receipt['receiptNumber'], $receipt['issuedAt'], $receipt['eligibleCents'], $points));
                }
                $label = $tenant->allowsDuplicateReceiptImports() && $existing instanceof LoyaltyReceiptRedemption
                    ? sprintf('Debug-Mehrfacheinlösung %s', $receipt['receiptNumber'])
                    : sprintf('Punktefähiger Einkauf %s', $receipt['receiptNumber']);
                $this->entityManager->persist(new PointTransaction($account, $points, 'receipt_credit', $label));
                $this->entityManager->flush();

                return new JsonResponse(['addedPoints' => $points]);
            });
        } catch (UniqueConstraintViolationException) {
            if ($tenant->allowsDuplicateReceiptImports()) {
                return new JsonResponse(['message' => 'Die Mehrfacheinlösung konnte nicht als Debug-Test gespeichert werden.'], Response::HTTP_CONFLICT);
            }
            return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
        }
    }
}
