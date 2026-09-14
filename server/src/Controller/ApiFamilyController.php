<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{FamilyConnection, TenantMembership, User};
use App\Repository\{TenantMembershipRepository, UserRepository};
use App\Service\{ActiveTenantProvider, ChatPushService, TenantMailer};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ApiFamilyController
{
    public function __construct(private readonly Security $security, private readonly ActiveTenantProvider $tenant, private readonly TenantMembershipRepository $memberships, private readonly UserRepository $users, private readonly EntityManagerInterface $em, private readonly TenantMailer $mailer, private readonly ChatPushService $push, private readonly string $clientUrl, private readonly string $mailFrom) {}

    #[Route('/api/v1/family', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->user(); $tenant = $this->tenant->get();
        $connections = $this->em->getRepository(FamilyConnection::class)->createQueryBuilder('connection')->where('connection.tenant = :tenant')->andWhere('connection.participantOne = :user OR connection.participantTwo = :user')->setParameter('tenant', $tenant)->setParameter('user', $user)->orderBy('connection.createdAt', 'DESC')->getQuery()->getResult();
        return new JsonResponse(['connections' => array_map(fn (FamilyConnection $connection) => $this->serialize($connection, $user), $connections)]);
    }

    #[Route('/api/v1/family/invitations', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $user = $this->user(); $data = $this->json($request); $email = is_array($data) && is_string($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) return new JsonResponse(['message' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 422);
        $recipient = $this->users->findOneByEmail($email); $tenant = $this->tenant->get();
        if (!$recipient instanceof User || $recipient->getId() === $user->getId() || !$this->isCustomer($recipient)) return new JsonResponse(['message' => 'Falls ein Kundenkonto mit dieser E-Mail-Adresse besteht, wurde die Einladung versendet.'], 202);
        $existing = $this->em->getRepository(FamilyConnection::class)->createQueryBuilder('connection')->where('connection.tenant = :tenant')->andWhere('(connection.participantOne = :first AND connection.participantTwo = :second) OR (connection.participantOne = :second AND connection.participantTwo = :first)')->setParameter('tenant', $tenant)->setParameter('first', $user)->setParameter('second', $recipient)->getQuery()->getOneOrNullResult();
        if ($existing instanceof FamilyConnection) return new JsonResponse(['message' => 'Für dieses Familienmitglied besteht bereits eine Verbindung oder Einladung.'], 409);
        $token = bin2hex(random_bytes(32)); $connection = new FamilyConnection($tenant, $user, $recipient, hash('sha256', $token)); $this->em->persist($connection);
        try {
            $url = rtrim($this->clientUrl, '/') . '/familie?token=' . rawurlencode($token);
            $inviterName = htmlspecialchars($user->getDisplayName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $recipientName = htmlspecialchars($recipient->getDisplayName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->mailer->send($tenant, (new Email())
                ->from($this->mailFrom)
                ->to($recipient->getEmail())
                ->subject('Einladung zum Familienzugang')
                ->text("{$user->getDisplayName()} möchte mit Ihnen einen Familienzugang in AesculApp verbinden. Bestätigen Sie die Einladung innerhalb von sieben Tagen:\n{$url}\n\nDanach legen beide Seiten getrennt fest, welche Medikamentendaten freigegeben werden.")
                ->html("<p>Hallo {$recipientName},</p><p><strong>{$inviterName}</strong> möchte mit Ihnen einen Familienzugang in AesculApp verbinden.</p><p><a href=\"{$safeUrl}\">Familieneinladung annehmen</a></p><p>Die Einladung ist sieben Tage gültig. Danach legen beide Seiten getrennt fest, welche Medikamentendaten freigegeben werden.</p>"),
                'family_invitation',
            );
            $this->em->flush();
        } catch (\Throwable) { return new JsonResponse(['message' => 'Die Einladung konnte derzeit nicht versendet werden.'], 503); }
        $this->push->scheduleFamilyNotification($recipient, $tenant, 'Familienzugang', 'Sie haben eine neue Einladung zum Familienzugang.');
        return new JsonResponse(['connection' => $this->serialize($connection, $user)], 201);
    }

    #[Route('/api/v1/family/invitations/accept', methods: ['POST'])]
    public function accept(Request $request): JsonResponse
    {
        $user = $this->user(); $data = $this->json($request); $token = is_array($data) && is_string($data['token'] ?? null) ? $data['token'] : ''; if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return new JsonResponse(['message' => 'Die Einladung ist ungültig.'], 422);
        $connection = $this->em->getRepository(FamilyConnection::class)->findOneBy(['tenant' => $this->tenant->get(), 'tokenHash' => hash('sha256', $token)]);
        if (!$connection instanceof FamilyConnection || !$connection->accepts($user, hash('sha256', $token))) return new JsonResponse(['message' => 'Die Einladung ist ungültig oder abgelaufen.'], 404);
        $connection->accept();
        $this->em->flush();
        $inviter = $connection->getInvitedBy();
        try {
            $inviterName = htmlspecialchars($inviter->getDisplayName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $recipientName = htmlspecialchars($user->getDisplayName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $url = htmlspecialchars(rtrim($this->clientUrl, '/') . '/familie', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->mailer->send($this->tenant->get(), (new Email())
                ->from($this->mailFrom)
                ->to($inviter->getEmail())
                ->subject('Ihre Familien-Einladung wurde angenommen')
                ->text("{$user->getDisplayName()} hat Ihre Einladung zum Familienzugang in AesculApp angenommen. Sie können nun in den Familieneinstellungen getrennt festlegen, welche Medikamentendaten Sie freigeben möchten.\n\n{$this->clientUrl}/familie")
                ->html("<p>Hallo {$inviterName},</p><p><strong>{$recipientName}</strong> hat Ihre Einladung zum Familienzugang in AesculApp angenommen.</p><p>Sie können nun getrennt festlegen, welche Medikamentendaten Sie füreinander freigeben möchten.</p><p><a href=\"{$url}\">Familieneinstellungen öffnen</a></p>"),
                'family_invitation_accepted',
            );
        } catch (\Throwable) {
            // The connection is already valid; mail delivery is logged by TenantMailer and must not undo acceptance.
        }
        $this->push->scheduleFamilyNotification($inviter, $this->tenant->get(), 'Familienzugang', 'Eine Einladung zum Familienzugang wurde angenommen.');
        return new JsonResponse(['connection' => $this->serialize($connection, $user)]);
    }

    #[Route('/api/v1/family/{id}/medication-access', methods: ['PATCH'])]
    public function medicationAccess(int $id, Request $request): JsonResponse
    {
        $user = $this->user(); $connection = $this->connection($id, $user); $data = $this->json($request); $view = is_array($data) ? $data['allowView'] ?? null : null; $manage = is_array($data) ? $data['allowManage'] ?? null : null;
        if (!is_bool($view) || !is_bool($manage)) return new JsonResponse(['message' => 'Ungültige Freigabe.'], 422);
        $connection->setMedicationAccess($user, $view, $manage); $this->em->flush(); return new JsonResponse(['connection' => $this->serialize($connection, $user)]);
    }

    #[Route('/api/v1/family/{id}/point-sharing/request', methods: ['POST'])]
    public function requestPointSharing(int $id): JsonResponse
    {
        $user = $this->user(); $tenant = $this->tenant->get(); $connection = $this->connection($id, $user);
        if (!$tenant->isFamilyPointSharingEnabled()) return new JsonResponse(['message' => 'Die Apotheke hat die Punkteteilung für Familien nicht freigegeben.'], 403);
        try { $connection->requestPointSharing($user); } catch (\LogicException) { return new JsonResponse(['message' => 'Die Punkteteilung kann derzeit nicht angefragt werden.'], 409); }
        $this->em->flush(); $recipient = $connection->other($user);
        try { $url = rtrim($this->clientUrl, '/') . '/familie'; $this->mailer->send($tenant, (new Email())->from($this->mailFrom)->to($recipient->getEmail())->subject('Punkteteilung für Familie angefragt')->text("{$user->getDisplayName()} möchte die Punkte mit Ihnen teilen. Bitte öffnen Sie den Familienzugang und stimmen Sie zu oder lehnen Sie ab.\n\n{$url}"), 'family_point_sharing_requested'); } catch (\Throwable) { /* Delivery failure is logged and does not undo the request. */ }
        $this->push->scheduleFamilyNotification($recipient, $tenant, 'Gemeinsame Punkte', 'Sie haben eine Anfrage zur gemeinsamen Punkteteilung erhalten.');
        return new JsonResponse(['connection' => $this->serialize($connection, $user)]);
    }

    #[Route('/api/v1/family/{id}/point-sharing/accept', methods: ['POST'])]
    public function acceptPointSharing(int $id): JsonResponse
    {
        $user = $this->user(); $connection = $this->connection($id, $user);
        try { $connection->acceptPointSharing($user); } catch (\LogicException) { return new JsonResponse(['message' => 'Die Punkteteilung kann derzeit nicht angenommen werden.'], 409); }
        $this->em->flush(); $requester = $connection->other($user);
        try { $this->mailer->send($this->tenant->get(), (new Email())->from($this->mailFrom)->to($requester->getEmail())->subject('Punkteteilung wurde angenommen')->text("{$user->getDisplayName()} hat der Punkteteilung zugestimmt.\n\n{$this->clientUrl}/familie"), 'family_point_sharing_accepted'); } catch (\Throwable) { /* Delivery failure is logged and does not undo consent. */ }
        $this->push->scheduleFamilyNotification($requester, $this->tenant->get(), 'Gemeinsame Punkte', 'Ihre Anfrage zur gemeinsamen Punkteteilung wurde angenommen.');
        return new JsonResponse(['connection' => $this->serialize($connection, $user)]);
    }

    #[Route('/api/v1/family/{id}/accept', methods: ['POST'])]
    public function acceptListedInvitation(int $id): JsonResponse
    {
        $user = $this->user(); $connection = $this->connection($id, $user);
        if (!$connection->canBeAcceptedBy($user)) return new JsonResponse(['message' => 'Die Einladung ist ungültig oder abgelaufen.'], 404);
        $connection->accept(); $this->em->flush();
        $inviter = $connection->getInvitedBy();
        try { $this->mailer->send($this->tenant->get(), (new Email())->from($this->mailFrom)->to($inviter->getEmail())->subject('Ihre Familien-Einladung wurde angenommen')->text("{$user->getDisplayName()} hat Ihre Einladung zum Familienzugang in AesculApp angenommen.\n\n{$this->clientUrl}/familie"), 'family_invitation_accepted'); } catch (\Throwable) { /* Delivery failure is logged and does not undo acceptance. */ }
        $this->push->scheduleFamilyNotification($inviter, $this->tenant->get(), 'Familienzugang', 'Eine Einladung zum Familienzugang wurde angenommen.');
        return new JsonResponse(['connection' => $this->serialize($connection, $user)]);
    }

    #[Route('/api/v1/family/{id}', methods: ['DELETE'])]
    public function disconnect(int $id): Response
    { $connection = $this->connection($id, $this->user()); $this->em->remove($connection); $this->em->flush(); return new Response(null, 204); }

    private function user(): User { $user = $this->security->getUser(); if (!$user instanceof User || !$this->isCustomer($user)) throw new \Symfony\Component\HttpKernel\Exception\HttpException(403); return $user; }
    private function isCustomer(User $user): bool { $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get()); return $membership instanceof TenantMembership && in_array('ROLE_CUSTOMER', $membership->getRoles(), true); }
    private function connection(int $id, User $user): FamilyConnection { $connection = $this->em->getRepository(FamilyConnection::class)->find($id); if (!$connection instanceof FamilyConnection || $connection->getTenant()->getId() !== $this->tenant->get()->getId() || !$connection->isParticipant($user)) throw new \Symfony\Component\HttpKernel\Exception\HttpException(404); return $connection; }
    private function json(Request $request): ?array { try { $data = $request->toArray(); return is_array($data) ? $data : null; } catch (\Throwable) { return null; } }
    private function serialize(FamilyConnection $connection, User $current): array { $other = $connection->other($current); return ['id' => $connection->getId(), 'status' => $connection->isAccepted() ? 'accepted' : 'pending', 'isIncomingInvitation' => !$connection->isAccepted() && $connection->canBeAcceptedBy($current), 'pointSharingStatus' => $connection->getPointSharingStatus(), 'isPointSharingRequestedByCurrentUser' => $connection->isPointSharingRequestedBy($current), 'canAcceptPointSharing' => $connection->canAcceptPointSharing($current), 'other' => ['id' => $other->getId(), 'displayName' => $other->getDisplayName(), 'profileImageUrl' => $other->getProfileImagePath()], 'canViewMedication' => $connection->canViewMedication($other, $current), 'canManageMedication' => $connection->canManageMedication($other, $current), 'otherCanViewMedication' => $connection->canViewMedication($current, $other), 'otherCanManageMedication' => $connection->canManageMedication($current, $other)]; }
}
