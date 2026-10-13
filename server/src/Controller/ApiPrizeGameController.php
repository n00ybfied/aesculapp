<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PointTransaction;
use App\Entity\PrizeGame;
use App\Entity\PrizeGamePrize;
use App\Entity\PrizeGameSpin;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\PointAccountProvisioner;
use App\Service\PrizeGameDrawService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiPrizeGameController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly PointAccountProvisioner $accounts,
        private readonly PrizeGameDrawService $draw,
    ) {}

    #[Route('/api/v1/games', methods: ['GET'])]
    public function customerList(): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();

        $now = new \DateTimeImmutable();
        $games = $this->em->getRepository(PrizeGame::class)->createQueryBuilder('game')
            ->where('game.tenant = :tenant AND game.visible = true')
            ->andWhere('game.availableFrom <= :now AND game.availableUntil > :now')
            ->setParameter('tenant', $this->tenant->get())
            ->setParameter('now', $now)
            ->orderBy('game.availableFrom', 'DESC')->getQuery()->getResult();

        return new JsonResponse(['games' => array_map(fn (PrizeGame $game): array => $this->gameData($game, $customer), $games)]);
    }

    #[Route('/api/v1/games/wins', methods: ['GET'])]
    public function customerWins(): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();

        $spins = $this->em->getRepository(PrizeGameSpin::class)->createQueryBuilder('spin')
            ->join('spin.game', 'game')
            ->where('spin.customer = :customer AND game.tenant = :tenant AND spin.prizeKind = :kind')
            ->setParameter('customer', $customer)
            ->setParameter('tenant', $this->tenant->get())
            ->setParameter('kind', 'coupon')
            ->orderBy('spin.createdAt', 'DESC')->getQuery()->getResult();

        return new JsonResponse(['wins' => array_map(fn (PrizeGameSpin $spin): array => $this->spinData($spin), $spins)]);
    }

    #[Route('/api/v1/games/wins/redeem', methods: ['POST'])]
    public function presentWins(Request $request): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();
        $ids = $this->payload($request)['winIds'] ?? null;
        if (!is_array($ids) || $ids === [] || count($ids) > 50) {
            return $this->invalid('Bitte wählen Sie gültige, unterschiedliche Gewinne aus.');
        }
        foreach ($ids as $id) if (!is_int($id) || $id < 1) return $this->invalid('Ungültige Gewinnauswahl.');
        if (count(array_unique($ids)) !== count($ids)) return $this->invalid('Ein Gewinn darf nur einmal ausgewählt werden.');

        return $this->em->wrapInTransaction(function () use ($customer, $ids): JsonResponse {
            $this->em->lock($customer, LockMode::PESSIMISTIC_WRITE);
            $spins = $this->customerCouponWins($customer);
            if (array_any($spins, static fn (PrizeGameSpin $spin): bool => $spin->isPresentationActive())) {
                return new JsonResponse(['message' => 'Bitte zeigen Sie zuerst Ihre aktive Gewinn-Einlösung vor.'], Response::HTTP_CONFLICT);
            }
            $byId = [];
            foreach ($spins as $spin) $byId[$spin->getId()] = $spin;
            $selected = [];
            foreach ($ids as $id) {
                $spin = $byId[$id] ?? null;
                if ($spin === null || $spin->getRedeemedAt() !== null) {
                    return new JsonResponse(['message' => 'Ein ausgewählter Gewinn ist nicht mehr verfügbar.'], Response::HTTP_CONFLICT);
                }
                $selected[] = $spin;
            }
            $batchId = bin2hex(random_bytes(16));
            $now = new \DateTimeImmutable();
            foreach ($selected as $spin) $spin->startPresentation($batchId, $now, $this->tenant->get()->getRedemptionDurationMinutes());
            $this->em->flush();
            return new JsonResponse(['redemption' => $this->winBatchData($selected)], Response::HTTP_CREATED);
        });
    }

    #[Route('/api/v1/games/wins/redemptions/active', methods: ['GET'])]
    public function customerActiveWins(): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();
        $spins = $this->customerCouponWins($customer);
        foreach ($spins as $spin) {
            if (!$spin->isPresentationActive()) continue;
            $batch = array_values(array_filter($spins, static fn (PrizeGameSpin $item): bool => $item->getRedemptionBatchId() === $spin->getRedemptionBatchId()));
            return new JsonResponse(['redemption' => $this->winBatchData($batch)]);
        }
        return new JsonResponse(['redemption' => null]);
    }

    #[Route('/api/v1/admin/games/wins/redemptions/active', methods: ['GET'])]
    public function adminActiveWins(Request $request): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $spins = $this->em->getRepository(PrizeGameSpin::class)->createQueryBuilder('spin')
            ->join('spin.game', 'game')->where('game.tenant = :tenant AND spin.redemptionBatchId IS NOT NULL')
            ->setParameter('tenant', $this->tenant->get())->orderBy('spin.id', 'DESC')->getQuery()->getResult();
        $batches = [];
        foreach ($spins as $spin) if ($spin->isPresentationActive()) $batches[$spin->getRedemptionBatchId()][] = $spin;
        return new JsonResponse(['redemptions' => array_map(fn (array $batch): array => $this->winBatchData($batch, $request), array_values($batches))]);
    }

    #[Route('/api/v1/admin/games/wins/redemptions/{id}/cancel', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function cancelWinPresentation(string $id): Response
    {
        if (!$this->admin()) return $this->forbidden();
        return $this->em->wrapInTransaction(function () use ($id): Response {
            $spins = $this->em->getRepository(PrizeGameSpin::class)->findBy(['redemptionBatchId' => $id]);
            if ($spins === [] || $spins[0]->getGame()->getTenant() !== $this->tenant->get()) return $this->notFound();
            $this->em->lock($spins[0]->getCustomer(), LockMode::PESSIMISTIC_WRITE);
            foreach ($spins as $spin) $this->em->refresh($spin);
            if (!array_all($spins, static fn (PrizeGameSpin $spin): bool => $spin->isPresentationActive())) return $this->notFound();
            foreach ($spins as $spin) $spin->cancelPresentation();
            $this->em->flush();
            return new Response(status: Response::HTTP_NO_CONTENT);
        });
    }

    #[Route('/api/v1/games/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function customerDetail(int $id): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();
        $game = $this->game($id);
        $now = new \DateTimeImmutable();
        if ($game === null || !$game->isVisible() || $game->getAvailableFrom() > $now || $game->getAvailableUntil() <= $now) {
            return $this->notFound();
        }

        return new JsonResponse([
            'game' => $this->gameData($game, $customer, true),
            'spins' => array_map(fn (PrizeGameSpin $spin): array => $this->spinData($spin), $this->customerSpins($game, $customer)),
        ]);
    }

    #[Route('/api/v1/games/{id}/spin', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function spin(int $id, Request $request): JsonResponse
    {
        $customer = $this->customer();
        if ($customer === null) return $this->unauthorized();
        $payload = $this->payload($request);
        $requestId = $payload['requestId'] ?? null;
        if (!is_string($requestId) || !preg_match('/^[a-f0-9-]{36}$/i', $requestId)) {
            return $this->invalid('Ungültige Spielanfrage.');
        }

        return $this->em->wrapInTransaction(function () use ($id, $customer, $requestId): JsonResponse {
            $game = $this->game($id);
            if ($game === null) return $this->notFound();
            // This row serializes every draw, including stock and the early end of the game.
            $this->em->lock($game, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($game);
            $previous = $this->em->getRepository(PrizeGameSpin::class)->findOneBy([
                'game' => $game, 'customer' => $customer, 'requestId' => $requestId,
            ]);
            if ($previous instanceof PrizeGameSpin) {
                return new JsonResponse(['spin' => $this->spinData($previous), 'game' => $this->gameData($game, $customer, true)]);
            }

            $now = new \DateTimeImmutable();
            if (!$game->isVisible() || $game->getAvailableFrom() > $now || $game->getAvailableUntil() <= $now || $game->getSoldOutAt() !== null) {
                return new JsonResponse(['message' => 'Dieses Gewinnspiel ist nicht mehr verfügbar.'], Response::HTTP_CONFLICT);
            }
            $spins = $this->customerSpins($game, $customer);
            if ($this->remaining($game, $spins, $now) <= 0) {
                return new JsonResponse(['message' => 'Sie haben Ihre Teilnahmen für dieses Gewinnspiel bereits genutzt.'], Response::HTTP_CONFLICT);
            }

            $prizes = $this->prizes($game);
            if ($prizes === []) return new JsonResponse(['message' => 'Dieses Gewinnspiel ist noch nicht vollständig eingerichtet.'], Response::HTTP_CONFLICT);
            foreach ($prizes as $candidate) {
                if ($candidate->isExhausted()) {
                    $game->markSoldOut($now);
                    return new JsonResponse(['message' => $game->getSoldOutText()], Response::HTTP_CONFLICT);
                }
            }

            $prize = $this->draw->draw($prizes, random_int(1, 10000));
            $spin = new PrizeGameSpin($game, $customer, $prize, $requestId, null);
            $this->em->persist($spin);
            if ($prize !== null) {
                $prize->award();
                if ($prize->getKind() === 'points') {
                    $account = $this->accounts->getOrCreate($this->tenant->get(), $customer);
                    $this->em->persist(new PointTransaction($account, $prize->getPoints(), 'prize_game_win', 'Gewinnspiel: '.$game->getTitle()));
                }
                if ($prize->isExhausted()) $game->markSoldOut($now);
            }
            $this->em->flush();

            return new JsonResponse(['spin' => $this->spinData($spin), 'game' => $this->gameData($game, $customer, true)], Response::HTTP_CREATED);
        });
    }

    #[Route('/api/v1/admin/games', methods: ['GET'])]
    public function adminList(): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $games = $this->em->getRepository(PrizeGame::class)->findBy(['tenant' => $this->tenant->get()], ['id' => 'DESC']);
        return new JsonResponse(['games' => array_map(fn (PrizeGame $game): array => $this->gameData($game, null, true), $games)]);
    }

    #[Route('/api/v1/admin/games/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function adminDetail(int $id): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $game = $this->game($id);
        return $game === null ? $this->notFound() : new JsonResponse(['game' => $this->adminGameData($game)]);
    }

    #[Route('/api/v1/admin/games', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $data = $this->validatedGameData($this->payload($request));
        if ($data === null) return $this->invalid('Bitte prüfen Sie Zeitraum, Teilnahmegrenze und Gewinnchancen.');

        $game = new PrizeGame($this->tenant->get(), $data['title'], $data['from'], $data['until']);
        $game->configure($data['title'], $data['visible'], $data['from'], $data['until'], $data['mode'], $data['limit'], $data['noWin'], $data['soldOutText']);
        $this->em->persist($game);
        foreach ($data['prizes'] as $index => $prize) $this->em->persist(new PrizeGamePrize($game, $prize[0], $prize[1], $prize[2], $prize[3], $prize[4], $prize[5], $index));
        $this->em->flush();
        return new JsonResponse(['game' => $this->adminGameData($game)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/games/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $data = $this->validatedGameData($this->payload($request));
        if ($data === null) return $this->invalid('Bitte prüfen Sie Zeitraum, Teilnahmegrenze und Gewinnchancen.');

        return $this->em->wrapInTransaction(function () use ($id, $data): JsonResponse {
            $game = $this->game($id);
            if ($game === null) return $this->notFound();
            // Use the same game lock as a spin so rules cannot change during a draw.
            $this->em->lock($game, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($game);
            $prizes = $this->prizes($game);
            $spinCount = $this->em->getRepository(PrizeGameSpin::class)->count(['game' => $game]);
            if ($spinCount > 0 && !$this->canDebugEditStartedGame()) {
                if ($data['from'] != $game->getAvailableFrom() || $data['mode'] !== $game->getParticipationMode()
                    || $data['limit'] !== $game->getSpinLimit() || $data['noWin'] !== $game->isNoWinEnabled()
                    || count($data['prizes']) !== count($prizes)) {
                    return new JsonResponse(['message' => 'Nach der ersten Teilnahme können Spielregeln und Gewinne nicht mehr geändert werden.'], Response::HTTP_CONFLICT);
                }
                foreach ($prizes as $index => $prize) {
                    if (array_slice($data['prizes'][$index], 0, 6) !== [$prize->getTitle(), $prize->getWheelLabel(), $prize->getKind(), $prize->getPoints(), $prize->getProbabilityBp(), $prize->getStockLimit()]) {
                        return new JsonResponse(['message' => 'Nach der ersten Teilnahme können Gewinne nicht mehr geändert werden.'], Response::HTTP_CONFLICT);
                    }
                }
            } elseif ($spinCount > 0) {
                if (count($data['prizes']) < count($prizes)) {
                    return new JsonResponse(['message' => 'Bereits vorhandene Gewinnfelder dürfen wegen der bisherigen Ziehungen nicht entfernt werden.'], Response::HTTP_CONFLICT);
                }
                foreach ($prizes as $index => $prize) {
                    if ($data['prizes'][$index][6] !== $prize->getId()) {
                        return new JsonResponse(['message' => 'Bereits vorhandene Gewinnfelder dürfen nicht umsortiert oder ersetzt werden.'], Response::HTTP_CONFLICT);
                    }
                }
                foreach (array_slice($data['prizes'], count($prizes)) as $values) {
                    if ($values[6] !== null) {
                        return new JsonResponse(['message' => 'Neue Gewinnfelder dürfen keine bestehende Gewinn-ID verwenden.'], Response::HTTP_CONFLICT);
                    }
                }
                foreach ($prizes as $index => $prize) {
                    $values = $data['prizes'][$index];
                    $prize->update($values[0], $values[1], $values[2], $values[3], $values[4], $values[5], $index);
                }
                foreach (array_slice($data['prizes'], count($prizes)) as $offset => $values) {
                    $this->em->persist(new PrizeGamePrize($game, $values[0], $values[1], $values[2], $values[3], $values[4], $values[5], count($prizes) + $offset));
                }
                if (array_any($prizes, static fn (PrizeGamePrize $prize): bool => $prize->isExhausted())) {
                    $game->markSoldOut(new \DateTimeImmutable());
                } else {
                    $game->clearSoldOut();
                }
            } else {
                foreach ($prizes as $prize) $this->em->remove($prize);
                foreach ($data['prizes'] as $index => $prize) $this->em->persist(new PrizeGamePrize($game, $prize[0], $prize[1], $prize[2], $prize[3], $prize[4], $prize[5], $index));
            }
            $game->configure($data['title'], $data['visible'], $data['from'], $data['until'], $data['mode'], $data['limit'], $data['noWin'], $data['soldOutText']);
            $this->em->flush();
            return new JsonResponse(['game' => $this->adminGameData($game)]);
        });
    }

    #[Route('/api/v1/admin/games/{id}/wins', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function adminWins(int $id): JsonResponse
    {
        if (!$this->admin()) return $this->forbidden();
        $game = $this->game($id);
        if ($game === null) return $this->notFound();
        $spins = $this->em->getRepository(PrizeGameSpin::class)->findBy(['game' => $game], ['id' => 'DESC']);
        return new JsonResponse(['wins' => array_map(fn (PrizeGameSpin $spin): array => [
            ...$this->spinData($spin), 'customer' => $spin->getCustomer()->getDisplayName(),
            'redeemedBy' => $spin->getRedeemedBy()?->getDisplayName(),
        ], $spins)]);
    }

    private function game(int $id): ?PrizeGame
    {
        $game = $this->em->getRepository(PrizeGame::class)->find($id);
        return $game instanceof PrizeGame && $game->getTenant() === $this->tenant->get() ? $game : null;
    }

    /** @return list<PrizeGamePrize> */
    private function prizes(PrizeGame $game): array
    {
        return $this->em->getRepository(PrizeGamePrize::class)->findBy(['game' => $game], ['position' => 'ASC']);
    }

    /** @return list<PrizeGameSpin> */
    private function customerSpins(PrizeGame $game, User $customer): array
    {
        return $this->em->getRepository(PrizeGameSpin::class)->findBy(['game' => $game, 'customer' => $customer], ['id' => 'DESC']);
    }

    /** @return list<PrizeGameSpin> */
    private function customerCouponWins(User $customer): array
    {
        return $this->em->getRepository(PrizeGameSpin::class)->createQueryBuilder('spin')
            ->join('spin.game', 'game')
            ->where('spin.customer = :customer AND game.tenant = :tenant AND spin.prizeKind = :kind')
            ->setParameter('customer', $customer)->setParameter('tenant', $this->tenant->get())
            ->setParameter('kind', 'coupon')->orderBy('spin.id', 'DESC')->getQuery()->getResult();
    }

    /** @param non-empty-list<PrizeGameSpin> $batch */
    private function winBatchData(array $batch, ?Request $request = null): array
    {
        $customer = $batch[0]->getCustomer();
        $items = array_map(static fn (PrizeGameSpin $spin): array => [
            'winId' => $spin->getId(), 'title' => $spin->getPrizeTitle(), 'gameTitle' => $spin->getGame()->getTitle(),
        ], $batch);
        $result = [
            'id' => $batch[0]->getRedemptionBatchId(), 'items' => $items,
            'summary' => implode(', ', array_column($items, 'title')),
            'validUntil' => $batch[0]->getRedemptionValidUntil()?->format(DATE_ATOM),
        ];
        if ($request !== null) {
            $result['customer'] = $customer->getDisplayName();
            $result['customerDetails'] = [
                'id' => $customer->getId(), 'displayName' => $customer->getDisplayName(),
                'username' => $customer->getUsername(),
                'profileImageUrl' => $customer->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$customer->getProfileImagePath(),
            ];
        }
        return $result;
    }

    /** @param list<PrizeGameSpin> $spins */
    private function remaining(PrizeGame $game, array $spins, \DateTimeImmutable $now): int
    {
        if ($game->getParticipationMode() !== 'daily') return max(0, $game->getSpinLimit() - count($spins));
        $today = $now->setTimezone(new \DateTimeZone('Europe/Vienna'))->format('Y-m-d');
        $count = 0;
        foreach ($spins as $spin) {
            if ($spin->getCreatedAt()->setTimezone(new \DateTimeZone('Europe/Vienna'))->format('Y-m-d') === $today) ++$count;
        }
        return max(0, $game->getSpinLimit() - $count);
    }

    /** @return array<string, mixed> */
    private function gameData(PrizeGame $game, ?User $customer, bool $withPrizes = false): array
    {
        $now = new \DateTimeImmutable();
        $status = !$game->isVisible() ? 'draft' : ($game->getAvailableUntil() <= $now ? 'ended' : ($game->getSoldOutAt() !== null ? 'sold_out' : ($game->getAvailableFrom() > $now ? 'scheduled' : 'active')));
        $data = [
            'id' => $game->getId(), 'title' => $game->getTitle(), 'isVisible' => $game->isVisible(),
            'availableFrom' => $game->getAvailableFrom()->format(DATE_ATOM), 'availableUntil' => $game->getAvailableUntil()->format(DATE_ATOM),
            'participationMode' => $game->getParticipationMode(), 'spinLimit' => $game->getSpinLimit(),
            'noWinEnabled' => $game->isNoWinEnabled(), 'soldOutText' => $game->getSoldOutText(),
            'soldOutAt' => $game->getSoldOutAt()?->format(DATE_ATOM), 'status' => $status,
        ];
        if ($customer !== null) $data['remainingSpins'] = $status === 'active' ? $this->remaining($game, $this->customerSpins($game, $customer), $now) : 0;
        if ($withPrizes) {
            $data['prizes'] = array_map(static fn (PrizeGamePrize $prize): array => [
                'id' => $prize->getId(), 'title' => $prize->getTitle(), 'wheelLabel' => $prize->getWheelLabel(),
                'kind' => $prize->getKind(), 'points' => $prize->getPoints(), 'probabilityBp' => $prize->getProbabilityBp(),
                'stockLimit' => $prize->getStockLimit(), 'awardedCount' => $prize->getAwardedCount(),
            ], $this->prizes($game));
        }
        return $data;
    }

    /** @return array<string, mixed> */
    private function adminGameData(PrizeGame $game): array
    {
        return $this->gameData($game, null, true) + ['debugEditAllowed' => $this->canDebugEditStartedGame()];
    }

    /** @return array<string, mixed> */
    private function spinData(PrizeGameSpin $spin): array
    {
        return [
            'id' => $spin->getId(), 'gameId' => $spin->getGame()->getId(), 'gameTitle' => $spin->getGame()->getTitle(),
            'prizeId' => $spin->getPrize()?->getId(), 'wheelIndex' => $spin->getPrize()?->getPosition(),
            'title' => $spin->getPrizeTitle() ?? 'Leider nein', 'kind' => $spin->getPrizeKind() ?? 'none',
            'points' => $spin->getPoints(),
            'createdAt' => $spin->getCreatedAt()->format(DATE_ATOM), 'redeemedAt' => $spin->getRedeemedAt()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        try { $payload = $request->toArray(); return is_array($payload) ? $payload : []; }
        catch (\Throwable) { return []; }
    }

    /** @return array{title:string,visible:bool,from:\DateTimeImmutable,until:\DateTimeImmutable,mode:string,limit:int,noWin:bool,soldOutText:string,prizes:list<array{string,string,string,int,int,?int,?int}>}|null */
    private function validatedGameData(array $payload): ?array
    {
        if (!is_string($payload['title'] ?? null) || !is_string($payload['soldOutText'] ?? null)) return null;
        $title = trim($payload['title']);
        $soldOutText = trim($payload['soldOutText']);
        $mode = $payload['participationMode'] ?? null;
        $limit = $payload['spinLimit'] ?? null;
        $visible = $payload['isVisible'] ?? false;
        $noWin = $payload['noWinEnabled'] ?? false;
        $from = $this->date($payload['availableFrom'] ?? null);
        $until = $this->date($payload['availableUntil'] ?? null);
        $rawPrizes = $payload['prizes'] ?? null;
        if ($title === '' || mb_strlen($title) > 180 || $soldOutText === '' || mb_strlen($soldOutText) > 3000
            || !in_array($mode, ['once', 'total', 'daily'], true) || !is_int($limit) || $limit < 1 || $limit > 1000
            || ($mode === 'once' && $limit !== 1) || !is_bool($visible) || !is_bool($noWin)
            || $from === null || $until === null || $from >= $until
            || !is_array($rawPrizes) || $rawPrizes === [] || count($rawPrizes) > 24) return null;

        $prizes = [];
        $sum = 0;
        foreach ($rawPrizes as $raw) {
            if (!is_array($raw)) return null;
            if (!is_string($raw['title'] ?? null) || !is_string($raw['wheelLabel'] ?? null)) return null;
            $prizeTitle = trim($raw['title']);
            $label = trim($raw['wheelLabel']);
            $kind = $raw['kind'] ?? null;
            $points = $raw['points'] ?? 0;
            $probability = $raw['probabilityBp'] ?? null;
            $stock = $raw['stockLimit'] ?? null;
            $id = $raw['id'] ?? null;
            if ($prizeTitle === '' || mb_strlen($prizeTitle) > 180 || $label === '' || mb_strlen($label) > 40
                || !in_array($kind, ['points', 'coupon'], true) || !is_int($points) || $points < 0 || $points > 100000
                || ($kind === 'points' && $points < 1) || ($kind === 'coupon' && $points !== 0)
                || !is_int($probability) || $probability < 1 || $probability > 10000
                || ($stock !== null && (!is_int($stock) || $stock < 1 || $stock > 1000000))
                || ($id !== null && (!is_int($id) || $id < 1))) return null;
            $sum += $probability;
            $prizes[] = [$prizeTitle, $label, $kind, $points, $probability, $stock, $id];
        }
        if ($sum > 10000 || (!$noWin && $sum !== 10000) || ($noWin && $sum >= 10000)) return null;
        return compact('title', 'visible', 'from', 'until', 'mode', 'limit', 'noWin', 'soldOutText', 'prizes');
    }

    private function date(mixed $raw): ?\DateTimeImmutable
    {
        if (!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/', $raw)) return null;
        try { return new \DateTimeImmutable($raw); }
        catch (\Throwable) { return null; }
    }

    private function customer(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User && $this->memberships->hasCustomerMembershipFor($user, $this->tenant->get()) ? $user : null;
    }

    private function admin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && [] !== array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles());
    }

    private function canDebugEditStartedGame(): bool
    {
        if (!$this->tenant->get()->allowsStartedPrizeGameEdits()) return false;
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true);
    }

    private function unauthorized(): JsonResponse { return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED); }
    private function forbidden(): JsonResponse { return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN); }
    private function notFound(): JsonResponse { return new JsonResponse(['message' => 'Gewinnspiel nicht gefunden.'], Response::HTTP_NOT_FOUND); }
    private function invalid(string $message): JsonResponse { return new JsonResponse(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY); }
}
