<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

final class AppointmentCustomerNotifier
{
    public function __construct(
        private readonly TenantMailer $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.auth.mail_from%')] private readonly string $mailFrom,
        #[Autowire('%app.auth.client_url%')] private readonly string $clientUrl,
    ) {
    }

    public function pending(Appointment $appointment): void
    {
        $this->send($appointment, 'Ihr Termin wartet auf Bestätigung',
            'Ihr Termin wartet auf die Bestätigung der durchführenden Person.', 'appointment_pending_staff_confirmation');
    }

    public function confirmed(Appointment $appointment): void
    {
        $this->send($appointment, 'Ihr Termin wurde bestätigt',
            'Ihr Termin wurde bestätigt und ist jetzt verbindlich reserviert.', 'appointment_staff_confirmed');
    }

    private function send(Appointment $appointment, string $subject, string $intro, string $type): void
    {
        $customer = $appointment->getCustomer();
        if ($customer === null) return;
        $person = $appointment->getTenant()->showsAppointmentStaffNames()
            ? "\nAnsprechperson: ".$appointment->getResource()->getName() : '';
        try {
            $this->mailer->send($appointment->getTenant(), (new Email())
                ->from($this->mailFrom)
                ->to($customer->getEmail())
                ->subject($subject)
                ->text(sprintf(
                    "Guten Tag %s,\n\n%s\n\n%s\n%s Uhr%s\n\nIhre Termine finden Sie in der App:\n%s/termine/meine\n",
                    $customer->getDisplayName(), $intro, $appointment->getType()->getTitle(),
                    $appointment->getStartsAt()->format('d.m.Y H:i'), $person, rtrim($this->clientUrl, '/'),
                )), $type);
        } catch (\Throwable $exception) {
            $this->logger->warning('appointment.customer_email.failed', [
                'appointmentId' => $appointment->getId(), 'errorClass' => $exception::class,
            ]);
        }
    }
}
