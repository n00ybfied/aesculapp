<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;
use App\Repository\TenantMembershipRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

final class AppointmentStaffNotifier
{
    public function __construct(
        private readonly TenantMailer $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailFrom,
        private readonly string $adminUrl,
        private readonly TenantMembershipRepository $memberships,
    ) {
    }

    public function notify(Appointment $appointment): void
    {
        $staff = $appointment->getResource()->getAssignedUser();
        if ($staff === null) {
            return;
        }
        $membership = $this->memberships->findForUserAndTenant($staff, $appointment->getTenant());
        if ($membership === null || !array_intersect(['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'], $membership->getRoles())) {
            return;
        }

        try {
            $this->mailer->send(
                $appointment->getTenant(),
                (new Email())
                    ->from($this->mailFrom)
                    ->to($staff->getEmail())
                    ->subject('Neuer Termin für Sie')
                    ->text(sprintf(
                        "Guten Tag %s,\n\nIhnen wurde ein neuer Termin zugewiesen.\n\n%s\n%s Uhr\nKunde: %s\n\nDetails im Adminbereich:\n%s/termine\n",
                        $staff->getDisplayName(),
                        $appointment->getType()->getTitle(),
                        $appointment->getStartsAt()->format('d.m.Y H:i'),
                        $appointment->getDisplayName(),
                        rtrim($this->adminUrl, '/'),
                    )),
                'appointment_assigned_to_staff',
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('appointment.staff_email.failed', [
                'appointmentId' => $appointment->getId(),
                'errorClass' => $exception::class,
            ]);
        }
    }
}
