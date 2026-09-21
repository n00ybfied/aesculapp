<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coupon;
use App\Entity\NewsPost;
use App\Entity\Reward;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:demo-images',
    description: 'Adds category-specific demo images only to catalog entries without an image.',
)]
final class SeedDemoImagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->activeTenant->get();
        $news = $this->entityManager->createQueryBuilder()
            ->update(NewsPost::class, 'post')
            ->set('post.imagePath', ':image')
            ->where('post.tenant = :tenant')
            ->andWhere('post.imagePath IS NULL')
            ->setParameter('image', '/uploads/demo/news-herbs.png')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->execute();
        $rewards = $this->entityManager->createQueryBuilder()
            ->update(Reward::class, 'reward')
            ->set('reward.imagePath', ':image')
            ->where('reward.tenant = :tenant')
            ->andWhere('reward.imagePath = :empty')
            ->setParameter('image', '/uploads/demo/reward-wellness.png')
            ->setParameter('empty', '')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->execute();
        $coupons = $this->entityManager->createQueryBuilder()
            ->update(Coupon::class, 'coupon')
            ->set('coupon.imagePath', ':image')
            ->where('coupon.tenant = :tenant')
            ->andWhere('coupon.imagePath = :empty')
            ->setParameter('image', '/uploads/demo/coupon-fresh.png')
            ->setParameter('empty', '')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->execute();

        $output->writeln(sprintf('Demo images added: %d news, %d rewards, %d coupons.', $news, $rewards, $coupons));

        return Command::SUCCESS;
    }
}
