<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\AppointmentCancellationNotice;
use App\Entity\AppointmentResource;
use App\Entity\AppointmentType;
use App\Entity\ChatConversation;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AppointmentBlocker;
use App\Service\AppointmentStaffNotifier;
use App\Service\ChatPushService;
use App\Service\ChatCipher;
use App\Service\TenantMailer;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminAppointmentController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly TenantMailer $mailer,
        private readonly string $mailFrom,
        private readonly AppointmentBlocker $blocker,
        private readonly ChatPushService $push,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.auth.client_url%')]
        private readonly string $clientUrl,
        private readonly ?AppointmentStaffNotifier $staffNotifier = null,
        private readonly ?ChatCipher $chatCipher = null,
    ) {
    }

    #[Route('/api/v1/admin/appointments', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $data = $request->toArray();
        $tenant = $this->activeTenant->get();
        $type = $this->entityManager->getRepository(AppointmentType::class)->findOneBy([
            'id' => (int) ($data['typeId'] ?? 0), 'tenant' => $tenant,
        ]);
        $resource = $this->entityManager->getRepository(AppointmentResource::class)->findOneBy([
            'id' => (int) ($data['resourceId'] ?? 0), 'tenant' => $tenant, 'isActive' => true,
        ]);
        $guestName = trim((string) ($data['guestName'] ?? ''));
        $customerId = $data['customerId'] ?? null;
        $hasCustomer = is_int($customerId) && $customerId > 0;
        $customer = $hasCustomer ? $this->entityManager->getRepository(User::class)->find($customerId) : null;
        $membership = $customer instanceof User ? $this->memberships->findForUserAndTenant($customer, $tenant) : null;
        $note = trim((string) ($data['note'] ?? ''));
        $localDateTime = (string) ($data['date'] ?? '').' '.(string) ($data['time'] ?? '');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $localDateTime, new \DateTimeZone('Europe/Vienna'));

        if (!$type instanceof AppointmentType || !$resource instanceof AppointmentResource
            || ($hasCustomer === ($guestName !== ''))
            || ($hasCustomer && (!$membership instanceof TenantMembership || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)))
            || (!$hasCustomer && $customerId !== null)
            || mb_strlen($guestName) > 160 || mb_strlen($note) > 1000
            || !$start instanceof \DateTimeImmutable || $start->format('Y-m-d H:i') !== $localDateTime
            || $start <= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Vienna'))
        ) {
            return new JsonResponse(['message' => 'Bitte Termin, Person und Kundenangaben prüfen.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $createdAppointment = null;
        $response = $this->entityManager->wrapInTransaction(function () use ($tenant, $type, $resource, $customer, $guestName, $start, $note, &$createdAppointment): JsonResponse {
            $this->entityManager->lock($resource, LockMode::PESSIMISTIC_WRITE);
            $end = $start->modify('+'.$type->getDurationMinutes().' minutes');
            if ($this->blocker->blocks($tenant, $type, $start, $end)) {
                return new JsonResponse(['message' => 'Dieser Zeitraum ist gesperrt.'], JsonResponse::HTTP_CONFLICT);
            }
            $occupiedEnd = $end->modify('+'.$type->getBufferMinutes().' minutes');
            $appointments = $this->entityManager->getRepository(Appointment::class)->findBy([
                'resource' => $resource, 'status' => 'reserved',
            ]);
            foreach ($appointments as $appointment) {
                $existingEnd = $appointment->getEndsAt()->modify('+'.$appointment->getType()->getBufferMinutes().' minutes');
                if ($appointment->getStartsAt() < $occupiedEnd && $existingEnd > $start) {
                    return new JsonResponse(['message' => 'Diese Person ist zu dieser Zeit bereits gebucht.'], JsonResponse::HTTP_CONFLICT);
                }
            }

            $appointment = new Appointment($tenant, $resource, $type, $customer, $start, $end, $note ?: null, $guestName ?: null);
            $this->entityManager->persist($appointment);
            $this->entityManager->flush();
            $createdAppointment = $appointment;

            return new JsonResponse(['appointment' => $this->serialize($appointment)], JsonResponse::HTTP_CREATED);
        });

        if ($createdAppointment instanceof Appointment) {
            $this->staffNotifier?->notify($createdAppointment);
        }

        if ($createdAppointment instanceof Appointment && $customer instanceof User) {
            try {
                $this->mailer->send(
                    $tenant,
                    (new Email())
                        ->from($this->mailFrom)
                        ->to($customer->getEmail())
                        ->subject('Ein Termin wurde für Sie reserviert')
                        ->text(sprintf(
                            "Guten Tag %s,\n\nIhre Apotheke hat für Sie einen Termin reserviert:\n\n%s\n%s Uhr\nAnsprechperson: %s\n\nIhre Termine finden Sie in der App:\n%s/termine/meine\n",
                            $customer->getDisplayName(),
                            $type->getTitle(),
                            $start->format('d.m.Y H:i'),
                            $resource->getName(),
                            rtrim($this->clientUrl, '/'),
                        )),
                    'appointment_created_by_staff',
                );
            } catch (\Throwable $exception) {
                $this->logger->warning('appointment.booking_email.failed', [
                    'appointmentId' => $createdAppointment->getId(),
                    'errorClass' => $exception::class,
                ]);
            }
            $this->push->scheduleAppointmentBooking($createdAppointment->getId());
        }

        return $response;
    }

    #[Route('/api/v1/admin/appointments', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $appointments = $this->entityManager->createQueryBuilder()
            ->select('appointment')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant')
            ->setParameter('tenant', $this->activeTenant->get())
            ->orderBy('appointment.startsAt', 'ASC')
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'appointments' => array_map(
                fn (Appointment $appointment) => $this->serialize($appointment),
                $appointments,
            ),
        ]);
    }

    #[Route('/api/v1/admin/appointments/customer-cancellations', methods: ['GET'])]
    public function customerCancellations(): JsonResponse
    {
        $membership = $this->adminMembership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }
        $notices = $this->entityManager->createQueryBuilder()
            ->select('notice')
            ->from(AppointmentCancellationNotice::class, 'notice')
            ->where('notice.tenant = :tenant')
            ->andWhere('notice.id > :lastId')
            ->setParameter('tenant', $this->activeTenant->get())
            ->setParameter('lastId', $membership->getLastAcknowledgedAppointmentCancellationId() ?? 0)
            ->orderBy('notice.id', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return new JsonResponse(['cancellations' => array_map(static fn (AppointmentCancellationNotice $notice): array => [
            'id' => $notice->getId(),
            'appointmentId' => $notice->getAppointment()->getId(),
            'customer' => $notice->getAppointment()->getDisplayName(),
            'startsAt' => $notice->getAppointment()->getStartsAt()->format(DATE_ATOM),
            'occurredAt' => $notice->getOccurredAt()->format(DATE_ATOM),
        ], $notices)]);
    }

    #[Route('/api/v1/admin/appointments/customer-cancellations/acknowledge', methods: ['POST'])]
    public function acknowledgeCustomerCancellations(Request $request): JsonResponse
    {
        $membership = $this->adminMembership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }
        $throughId = $request->toArray()['throughId'] ?? null;
        if (!is_int($throughId) || $throughId < 1) {
            return new JsonResponse(['message' => 'Ungültiger Lesestand.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $latestId = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(MAX(notice.id), 0)')
            ->from(AppointmentCancellationNotice::class, 'notice')
            ->where('notice.tenant = :tenant')
            ->setParameter('tenant', $this->activeTenant->get())
            ->getQuery()
            ->getSingleScalarResult();
        $membership->acknowledgeAppointmentCancellationsThrough(min($throughId, $latestId));
        $this->entityManager->flush();

        return new JsonResponse(['acknowledgedThroughId' => $membership->getLastAcknowledgedAppointmentCancellationId()]);
    }

    #[Route('/api/v1/admin/appointments/unseen-count', methods: ['GET'])]
    public function unseenCount(): JsonResponse
    {
        $membership = $this->adminMembership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($membership->getLastSeenAppointmentId() === null) {
            $membership->markAppointmentsSeenThrough($this->latestAppointmentId());
            $this->entityManager->flush();
        }

        return new JsonResponse(['count' => $this->countUnseen($membership)]);
    }

    #[Route('/api/v1/admin/appointments/seen', methods: ['POST'])]
    public function markSeen(Request $request): JsonResponse
    {
        $membership = $this->adminMembership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $throughId = $request->toArray()['throughId'] ?? null;
        if (!is_int($throughId) || $throughId < 0) {
            return new JsonResponse(['message' => 'Ungültiger Lesestand.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->markAppointmentsSeenThrough(min($throughId, $this->latestAppointmentId()));
        $this->entityManager->flush();

        return new JsonResponse(['count' => $this->countUnseen($membership)]);
    }

    #[Route('/api/v1/admin/appointments/{id}/cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $appointment = $this->findTenantAppointment($id);
        if (!$appointment instanceof Appointment) {
            return new JsonResponse(['message' => 'Nicht gefunden.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $appointment->cancel();
        $this->entityManager->flush();

        if ($appointment->getCustomer() !== null && ($request->toArray()['notifyCustomer'] ?? true) === true) {
            try {
                $this->mailer->send(
                    $this->activeTenant->get(),
                    (new Email())
                        ->from($this->mailFrom)
                        ->to($appointment->getCustomer()->getEmail())
                        ->subject('Ihr Termin wurde storniert')
                        ->text(sprintf(
                            'Ihr Termin für %s am %s Uhr wurde storniert.',
                            $appointment->getType()->getTitle(),
                            $appointment->getStartsAt()->format('d.m.Y H:i'),
                        )),
                    'appointment_cancelled',
                );
            } catch (\Throwable) {
                // A mail delivery issue must not prevent the appointment cancellation.
            }
        }

        return new JsonResponse(['appointment' => $this->serialize($appointment)]);
    }

    private function findTenantAppointment(int $id): ?Appointment
    {
        $appointment = $this->entityManager->createQueryBuilder()
            ->select('appointment')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.id = :id')
            ->andWhere('appointment.tenant = :tenant')
            ->setParameter('id', $id)
            ->setParameter('tenant', $this->activeTenant->get())
            ->getQuery()
            ->getOneOrNullResult();

        return $appointment instanceof Appointment ? $appointment : null;
    }

    private function isAdmin(): bool
    {
        return $this->adminMembership() !== null;
    }

    #[Route('/api/v1/admin/appointments/{id}/chat', methods: ['POST'])]
    public function linkChat(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }
        $appointment = $this->findTenantAppointment($id);
        if (!$appointment instanceof Appointment) {
            return new JsonResponse(['message' => 'Nicht gefunden.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $customer = $appointment->getCustomer();
        if (!$customer instanceof User) {
            return new JsonResponse(['message' => 'Für Gäste ohne App-Konto ist kein Chat möglich.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($appointment->getChatConversation() instanceof ChatConversation) {
            return new JsonResponse(['conversationId' => $appointment->getChatConversation()->id]);
        }

        $data = $request->toArray();
        $chatId = $data['conversationId'] ?? null;
        if ($chatId !== null && (!is_int($chatId) || $chatId < 1)) {
            return new JsonResponse(['message' => 'Ungültiges Gespräch.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->entityManager->wrapInTransaction(function () use ($appointment, $customer, $chatId): JsonResponse {
            $membership = $this->memberships->findForUserAndTenant($customer, $this->activeTenant->get());
            if (!$membership instanceof TenantMembership || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) {
                return new JsonResponse(['message' => 'Das Kundenkonto ist nicht mehr verfügbar.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->entityManager->lock($membership, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($appointment, LockMode::PESSIMISTIC_WRITE);
            $conversation = $appointment->getChatConversation();
            if (!$conversation instanceof ChatConversation && $chatId !== null) {
                $conversation = $this->entityManager->getRepository(ChatConversation::class)->findOneBy([
                    'id' => $chatId, 'tenant' => $this->activeTenant->get(), 'customer' => $customer,
                ]);
                if (!$conversation instanceof ChatConversation) {
                    return new JsonResponse(['message' => 'Dieses Gespräch gehört nicht zum Kunden.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
            if (!$conversation instanceof ChatConversation) {
                $conversation = $this->entityManager->getRepository(ChatConversation::class)->findOneBy([
                    'tenant' => $this->activeTenant->get(), 'customer' => $customer, 'status' => 'open',
                ]);
            }
            if (!$conversation instanceof ChatConversation) {
                if ($this->chatCipher === null) {
                    return new JsonResponse(['message' => 'Chat ist nicht verfügbar.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
                }
                $previous = $this->entityManager->createQueryBuilder()
                    ->select('conversation')
                    ->from(ChatConversation::class, 'conversation')
                    ->where('conversation.tenant = :tenant AND conversation.customer = :customer')
                    ->andWhere('conversation.consentVersion = :version AND conversation.consentedAt IS NOT NULL')
                    ->setParameter('tenant', $this->activeTenant->get())
                    ->setParameter('customer', $customer)
                    ->setParameter('version', ApiChatController::CONSENT_VERSION)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                $conversation = new ChatConversation($this->activeTenant->get(), $customer);
                if ($previous instanceof ChatConversation && $previous->consentedAt !== null) {
                    $conversation->recordConsent(ApiChatController::CONSENT_VERSION, $previous->consentedAt);
                }
                $this->entityManager->persist($conversation);
                $this->entityManager->flush();
                $conversation->encryptedSubject = $this->chatCipher->encrypt(
                    'Termin '.$appointment->getStartsAt()->format('d.m.Y H:i'),
                    'chat:'.$conversation->tenant->getId().':'.$conversation->id.':subject',
                );
            }
            $appointment->setChatConversation($conversation);
            $this->entityManager->flush();
            return new JsonResponse(['conversationId' => $conversation->id]);
        });
    }

    #[Route('/api/v1/admin/appointments/{id}/chats', methods: ['GET'])]
    public function availableChats(int $id): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }
        $appointment = $this->findTenantAppointment($id);
        if (!$appointment instanceof Appointment) {
            return new JsonResponse(['message' => 'Nicht gefunden.'], JsonResponse::HTTP_NOT_FOUND);
        }
        if ($appointment->getCustomer() === null) {
            return new JsonResponse(['conversations' => []]);
        }
        $conversations = $this->entityManager->getRepository(ChatConversation::class)->findBy([
            'tenant' => $this->activeTenant->get(), 'customer' => $appointment->getCustomer(),
        ], ['updatedAt' => 'DESC'], 30);

        return new JsonResponse(['conversations' => array_map(static fn (ChatConversation $conversation): array => [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'updatedAt' => $conversation->updatedAt->format(DATE_ATOM),
        ], $conversations)]);
    }

    private function adminMembership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());

        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles())
            ? $membership
            : null;
    }

    private function latestAppointmentId(): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(MAX(appointment.id), 0)')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant')
            ->setParameter('tenant', $this->activeTenant->get())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countUnseen(TenantMembership $membership): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(appointment.id)')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant')
            ->andWhere('appointment.id > :lastSeenId')
            ->andWhere('appointment.status = :status')
            ->setParameter('tenant', $this->activeTenant->get())
            ->setParameter('lastSeenId', $membership->getLastSeenAppointmentId() ?? 0)
            ->setParameter('status', 'reserved')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, mixed> */
    private function serialize(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'customer' => $appointment->getDisplayName(),
            'customerId' => $appointment->getCustomer()?->getId(),
            'type' => $appointment->getType()->getTitle(),
            'resource' => $appointment->getResource()->getName(),
            'resourceId' => $appointment->getResource()->getId(),
            'resourceColor' => $appointment->getResource()->getColor(),
            'startsAt' => $appointment->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $appointment->getEndsAt()->format(DATE_ATOM),
            'status' => $appointment->getStatus(),
            'note' => $appointment->getCustomerNote(),
            'chatConversationId' => $appointment->getChatConversation()?->id,
        ];
    }
}
