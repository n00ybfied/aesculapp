<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\TenantMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    ) {
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

        if (($request->toArray()['notifyCustomer'] ?? true) === true) {
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
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());

        return $membership !== null
            && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }

    /** @return array{id:int,customer:string,type:string,resource:string,startsAt:string,endsAt:string,status:string} */
    private function serialize(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'customer' => $appointment->getCustomer()->getDisplayName(),
            'type' => $appointment->getType()->getTitle(),
            'resource' => $appointment->getResource()->getName(),
            'startsAt' => $appointment->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $appointment->getEndsAt()->format(DATE_ATOM),
            'status' => $appointment->getStatus(),
        ];
    }
}
