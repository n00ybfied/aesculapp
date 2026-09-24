<?php

declare(strict_types=1);

namespace App\Service;

final class NotificationTemplateCatalog
{
    /** @var array<string, array{channel: string, label: string, title: string, body: string, tags: list<string>, required: list<string>}> */
    public const DEFINITIONS = [
        'email_verification' => [
            'channel' => 'email', 'label' => 'E-Mail-Adresse bestätigen', 'title' => 'E-Mail-Adresse für Aesculapp bestätigen',
            'body' => "Bitte bestätigen Sie Ihre E-Mail-Adresse innerhalb von 24 Stunden:\n{{action_url}}\n\nErst danach ist Ihr Konto aktiv.",
            'tags' => ['action_url'], 'required' => ['action_url'],
        ],
        'email_change' => [
            'channel' => 'email', 'label' => 'Neue E-Mail-Adresse bestätigen', 'title' => 'Neue E-Mail-Adresse für Aesculapp bestätigen',
            'body' => "Sie haben eine Änderung Ihrer E-Mail-Adresse angefordert.\n\nBestätigen Sie die neue Adresse innerhalb von 24 Stunden:\n{{action_url}}\n\nWenn Sie dies nicht angefordert haben, ignorieren Sie diese E-Mail.",
            'tags' => ['action_url'], 'required' => ['action_url'],
        ],
        'password_reset' => [
            'channel' => 'email', 'label' => 'Passwort zurücksetzen', 'title' => 'Passwort für {{portal_name}} zurücksetzen',
            'body' => "Sie haben angefordert, Ihr Passwort zurückzusetzen.\n\nÖffnen Sie innerhalb von 60 Minuten diesen Link:\n{{action_url}}\n\nWenn Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren.",
            'tags' => ['portal_name', 'action_url'], 'required' => ['action_url'],
        ],
        'admin_invitation' => [
            'channel' => 'email', 'label' => 'Mitarbeitereinladung', 'title' => '{{invitation_prefix}}Einladung zum Aesculapp Apothekenportal',
            'body' => "Sie wurden zum Apothekenportal eingeladen. {{invitation_instruction}}:\n{{action_url}}",
            'tags' => ['invitation_prefix', 'invitation_instruction', 'action_url'], 'required' => ['action_url'],
        ],
        'appointment_assigned_to_staff' => [
            'channel' => 'email', 'label' => 'Neuer Termin für Mitarbeiter', 'title' => 'Neuer Termin für Sie',
            'body' => "Guten Tag {{staff_name}},\n\nIhnen wurde ein neuer Termin zugewiesen.\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr\nKunde: {{customer_name}}\n\nDetails im Adminbereich:\n{{action_url}}\n",
            'tags' => ['staff_name', 'appointment_type', 'appointment_datetime', 'customer_name', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_assigned_to_staff_pending' => [
            'channel' => 'email', 'label' => 'Termin wartet auf Mitarbeiter', 'title' => 'Termin wartet auf Ihre Bestätigung',
            'body' => "Guten Tag {{staff_name}},\n\nIhnen wurde ein Termin zugewiesen. Bitte bestätigen Sie ihn, damit er verbindlich gebucht wird.\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr\nKunde: {{customer_name}}\n\nDetails im Adminbereich:\n{{action_url}}\n",
            'tags' => ['staff_name', 'appointment_type', 'appointment_datetime', 'customer_name', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_pending_staff_confirmation' => [
            'channel' => 'email', 'label' => 'Termin wartet auf Bestätigung', 'title' => 'Ihr Termin wartet auf Bestätigung',
            'body' => "Guten Tag {{customer_name}},\n\nIhr Termin wartet auf die Bestätigung der durchführenden Person.\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr{{staff_line}}\n\nIhre Termine finden Sie in der App:\n{{action_url}}\n",
            'tags' => ['customer_name', 'appointment_type', 'appointment_datetime', 'staff_line', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_staff_confirmed' => [
            'channel' => 'email', 'label' => 'Termin bestätigt', 'title' => 'Ihr Termin wurde bestätigt',
            'body' => "Guten Tag {{customer_name}},\n\nIhr Termin wurde bestätigt und ist jetzt verbindlich reserviert.\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr{{staff_line}}\n\nIhre Termine finden Sie in der App:\n{{action_url}}\n",
            'tags' => ['customer_name', 'appointment_type', 'appointment_datetime', 'staff_line', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_reminder' => [
            'channel' => 'email', 'label' => 'Terminerinnerung', 'title' => 'Erinnerung: Ihr Termin ist morgen',
            'body' => "Guten Tag {{customer_name}},\n\nSie haben morgen einen Termin in Ihrer Apotheke:\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr{{staff_line}}\n\nIhre Termine finden Sie in der App:\n{{action_url}}\n",
            'tags' => ['customer_name', 'appointment_type', 'appointment_datetime', 'staff_line', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_created_by_staff' => [
            'channel' => 'email', 'label' => 'Termin vom Team angelegt', 'title' => 'Ein Termin wurde für Sie reserviert',
            'body' => "Guten Tag {{customer_name}},\n\nIhre Apotheke hat für Sie einen Termin reserviert:\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr{{staff_line}}\n\nIhre Termine finden Sie in der App:\n{{action_url}}\n",
            'tags' => ['customer_name', 'appointment_type', 'appointment_datetime', 'staff_line', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_created_by_staff_pending' => [
            'channel' => 'email', 'label' => 'Termin vom Team angelegt – wartet', 'title' => 'Ihr Termin wartet auf Bestätigung',
            'body' => "Guten Tag {{customer_name}},\n\nIhr Termin wartet auf die Bestätigung der durchführenden Person.\n\n{{appointment_type}}\n{{appointment_datetime}} Uhr{{staff_line}}\n\nIhre Termine finden Sie in der App:\n{{action_url}}\n",
            'tags' => ['customer_name', 'appointment_type', 'appointment_datetime', 'staff_line', 'action_url'],
            'required' => ['appointment_type', 'appointment_datetime', 'action_url'],
        ],
        'appointment_cancelled' => [
            'channel' => 'email', 'label' => 'Termin vom Team storniert', 'title' => 'Ihr Termin wurde storniert',
            'body' => 'Ihr Termin für {{appointment_type}} am {{appointment_datetime}} Uhr wurde storniert.',
            'tags' => ['appointment_type', 'appointment_datetime'], 'required' => ['appointment_type', 'appointment_datetime'],
        ],
        'family_invitation' => [
            'channel' => 'email', 'label' => 'Familieneinladung', 'title' => 'Einladung zum Familienzugang',
            'body' => "{{actor_name}} möchte mit Ihnen einen Familienzugang in AesculApp verbinden. Bestätigen Sie die Einladung innerhalb von sieben Tagen:\n{{action_url}}\n\nDanach legen beide Seiten getrennt fest, welche Medikamentendaten freigegeben werden.",
            'tags' => ['actor_name', 'action_url'], 'required' => ['action_url'],
        ],
        'family_invitation_accepted' => [
            'channel' => 'email', 'label' => 'Familieneinladung angenommen', 'title' => 'Ihre Familien-Einladung wurde angenommen',
            'body' => "{{actor_name}} hat Ihre Einladung zum Familienzugang in AesculApp angenommen. Sie können nun in den Familieneinstellungen getrennt festlegen, welche Medikamentendaten Sie freigeben möchten.\n\n{{action_url}}",
            'tags' => ['actor_name', 'action_url'], 'required' => ['action_url'],
        ],
        'family_invitation_accepted_in_app' => [
            'channel' => 'email', 'label' => 'Familieneinladung in der App angenommen', 'title' => 'Ihre Familien-Einladung wurde angenommen',
            'body' => "{{actor_name}} hat Ihre Einladung zum Familienzugang in AesculApp angenommen.\n\n{{action_url}}",
            'tags' => ['actor_name', 'action_url'], 'required' => ['action_url'],
        ],
        'family_point_sharing_requested' => [
            'channel' => 'email', 'label' => 'Punkteteilung angefragt', 'title' => 'Punkteteilung für Familie angefragt',
            'body' => "{{actor_name}} möchte die Punkte mit Ihnen teilen. Bitte öffnen Sie den Familienzugang und stimmen Sie zu oder lehnen Sie ab.\n\n{{action_url}}",
            'tags' => ['actor_name', 'action_url'], 'required' => ['action_url'],
        ],
        'family_point_sharing_accepted' => [
            'channel' => 'email', 'label' => 'Punkteteilung angenommen', 'title' => 'Punkteteilung wurde angenommen',
            'body' => "{{actor_name}} hat der Punkteteilung zugestimmt.\n\n{{action_url}}",
            'tags' => ['actor_name', 'action_url'], 'required' => ['action_url'],
        ],
        'push_chat_reply' => [
            'channel' => 'push', 'label' => 'Neue Chatantwort', 'title' => 'Neue Antwort',
            'body' => 'Sie haben eine neue Antwort von Ihrer Apotheke.', 'tags' => [], 'required' => [],
        ],
        'push_appointment_booking' => [
            'channel' => 'push', 'label' => 'Neuer Termin', 'title' => 'Neuer Termin',
            'body' => 'Ihre Apotheke hat für Sie am {{appointment_date}} um {{appointment_time}} Uhr einen Termin eingetragen.',
            'tags' => ['appointment_date', 'appointment_time'], 'required' => ['appointment_date', 'appointment_time'],
        ],
        'push_appointment_reminder' => [
            'channel' => 'push', 'label' => 'Terminerinnerung', 'title' => 'Terminerinnerung',
            'body' => 'Sie haben morgen um {{appointment_time}} Uhr einen Termin in Ihrer Apotheke.',
            'tags' => ['appointment_time'], 'required' => ['appointment_time'],
        ],
        'push_family_invitation' => [
            'channel' => 'push', 'label' => 'Familieneinladung', 'title' => 'Familienzugang',
            'body' => 'Sie haben eine neue Einladung zum Familienzugang.', 'tags' => [], 'required' => [],
        ],
        'push_family_invitation_accepted' => [
            'channel' => 'push', 'label' => 'Familieneinladung angenommen', 'title' => 'Familienzugang',
            'body' => 'Eine Einladung zum Familienzugang wurde angenommen.', 'tags' => [], 'required' => [],
        ],
        'push_family_point_sharing_requested' => [
            'channel' => 'push', 'label' => 'Punkteteilung angefragt', 'title' => 'Gemeinsame Punkte',
            'body' => 'Sie haben eine Anfrage zur gemeinsamen Punkteteilung erhalten.', 'tags' => [], 'required' => [],
        ],
        'push_family_point_sharing_accepted' => [
            'channel' => 'push', 'label' => 'Punkteteilung angenommen', 'title' => 'Gemeinsame Punkte',
            'body' => 'Ihre Anfrage zur gemeinsamen Punkteteilung wurde angenommen.', 'tags' => [], 'required' => [],
        ],
        'push_test' => [
            'channel' => 'push', 'label' => 'Test-Push', 'title' => 'AesculApp-Test',
            'body' => 'Push-Benachrichtigungen funktionieren auf diesem Gerät.', 'tags' => [], 'required' => [],
        ],
    ];
}
