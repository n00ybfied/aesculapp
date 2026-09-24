<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coupon;
use App\Entity\NewsPost;
use App\Entity\Reward;
use App\Entity\Tenant;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:apotheke-test-catalog',
    description: 'Adds clearly labelled sample news, coupons and rewards to the pharmacy test tenant.',
)]
final class SeedApothekeTestCatalogCommand extends Command
{
    private const TENANT_SLUG = 'stadtapotheke-trofaiach';
    private const NEWS_IMAGE = '/uploads/demo/news-herbs.png';
    private const COUPON_IMAGE = '/uploads/demo/coupon-fresh.png';
    private const REWARD_IMAGE = '/uploads/demo/reward-wellness.png';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('confirm', null, InputOption::VALUE_NONE, 'Confirm that visible fictional test content may be added.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('confirm')) {
            $output->writeln('<error>No data added. Pass --confirm to add visible fictional test content.</error>');
            return Command::FAILURE;
        }

        $tenant = $this->activeTenant->get();
        if ($tenant->getSlug() !== self::TENANT_SLUG) {
            $output->writeln('<error>No data added. This seed is limited to the Stadtapotheke Trofaiach tenant.</error>');
            return Command::FAILURE;
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Vienna'));
        $news = $this->seedNews($tenant, $today);
        $coupons = $this->seedCoupons($tenant, $today);
        $rewards = $this->seedRewards($tenant, $today);
        $this->entityManager->flush();

        $output->writeln(sprintf('Apotheken-Testkatalog: %d Inhalte, %d Gutscheine und %d Prämien neu angelegt.', $news, $coupons, $rewards));
        $output->writeln('Vorhandene Einträge mit gleichem Titel wurden nicht verändert.');

        return Command::SUCCESS;
    }

    private function seedNews(Tenant $tenant, \DateTimeImmutable $today): int
    {
        $items = [
            ['Reiseapotheke planen', 'Gut vorbereitet in den Urlaub starten.', 'Wir zeigen, welche Fragen bei der Zusammenstellung einer Reiseapotheke hilfreich sind. Besprechen Sie Ihre Reisepläne und persönlichen Bedürfnisse mit unserem Team.'],
            ['Blutdruckmessung vor Ort', 'Ein kurzer Gesundheitscheck in der Apotheke.', 'Fragen Sie unser Team nach einer Blutdruckmessung und der passenden Einordnung Ihres Messergebnisses.'],
            ['Hausapotheke überprüfen', 'Ablaufdaten und Aufbewahrung im Blick behalten.', 'Ein regelmäßiger Blick in die Hausapotheke hilft, den Überblick über vorhandene Produkte zu behalten. Bei Fragen zur Aufbewahrung beraten wir Sie gern.'],
            ['Sonnenschutz im Alltag', 'Auch kurze Wege finden draußen statt.', 'Wir helfen bei der Auswahl eines Sonnenschutzes, der zu Hauttyp, Alltag und Reiseplänen passt.'],
            ['Hautpflege im Herbst', 'Pflege für die kühlere Jahreszeit auswählen.', 'Wenn sich Ihre Haut im Herbst anders anfühlt, besprechen wir mit Ihnen eine passende Pflegeroutine.'],
            ['Medikamente richtig anwenden', 'Fragen zur Einnahme? Wir sind für Sie da.', 'Bringen Sie Ihre Fragen zu Einnahmezeit, Anwendung und Aufbewahrung mit. Individuelle Arzneimittelfragen klären wir im persönlichen Gespräch.'],
            ['Gut vorbereitet zum Beratungstermin', 'Ein paar Angaben erleichtern das Gespräch.', 'Notieren Sie Ihre Fragen und bringen Sie bei Bedarf eine aktuelle Liste Ihrer Arzneimittel mit.'],
            ['Pflege für Hände und Lippen', 'Kleine Routinen für trockene Tage.', 'Unser Team stellt Ihnen auf Wunsch verschiedene Pflegeprodukte für Hände und Lippen vor.'],
            ['Die Apotheke auf Reisen', 'Wichtige Produkte griffbereit halten.', 'Ob Städtetrip oder Wanderung: Gemeinsam finden wir eine praktische Zusammenstellung für unterwegs.'],
            ['Gesundheit im Familienalltag', 'Persönliche Beratung für Groß und Klein.', 'Bei Fragen zu Pflege, Anwendung und geeigneten Produkten für die Familie nehmen wir uns Zeit für Sie.'],
            ['Gut durch die kalte Jahreszeit', 'Beratung rund um typische Winterfragen.', 'Wir unterstützen Sie bei der Auswahl apothekenüblicher Pflege- und Gesundheitsprodukte für die kalte Jahreszeit.'],
            ['Das Team stellt sich vor', 'Lernen Sie Ihre Ansprechpartner kennen.', 'In unserer Apotheke beraten wir Sie persönlich und beantworten Ihre Fragen zu Produkten und Dienstleistungen.'],
        ];

        $created = 0;
        foreach ($items as $index => [$topic, $subtitle, $text]) {
            $title = 'TEST · '.$topic;
            if ($this->exists(NewsPost::class, $tenant, $title)) continue;

            $this->entityManager->persist(new NewsPost(
                $tenant,
                $title,
                $subtitle,
                '<p><strong>Fiktiver Testinhalt – keine aktuelle Ankündigung der Apotheke.</strong></p><p>'.$text.'</p>',
                self::NEWS_IMAGE,
                true,
                $today->modify(sprintf('-%d days', $index))->setTime(9, 0),
                null,
                null,
            ));
            ++$created;
        }

        return $created;
    }

    private function seedCoupons(Tenant $tenant, \DateTimeImmutable $today): int
    {
        $items = [
            ['Pflegeprobe für trockene Hände', 'Eine kleine Probe zum Kennenlernen.', 'Muster für eine Handpflegeprobe, solange der Testvorrat reicht.'],
            ['5 % auf ausgewählte Hautpflege', 'Passende Pflege für den Alltag.', 'Musteraktion auf ausgewählte Kosmetik- und Pflegeprodukte.'],
            ['Kräutertee-Kostprobe', 'Ein kleiner Genussmoment.', 'Musteraktion für eine Teeprobe bei einem Einkauf.'],
            ['2 Euro auf eine Trinkflasche', 'Praktisch für unterwegs.', 'Musteraktion auf ausgewählte Trinkflaschen.'],
            ['Sonnenschutzprobe', 'Verschiedene Texturen ausprobieren.', 'Musteraktion für eine passende Pflegeprobe, solange der Testvorrat reicht.'],
            ['5 % auf Lippenpflege', 'Pflege für unterwegs.', 'Musteraktion auf ausgewählte Lippenpflegeprodukte.'],
            ['Mini-Beratung zur Hausapotheke', 'Fragen im persönlichen Gespräch klären.', 'Musteraktion für ein kurzes Beratungsgespräch nach Terminvereinbarung.'],
            ['Duftprobe für zuhause', 'Ein Produkt in Ruhe kennenlernen.', 'Musteraktion für eine kleine Duftprobe, solange der Testvorrat reicht.'],
        ];

        $created = 0;
        foreach ($items as [$topic, $subtitle, $details]) {
            $title = 'TEST · '.$topic;
            if ($this->exists(Coupon::class, $tenant, $title)) continue;

            $this->entityManager->persist(new Coupon(
                $tenant,
                $title,
                $subtitle,
                '<p><strong>Fiktiver Testgutschein – kein gültiges Angebot und nicht an der Kasse einlösbar.</strong></p><p>'.$details.'</p>',
                self::COUPON_IMAGE,
                true,
                null,
                $today->modify('+180 days')->setTime(23, 59),
            ));
            ++$created;
        }

        return $created;
    }

    private function seedRewards(Tenant $tenant, \DateTimeImmutable $today): int
    {
        $items = [
            ['Kräutertee-Mischung', 'Eine kleine Auszeit für zuhause.', 250],
            ['Handpflege-Set', 'Pflege für den Alltag.', 450],
            ['Wärmendes Kirschkernkissen', 'Gemütliche Wohlfühlmomente.', 650],
            ['Wiederverwendbare Trinkflasche', 'Ein Begleiter für unterwegs.', 700],
            ['Kleines Erste-Hilfe-Etui', 'Praktisch auf Reisen.', 900],
            ['Stoffbeutel für den Einkauf', 'Leicht und wiederverwendbar.', 200],
            ['Entspannungsball', 'Eine kurze Pause für die Hände.', 400],
            ['Notizbuch für Gesundheitsfragen', 'Fragen und Termine festhalten.', 300],
        ];

        $created = 0;
        foreach ($items as [$topic, $subtitle, $points]) {
            $title = 'TEST · '.$topic;
            if ($this->exists(Reward::class, $tenant, $title)) continue;

            $this->entityManager->persist(new Reward(
                $tenant,
                $title,
                $subtitle,
                '<p><strong>Fiktive Testprämie – keine tatsächliche Warenausgabe.</strong></p><p>'.$subtitle.'</p>',
                self::REWARD_IMAGE,
                $points,
                true,
                null,
                $today->modify('+180 days')->setTime(23, 59),
            ));
            ++$created;
        }

        return $created;
    }

    /** @param class-string<object> $className */
    private function exists(string $className, Tenant $tenant, string $title): bool
    {
        return $this->entityManager->getRepository($className)->findOneBy([
            'tenant' => $tenant,
            'title' => $title,
        ]) !== null;
    }
}
