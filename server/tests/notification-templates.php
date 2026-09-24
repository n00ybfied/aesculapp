<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$tenant = (new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']))->get();
$templates = new App\Service\NotificationTemplates($em);

function checkTemplate(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$connection = $em->getConnection();
$connection->beginTransaction();
try {
    $listed = array_column($templates->listFor($tenant), null, 'key');
    foreach (App\Service\NotificationTemplateCatalog::DEFINITIONS as $key => $definition) {
        $text = $definition['title'].$definition['body'];
        preg_match_all('/{{([^{}]+)}}/', $text, $matches);
        foreach ($matches[1] as $tag) {
            checkTemplate(in_array($tag, $definition['tags'], true), $key.' has an undeclared insert tag: '.$tag);
        }
        foreach ($definition['required'] as $tag) {
            checkTemplate(str_contains($definition['body'], '{{'.$tag.'}}'), $key.' omits a required tag in its default.');
        }
    }
    checkTemplate(str_contains($listed['email_verification']['body'], '{{action_url}}'), 'The real default text and action tag must be visible.');
    checkTemplate(!str_contains($listed['email_verification']['body'], '{{original_body}}'), 'The old generic placeholder must not be shown.');

    $templates->save($tenant, 'email_verification', 'Bitte bestätigen', "Klicken Sie hier:\n{{action_url}}");
    $rendered = $templates->render($tenant, 'email_verification', 'Old title', 'Old body', ['action_url' => 'https://example.invalid/confirm?token=abc']);
    checkTemplate($rendered['title'] === 'Bitte bestätigen', 'The custom subject was not rendered.');
    checkTemplate($rendered['body'] === "Klicken Sie hier:\nhttps://example.invalid/confirm?token=abc", 'The action link was not rendered.');

    try {
        $templates->save($tenant, 'email_verification', 'Ungültig', 'Der Link fehlt.');
        throw new RuntimeException('A missing action tag was accepted.');
    } catch (InvalidArgumentException) {
        // A confirmation link must never disappear from an edited message.
    }

    try {
        $templates->save($tenant, 'email_verification', 'Ungültig', '{{unknown}} {{action_url}}');
        throw new RuntimeException('An unknown insert tag was accepted.');
    } catch (InvalidArgumentException) {
    }

    $templates->save($tenant, 'push_appointment_booking', 'Termin', 'Am {{appointment_date}} um {{appointment_time}} Uhr.');
    $push = $templates->render($tenant, 'push_appointment_booking', 'Old title', 'Old body', ['appointment_date' => '25.09.2026', 'appointment_time' => '10:30']);
    checkTemplate($push['body'] === 'Am 25.09.2026 um 10:30 Uhr.', 'The appointment push values were not rendered.');

    echo "Notification templates: defaults, insertion, required tags and validation passed.\n";
} finally {
    $connection->rollBack();
    $em->clear();
    $kernel->shutdown();
}
