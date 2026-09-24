<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Appointment;
use App\Service\ChatPushService;
use App\Service\TenantMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:appointments:send-reminders',
    description: 'Sends email and push reminders for appointments on the following day.',
)]
final class SendAppointmentRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantMailer $mailer,
        private readonly ChatPushService $push,
        #[Autowire('%app.auth.client_url%')]
        private readonly string $clientUrl,
        #[Autowire('%app.auth.mail_from%')]
        private readonly string $mailFrom,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timezone = new \DateTimeZone('Europe/Vienna');
        $tomorrow = (new \DateTimeImmutable('tomorrow', $timezone))->setTime(0, 0);
        $dayAfterTomorrow = $tomorrow->modify('+1 day');
        $appointments = $this->entityManager->createQueryBuilder()
            ->select('appointment')
            ->from(Appointment::class, 'appointment')
            ->where('appointment.status = :status')
            ->andWhere('appointment.startsAt >= :tomorrow')
            ->andWhere('appointment.startsAt < :dayAfterTomorrow')
            ->setParameter('status', 'reserved')
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('dayAfterTomorrow', $dayAfterTomorrow)
            ->getQuery()
            ->getResult();

        $emailsSent = 0;
        $pushesSent = 0;
        foreach ($appointments as $appointment) {
            if (!$appointment instanceof Appointment || $appointment->getCustomer() === null) {
                continue;
            }

            if (!$appointment->isReminderEmailSent()) {
                try {
                    $this->mailer->send(
                        $appointment->getTenant(),
                        (new Email())
                            ->from($this->mailFrom)
                            ->to($appointment->getCustomer()->getEmail())
                            ->subject('Erinnerung: Ihr Termin ist morgen')
                            ->text($this->emailText($appointment)),
                        'appointment_reminder',
                    );
                    $appointment->markReminderEmailSent();
                    ++$emailsSent;
                } catch (\Throwable) {
                    $output->writeln(sprintf('E-Mail-Erinnerung für Termin #%d konnte nicht versendet werden.', $appointment->getId()));
                }
            }

            if (!$appointment->isReminderPushSent() && $this->push->sendAppointmentReminder(
                $appointment->getCustomer(),
                $appointment->getTenant(),
                sprintf('Sie haben morgen um %s Uhr einen Termin in Ihrer Apotheke.', $appointment->getStartsAt()->format('H:i')),
            )) {
                $appointment->markReminderPushSent();
                ++$pushesSent;
            }
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('%d E-Mail- und %d Push-Erinnerungen verarbeitet.', $emailsSent, $pushesSent));

        return Command::SUCCESS;
    }

    private function emailText(Appointment $appointment): string
    {
        return sprintf(
            "Guten Tag %s,\n\nSie haben morgen einen Termin in Ihrer Apotheke:\n\n%s\n%s Uhr\nAnsprechperson: %s\n\nIhre Termine finden Sie in der App:\n%s/termine/meine\n",
            $appointment->getCustomer()->getDisplayName(),
            $appointment->getType()->getTitle(),
            $appointment->getStartsAt()->format('d.m.Y H:i'),
            $appointment->getResource()->getName(),
            rtrim($this->clientUrl, '/'),
        );
    }
}
