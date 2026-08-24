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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:sta',
    description: 'Creates the Stadtapotheke Trofaiach tenant and its prototype customer.',
)]
final class SeedStaTenantCommand extends Command
{
    private const TENANT_SLUG = 'stadtapotheke-trofaiach';
    private const USERNAME = 'kunde';
    private const USER_EMAIL = 'kunde@stadtapotheke-trofaiach.test';
    private const USER_PASSWORD = 'trofaiach';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->entityManager->getRepository(Tenant::class)->findOneBy(['slug' => self::TENANT_SLUG]);
        if (!$tenant instanceof Tenant) {
            $tenant = new Tenant('Stadtapotheke Trofaiach', self::TENANT_SLUG);
            $this->entityManager->persist($tenant);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => self::USERNAME]);
        if (!$user instanceof User) {
            $user = new User(self::USERNAME, self::USER_EMAIL, 'Kunde');
            $user->setPassword($this->passwordHasher->hashPassword($user, self::USER_PASSWORD));
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        $membership = $this->entityManager->getRepository(TenantMembership::class)->findOneBy([
            'tenant' => $tenant,
            'user' => $user,
        ]);
        if (!$membership instanceof TenantMembership) {
            $this->entityManager->persist(new TenantMembership($tenant, $user));
            $this->entityManager->flush();
        }

        $output->writeln(sprintf(
            'Tenant #%d and prototype user "%s" are ready.',
            $tenant->getId(),
            $user->getUsername(),
        ));

        return Command::SUCCESS;
    }
}
