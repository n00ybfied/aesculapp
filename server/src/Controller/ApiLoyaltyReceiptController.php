<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LoyaltyReceiptRedemption;
use App\Entity\PointTransaction;
use App\Entity\User;
use App\Service\ActiveTenantProvider;
use App\Service\LoyaltyReceiptQrParser;
use App\Service\PointAccountProvisioner;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

final class ApiLoyaltyReceiptController
{
    public function __construct(
        private readonly ActiveTenantProvider $activeTenant,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoyaltyReceiptQrParser $parser,
        private readonly PointAccountProvisioner $pointAccounts,
        private readonly Security $security,
        #[Autowire(service: 'monolog.logger.qr')]
        private readonly LoggerInterface $qrLogger,
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
            $this->qrLogger->warning('qr.import.rejected.invalid_payload', ['userId' => $user->getId()]);
            return new JsonResponse(['message' => 'Invalid QR code.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rawQrValue = trim($rawQrValue);
        $qrHashPrefix = substr(hash('sha256', $rawQrValue), 0, 12);
        $tenant = $this->activeTenant->get();
        $expectedPrefix = $tenant->getReceiptQrPrefix();
        if ($expectedPrefix !== null && !str_starts_with($rawQrValue, $expectedPrefix)) {
            $this->qrLogger->notice('qr.import.rejected.prefix_mismatch', [
                'tenantId' => $tenant->getId(),
                'userId' => $user->getId(),
                'qrHashPrefix' => $qrHashPrefix,
                'qrLength' => strlen($rawQrValue),
            ]);
            return new JsonResponse(['message' => 'Dieser QR-Code gehört nicht zum Punkteprogramm dieser Apotheke.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $receipt = $this->parser->parse($rawQrValue);
        } catch (\InvalidArgumentException) {
            $this->qrLogger->warning('qr.import.rejected.unsupported', [
                'userId' => $user->getId(),
                'qrHashPrefix' => $qrHashPrefix,
                'qrLength' => strlen($rawQrValue),
            ]);
            return new JsonResponse(['message' => 'Unsupported loyalty QR code.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $qrHash = hash('sha256', $rawQrValue);
        $context = [
            'tenantId' => $tenant->getId(),
            'userId' => $user->getId(),
            'receiptNumber' => $receipt['receiptNumber'],
            'issuedAt' => $receipt['issuedAt']->format(DATE_ATOM),
            'eligibleCents' => $receipt['eligibleCents'],
            'qrHashPrefix' => $qrHashPrefix,
        ];
        if ($receipt['eligibleCents'] < 1) {
            $this->qrLogger->notice('qr.import.rejected.no_eligible_amount', $context);
            return new JsonResponse(['message' => 'Dieser Beleg enthält keinen punktefähigen Betrag.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$tenant->allowsDuplicateReceiptImports() && $this->entityManager->getRepository(LoyaltyReceiptRedemption::class)->findOneBy(['tenant' => $tenant, 'qrHash' => $qrHash]) instanceof LoyaltyReceiptRedemption) {
            $this->qrLogger->notice('qr.import.rejected.duplicate', $context);
            return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
        }
        $this->qrLogger->info('qr.import.requested', $context + ['debugDuplicatesAllowed' => $tenant->allowsDuplicateReceiptImports()]);

        try {
            return $this->entityManager->wrapInTransaction(function () use ($tenant, $user, $qrHash, $receipt, $context): JsonResponse {
                $account = $this->pointAccounts->getOrCreate($tenant, $user);

                $points = intdiv($receipt['eligibleCents'] * $tenant->getPointsPerEuro(), 100);
                $existing = $this->entityManager->getRepository(LoyaltyReceiptRedemption::class)->findOneBy(['tenant' => $tenant, 'qrHash' => $qrHash]);
                if ($existing instanceof LoyaltyReceiptRedemption && !$tenant->allowsDuplicateReceiptImports()) {
                    $this->qrLogger->notice('qr.import.rejected.duplicate', $context);
                    return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
                }
                $label = $tenant->allowsDuplicateReceiptImports() && $existing instanceof LoyaltyReceiptRedemption
                    ? sprintf('Debug-Mehrfacheinlösung %s', $receipt['receiptNumber'])
                    : sprintf('Punktefähiger Einkauf %s', $receipt['receiptNumber']);
                $pointTransaction = new PointTransaction($account, $points, 'receipt_credit', $label);
                $this->entityManager->persist($pointTransaction);
                if (!$existing instanceof LoyaltyReceiptRedemption) {
                    $this->entityManager->persist(new LoyaltyReceiptRedemption($tenant, $account, $pointTransaction, $qrHash, $receipt['receiptNumber'], $receipt['issuedAt'], $receipt['eligibleCents'], $points));
                }
                $this->entityManager->flush();
                $this->qrLogger->info('qr.import.accepted', $context + [
                    'points' => $points,
                    'isDebugDuplicate' => $existing instanceof LoyaltyReceiptRedemption,
                ]);

                return new JsonResponse(['addedPoints' => $points]);
            });
        } catch (UniqueConstraintViolationException) {
            $this->qrLogger->notice('qr.import.rejected.duplicate_race', $context);
            if ($tenant->allowsDuplicateReceiptImports()) {
                return new JsonResponse(['message' => 'Die Mehrfacheinlösung konnte nicht als Debug-Test gespeichert werden.'], Response::HTTP_CONFLICT);
            }
            return new JsonResponse(['message' => 'Dieser Beleg wurde bereits eingelöst.'], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->qrLogger->error('qr.import.failed.unexpected', $context + ['exception' => $exception]);
            throw $exception;
        }
    }
}
