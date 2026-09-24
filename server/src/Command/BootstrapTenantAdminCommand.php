<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:bootstrap:tenant-admin',
    description: 'Creates the first tenant and administrator in an empty installation.',
)]
final class BootstrapTenantAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly \App\Service\UsernameReservation $usernameReservation,
        #[Autowire('%env(APP_TENANT_SLUG)%')] private readonly string $tenantSlug,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->isInteractive()) {
            $io->error('Dieser Befehl benötigt ein interaktives Terminal mit verdeckter Passworteingabe.');
            return Command::FAILURE;
        }

        if ($this->entityManager->getRepository(Tenant::class)->count([]) > 0
            || $this->entityManager->getRepository(User::class)->count([]) > 0) {
            $io->error('Die Installation enthält bereits Mandanten oder Benutzer. Es wurde nichts geändert.');
            return Command::FAILURE;
        }

        $slug = trim($this->tenantSlug);
        if ($slug === '') {
            $io->error('APP_TENANT_SLUG fehlt.');
            return Command::FAILURE;
        }

        $tenantName = trim((string) $io->ask('Name der Apotheke'));
        $username = mb_strtolower(trim((string) $io->ask('Admin-Benutzername')));
        $email = mb_strtolower(trim((string) $io->ask('Admin-E-Mail-Adresse')));
        $displayName = trim((string) $io->ask('Anzeigename des Admins'));

        if ($tenantName === '' || mb_strlen($tenantName) > 160
            || preg_match('/^[a-z][a-z0-9._-]{2,39}$/', $username) !== 1
            || $this->usernameReservation->isReserved($username)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 180
            || $displayName === '' || mb_strlen($displayName) > 160) {
            $io->error('Ungültige Eingabe. Der Benutzername muss 3–40 Zeichen (a–z, 0–9, Punkt, Minus, Unterstrich) haben.');
            return Command::FAILURE;
        }

        $passwordQuestion = new Question('Admin-Passwort (mindestens 14 Zeichen)');
        $passwordQuestion->setHidden(true)->setHiddenFallback(false);
        $password = $io->askQuestion($passwordQuestion);

        $confirmationQuestion = new Question('Admin-Passwort wiederholen');
        $confirmationQuestion->setHidden(true)->setHiddenFallback(false);
        $confirmation = $io->askQuestion($confirmationQuestion);

        if (!is_string($password) || mb_strlen($password) < 14 || $password !== $confirmation) {
            $io->error('Die Passwörter stimmen nicht überein oder sind kürzer als 14 Zeichen.');
            return Command::FAILURE;
        }

        try {
            $this->entityManager->wrapInTransaction(function () use (
                $tenantName,
                $slug,
                $username,
                $email,
                $displayName,
                $password,
            ): void {
                $tenant = new Tenant($tenantName, $slug);
                $user = new User($username, $email, $displayName);
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));

                $this->entityManager->persist($tenant);
                $this->entityManager->persist($user);
                $this->entityManager->persist(new TenantMembership($tenant, $user, ['ROLE_TENANT_ADMIN']));
            });
        } catch (\Throwable) {
            $io->error('Die Erstanlage ist fehlgeschlagen und wurde zurückgerollt. Bitte Serverlog prüfen.');
            return Command::FAILURE;
        }

        $io->success(sprintf('Mandant und Admin "%s" wurden angelegt. Das Passwort wurde nicht ausgegeben.', $username));
        return Command::SUCCESS;
    }
}
