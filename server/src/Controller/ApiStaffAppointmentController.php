<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AppointmentCustomerNotifier;
use App\Service\ChatPushService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiStaffAppointmentController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly Security $security,
        private readonly AppointmentCustomerNotifier $notifier,
        private readonly ChatPushService $push,
    ) {
    }

    #[Route('/api/v1/admin/appointments/mine', methods: ['GET'])]
    public function mine(): JsonResponse
    {
        $user = $this->staffUser();
        if ($user === null) return new JsonResponse(['message' => 'Forbidden.'], 403);
        $appointments = $this->entityManager->createQueryBuilder()
            ->select('appointment')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant AND appointment.assignedUser = :user')
            ->andWhere('appointment.endsAt >= :now')
            ->setParameter('tenant', $this->activeTenant->get())
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('appointment.startsAt', 'ASC')
            ->setMaxResults(200)
            ->getQuery()->getResult();

        return new JsonResponse(['appointments' => array_map(fn (Appointment $appointment): array => $this->serialize($appointment), $appointments)]);
    }

    #[Route('/api/v1/admin/appointments/mine/{id}/confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        $user = $this->staffUser();
        if ($user === null) return new JsonResponse(['message' => 'Forbidden.'], 403);

        return $this->confirmAppointment($id, $user);
    }

    #[Route('/api/v1/admin/appointments/{id}/confirm', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function confirmAsAdmin(int $id): JsonResponse
    {
        if (!$this->isTenantAdmin()) return new JsonResponse(['message' => 'Forbidden.'], 403);

        return $this->confirmAppointment($id, null);
    }

    #[Route('/api/v1/admin/appointments/mine/alert', methods: ['GET'])]
    public function alert(): JsonResponse
    {
        $membership = $this->staffMembership();
        if ($membership === null) return new JsonResponse(['message' => 'Forbidden.'], 403);

        $tenant = $this->activeTenant->get();
        $isAdmin = in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true);
        $confirmationEnabled = $tenant->isAppointmentStaffConfirmationEnabled();
        if (!$confirmationEnabled && $membership->getLastSeenAppointmentId() === null) {
            $membership->markAppointmentsSeenThrough($this->latestAppointmentId($isAdmin ? null : $membership->getUser()));
            $this->entityManager->flush();
        }

        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(appointment.id)')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if (!$isAdmin) {
            $query->andWhere('appointment.assignedUser = :user')->setParameter('user', $membership->getUser());
        }
        if ($confirmationEnabled) {
            $query->andWhere('appointment.status = :status AND appointment.startsAt > :now')
                ->setParameter('status', 'pending_staff_confirmation')
                ->setParameter('now', new \DateTimeImmutable());
        } else {
            $query->andWhere('appointment.status = :status AND appointment.id > :lastSeenId')
                ->setParameter('status', 'reserved')
                ->setParameter('lastSeenId', $membership->getLastSeenAppointmentId() ?? 0);
        }

        return new JsonResponse(['mode' => $confirmationEnabled ? 'confirmation' : 'new', 'count' => (int) $query->getQuery()->getSingleScalarResult()]);
    }

    #[Route('/api/v1/admin/appointments/mine/seen', methods: ['POST'])]
    public function markOwnSeen(Request $request): JsonResponse
    {
        $membership = $this->staffMembership();
        if ($membership === null) return new JsonResponse(['message' => 'Forbidden.'], 403);
        if (in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true)) return new JsonResponse(['message' => 'Administratorentermine werden in der Terminverwaltung als gelesen markiert.'], 403);

        $throughId = $request->toArray()['throughId'] ?? null;
        if (!is_int($throughId) || $throughId < 0) return new JsonResponse(['message' => 'Ungültiger Lesestand.'], 422);
        $membership->markAppointmentsSeenThrough(min($throughId, $this->latestAppointmentId($membership->getUser())));
        $this->entityManager->flush();

        return new JsonResponse(['seenThroughId' => $membership->getLastSeenAppointmentId()]);
    }

    private function confirmAppointment(int $id, ?User $assignedUser): JsonResponse
    {

        $confirmed = false;
        $appointment = null;
        $response = $this->entityManager->wrapInTransaction(function () use ($id, $assignedUser, &$confirmed, &$appointment): JsonResponse {
            $query = $this->entityManager->createQueryBuilder()
                ->select('appointment')
                ->from(Appointment::class, 'appointment')
                ->where('appointment.id = :id AND appointment.tenant = :tenant')
                ->setParameter('id', $id)
                ->setParameter('tenant', $this->activeTenant->get());
            if ($assignedUser !== null) {
                $query->andWhere('appointment.assignedUser = :user')->setParameter('user', $assignedUser);
            }
            $appointment = $query->getQuery()->getOneOrNullResult();
            if (!$appointment instanceof Appointment) return new JsonResponse(['message' => 'Nicht gefunden.'], 404);
            $this->entityManager->refresh($appointment, LockMode::PESSIMISTIC_WRITE);
            if ($appointment->getStatus() === 'reserved') return new JsonResponse(['appointment' => $this->serialize($appointment)]);
            if ($appointment->getStatus() !== 'pending_staff_confirmation' || $appointment->getStartsAt() <= new \DateTimeImmutable()) {
                return new JsonResponse(['message' => 'Dieser Termin kann nicht mehr bestätigt werden.'], 409);
            }
            $appointment->confirmByStaff();
            $this->entityManager->flush();
            $confirmed = true;
            return new JsonResponse(['appointment' => $this->serialize($appointment)]);
        });

        if ($confirmed && $appointment instanceof Appointment) {
            $this->notifier->confirmed($appointment);
            if ($appointment->getCustomer() !== null) $this->push->scheduleAppointmentBooking($appointment->getId());
        }
        return $response;
    }

    private function isTenantAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true);
    }

    private function staffUser(): ?User
    {
        return $this->staffMembership()?->getUser();
    }

    private function staffMembership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return null;
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        return $membership !== null && array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles()) ? $membership : null;
    }

    private function latestAppointmentId(?User $assignedUser): int
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(MAX(appointment.id), 0)')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.tenant = :tenant')
            ->setParameter('tenant', $this->activeTenant->get());
        if ($assignedUser !== null) $query->andWhere('appointment.assignedUser = :user')->setParameter('user', $assignedUser);
        return (int) $query->getQuery()->getSingleScalarResult();
    }

    private function serialize(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'customer' => $appointment->getDisplayName(),
            'type' => $appointment->getType()->getTitle(),
            'startsAt' => $appointment->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $appointment->getEndsAt()->format(DATE_ATOM),
            'status' => $appointment->getStatus(),
            'note' => $appointment->getCustomerNote(),
        ];
    }
}
