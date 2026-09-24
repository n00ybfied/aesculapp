<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Appointment;

final class AppointmentTemplateValues
{
    /** @return array<string, string> */
    public static function customer(Appointment $appointment, string $clientUrl): array
    {
        return [
            'customer_name' => $appointment->getCustomer()?->getDisplayName() ?? $appointment->getDisplayName(),
            'appointment_type' => $appointment->getType()->getTitle(),
            'appointment_datetime' => $appointment->getStartsAt()->format('d.m.Y H:i'),
            'staff_line' => $appointment->getTenant()->showsAppointmentStaffNames()
                ? "\nAnsprechperson: ".$appointment->getResource()->getName() : '',
            'action_url' => rtrim($clientUrl, '/').'/termine/meine',
        ];
    }
}
