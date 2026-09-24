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
        $staff = $appointment->getAssignedUser();
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
                    ->subject($appointment->getStatus() === 'pending_staff_confirmation' ? 'Termin wartet auf Ihre Bestätigung' : 'Neuer Termin für Sie')
                    ->text(sprintf(
                        "Guten Tag %s,\n\n%s\n\n%s\n%s Uhr\nKunde: %s\n\nDetails im Adminbereich:\n%s/meine-termine\n",
                        $staff->getDisplayName(),
                        $appointment->getStatus() === 'pending_staff_confirmation' ? 'Ihnen wurde ein Termin zugewiesen. Bitte bestätigen Sie ihn, damit er verbindlich gebucht wird.' : 'Ihnen wurde ein neuer Termin zugewiesen.',
                        $appointment->getType()->getTitle(),
                        $appointment->getStartsAt()->format('d.m.Y H:i'),
                        $appointment->getDisplayName(),
                        rtrim($this->adminUrl, '/'),
                    )),
                $appointment->getStatus() === 'pending_staff_confirmation' ? 'appointment_assigned_to_staff_pending' : 'appointment_assigned_to_staff',
                [
                    'staff_name' => $staff->getDisplayName(),
                    'appointment_type' => $appointment->getType()->getTitle(),
                    'appointment_datetime' => $appointment->getStartsAt()->format('d.m.Y H:i'),
                    'customer_name' => $appointment->getDisplayName(),
                    'action_url' => rtrim($this->adminUrl, '/').'/meine-termine',
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('appointment.staff_email.failed', [
                'appointmentId' => $appointment->getId(),
                'errorClass' => $exception::class,
            ]);
        }
    }
}
