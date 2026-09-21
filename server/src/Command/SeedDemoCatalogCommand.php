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
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed:demo-catalog',
    description: 'Adds demo news, coupons and rewards with bundled images for pagination and filter tests.',
)]
final class SeedDemoCatalogCommand extends Command
{
    private const NEWS_IMAGE = '/uploads/demo/news-herbs.png';
    private const COUPON_IMAGE = '/uploads/demo/coupon-fresh.png';
    private const REWARD_IMAGE = '/uploads/demo/reward-wellness.png';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActiveTenantProvider $activeTenant,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->activeTenant->get();
        $now = new \DateTimeImmutable('today');

        $createdNews = $this->seedNews($tenant, $now);
        $createdCoupons = $this->seedCoupons($tenant, $now);
        $createdRewards = $this->seedRewards($tenant, $now);

        $this->entityManager->flush();

        $output->writeln(sprintf(
            'Demo catalog ready: %d news, %d coupons and %d rewards added.',
            $createdNews,
            $createdCoupons,
            $createdRewards,
        ));
        $output->writeln('Existing demo entries were kept; the command can be run again safely.');

        return Command::SUCCESS;
    }

    private function seedNews(Tenant $tenant, \DateTimeImmutable $now): int
    {
        $items = [
            ['Herbstfit durch den Alltag', 'Praktische Tipps für ein starkes Immunsystem.', -1, true, null, null],
            ['Blutdruck messen: Wir helfen gern', 'Kostenlose Messung und persönliche Einordnung vor Ort.', -3, true, null, null],
            ['Aktionswoche: Hautpflege', 'Beratung zu sanfter Pflege für die kühlere Jahreszeit.', -5, true, null, null],
            ['Reiseapotheke rechtzeitig planen', 'Was bei Kurzurlaub und Fernreise nicht fehlen sollte.', -7, true, null, null],
            ['Gut durch die Allergiesaison', 'Alltagstipps und passende Produkte für empfindliche Nasen.', -9, true, null, null],
            ['Unser Team bildet sich fort', 'Damit Sie auch künftig kompetent beraten werden.', -11, true, null, null],
            ['Kinder gesund durch den Schulalltag', 'Kleine Helfer für Pausenbox, Sport und Erkältungszeit.', -13, true, null, null],
            ['Mehr trinken leicht gemacht', 'Erinnerungen und Ideen für ausreichend Flüssigkeit.', -15, true, null, null],
            ['Sonnenschutz auch im Alltag', 'UV-Schutz ist nicht nur im Urlaub wichtig.', -17, true, null, null],
            ['Arzneimittel richtig aufbewahren', 'So bleiben Medikamente sicher und wirksam.', -19, true, null, null],
            ['Entspannt einschlafen', 'Unsere Empfehlungen für eine ruhige Abendroutine.', -21, true, null, null],
            ['Gesundheitscheck im Frühling', 'Jetzt einen Termin für Ihre Vorsorge einplanen.', -23, true, null, null],
            ['Bewegungspause für zwischendurch', 'Drei einfache Übungen für Büro und Zuhause.', -25, true, null, null],
            ['Richtig inhalieren bei Erkältung', 'Worauf Sie bei Inhalation und Pflege achten können.', -27, true, null, null],
            ['Vitamin D: Beratung im Herbst', 'Wir erklären, wann eine individuelle Beratung sinnvoll ist.', -29, true, null, null],
            ['Unsere neue Abholstation', 'Schneller und unkomplizierter zu Ihrer Vorbestellung.', -31, true, null, null],
            ['Interne Schulung zum Diabetesmonat', 'Dieser Beitrag ist bewusst nicht in der Kundenansicht sichtbar.', -2, false, null, null],
            ['Entwurf: Weihnachtsöffnungszeiten', 'Dieser Entwurf dient zum Testen des Sichtbarkeitsfilters.', -4, false, null, null],
            ['Interne Checkliste für Beratungstage', 'Nicht veröffentlichte Demo-News für die Administration.', -6, false, null, null],
            ['Entwurf: Neue Messaktion', 'Zum Testen von ausgeblendeten Beiträgen.', -8, false, null, null],
            ['Demnächst: Beratungstag zum Thema Rücken', 'Dieser Beitrag wird erst künftig angezeigt.', 1, true, 7, null],
            ['Vorschau: Winterfit-Aktion', 'Geplanter Beitrag mit spätem Veröffentlichungszeitpunkt.', 2, true, 14, null],
            ['Vergangene Aktion: Sommergesund', 'Die Laufzeit dieses Beitrags ist bereits abgelaufen.', -35, true, null, -2],
            ['Archiv: Aktion zum Weltdiabetestag', 'Abgelaufener Beitrag für den Zeitfilter.', -37, true, null, -5],
        ];

        $created = 0;
        foreach ($items as [$title, $subtitle, $publishedOffset, $visible, $showFromOffset, $showUntilOffset]) {
            if ($this->exists(NewsPost::class, $tenant, $title)) {
                continue;
            }

            $body = sprintf(
                '<p>%s</p><p>Besuchen Sie uns in Ihrer Apotheke. Unser Team nimmt sich Zeit für Ihre Fragen und berät Sie persönlich.</p>',
                $subtitle,
            );
            $this->entityManager->persist(new NewsPost(
                $tenant,
                $title,
                $subtitle,
                $body,
                self::NEWS_IMAGE,
                $visible,
                $now->modify(sprintf('%+d days', $publishedOffset))->setTime(9, 0),
                $showFromOffset === null ? null : $now->modify(sprintf('%+d days', $showFromOffset))->setTime(8, 0),
                $showUntilOffset === null ? null : $now->modify(sprintf('%+d days', $showUntilOffset))->setTime(20, 0),
            ));
            ++$created;
        }

        return $created;
    }

    private function seedCoupons(Tenant $tenant, \DateTimeImmutable $now): int
    {
        $items = [
            ['5 % auf ausgewählte Hautpflege', 'Für Ihre tägliche Pflegeroutine.', 'Einmalig einlösbar auf ausgewählte Pflegeprodukte. Ausgenommen sind rezeptpflichtige Arzneimittel.', true, null, 30],
            ['Gratis Kräutertee-Probe', 'Eine kleine Auszeit für zuhause.', 'Erhalten Sie eine Kräutertee-Probe zu Ihrem Einkauf ab 10 Euro.', true, null, 30],
            ['10 % auf die Reiseapotheke', 'Gut vorbereitet unterwegs.', 'Gültig auf ausgewählte Reiseartikel und apothekenübliche Produkte.', true, null, 21],
            ['Kostenlose Blutdruckmessung', 'Mit kurzer persönlicher Beratung.', 'Lassen Sie Ihren Blutdruck in Ruhe messen und besprechen Sie das Ergebnis mit unserem Team.', true, null, 60],
            ['2 Euro auf Vitaminpräparate', 'Unterstützung für den Alltag.', 'Gültig ab einem Warenwert von 15 Euro auf ausgewählte Vitaminpräparate.', true, null, 30],
            ['Pflegeprobe für empfindliche Haut', 'Sanft ausprobieren.', 'Sie erhalten eine passende Pflegeprobe, solange der Vorrat reicht.', true, null, 45],
            ['5 % auf Kompressionsstrümpfe', 'Beweglich durch den Tag.', 'Einmalig auf ein Paar Kompressionsstrümpfe oder Zubehör einlösbar.', true, null, 40],
            ['Husten- und Halsbonbon gratis', 'Kleine Hilfe für unterwegs.', 'Einlösbar zu einem Einkauf ab 8 Euro.', true, null, 20],
            ['Beratungsgutschein für die Hausapotheke', 'Gemeinsam Ordnung schaffen.', 'Vereinbaren Sie einen kurzen Beratungscheck zu Ihrer Hausapotheke.', true, null, 90],
            ['3 Euro auf Sonnenschutz', 'Gut geschützt in den Tag.', 'Gültig für ausgewählte Sonnenschutzprodukte ab Lichtschutzfaktor 30.', true, null, 50],
            ['Probe: Handcreme mit Urea', 'Wohltuend bei trockenen Händen.', 'Eine Probe pro Kundin oder Kunde, solange der Vorrat reicht.', true, null, 35],
            ['5 % auf Inhalationszubehör', 'Durchatmen leicht gemacht.', 'Gültig auf ausgewähltes Inhalationszubehör und Pflegemittel.', true, null, 30],
            ['Vorschau: Wintertee zum Aktionspreis', 'Erst ab Beginn der Winteraktion einlösbar.', 'Dieser Gutschein ist absichtlich für einen zukünftigen Zeitraum geplant.', true, 7, 35],
            ['Vorschau: Hautanalyse im Advent', 'Künftiger Gutschein für einen Beratungstermin.', 'Dieser Gutschein ist erst zu einem späteren Datum verfügbar.', true, 14, 45],
            ['Abgelaufen: Sommer-Sonnenschutz', 'Nur zum Testen abgelaufener Gutscheine.', 'Dieser Gutschein hat eine vergangene Laufzeit und soll nicht mehr einlösbar sein.', true, -30, -2],
            ['Abgelaufen: Frühlings-Teeaktion', 'Archivierter Aktionsgutschein.', 'Die zeitliche Gültigkeit ist bereits beendet.', true, -45, -5],
            ['Interner Entwurf: Baby-Pflegeprobe', 'Nicht für Kundinnen und Kunden sichtbar.', 'Dieser Gutschein dient zum Testen des Sichtbarkeitsfilters.', false, null, 30],
            ['Interner Entwurf: Messaktion', 'Ausgeblendeter Gutschein für die Administration.', 'Dieser Gutschein ist sichtbar nur in der Verwaltungsansicht.', false, null, 30],
        ];

        $created = 0;
        foreach ($items as [$title, $subtitle, $description, $visible, $fromOffset, $untilOffset]) {
            if ($this->exists(Coupon::class, $tenant, $title)) {
                continue;
            }

            $this->entityManager->persist(new Coupon(
                $tenant,
                $title,
                $subtitle,
                sprintf('<p>%s</p>', $description),
                self::COUPON_IMAGE,
                $visible,
                $fromOffset === null ? null : $now->modify(sprintf('%+d days', $fromOffset))->setTime(0, 0),
                $now->modify(sprintf('%+d days', $untilOffset))->setTime(23, 59),
            ));
            ++$created;
        }

        return $created;
    }

    private function seedRewards(Tenant $tenant, \DateTimeImmutable $now): int
    {
        $items = [
            ['Wärmendes Kirschkernkissen', 'Für gemütliche Wohlfühlmomente.', 450, true, null, 60],
            ['Kräutertee-Set', 'Drei Sorten für eine entspannte Pause.', 300, true, null, 45],
            ['Trinkflasche für unterwegs', 'Praktisch für Alltag, Sport und Spaziergänge.', 650, true, null, 90],
            ['Handpflege-Set', 'Sanfte Pflege für beanspruchte Hände.', 550, true, null, 50],
            ['Inhalationssalz', 'Wohltuend für eine kleine Auszeit.', 250, true, null, 40],
            ['Erste-Hilfe-Mini-Set', 'Kompakt und passend für unterwegs.', 900, true, null, 90],
            ['Leselampe für die Abendroutine', 'Angenehmes Licht für ruhige Stunden.', 1200, true, null, 70],
            ['Faszienball', 'Kleine Bewegungspause für zuhause.', 700, true, null, 60],
            ['Wohlfühl-Socken', 'Weich und warm durch den Tag.', 500, true, null, 45],
            ['Frühstücksbrettchen Gesundheit', 'Ein freundlicher Start in den Morgen.', 800, true, null, 75],
            ['Notizbuch für Gesundheitsziele', 'Platz für Termine, Fragen und Erfolge.', 350, true, null, 55],
            ['Einkaufsbeutel Apotheke', 'Wiederverwendbar und alltagstauglich.', 200, true, null, 30],
            ['Sitzkissen für unterwegs', 'Mehr Komfort bei längeren Wegen.', 1100, true, null, 80],
            ['Aromaduftstein', 'Eine kleine Ruheinsel für zuhause.', 600, true, null, 50],
            ['Vorschau: Winter-Wärmflasche', 'Erst zum Start der Winteraktion verfügbar.', 750, true, 7, 45],
            ['Vorschau: Neujahrs-Planer', 'Geplante Prämie für den Jahreswechsel.', 400, true, 14, 70],
            ['Abgelaufen: Sommer-Strandtasche', 'Archivprämie für den Zeitfilter.', 950, true, -40, -2],
            ['Abgelaufen: Frühlings-Saatgutset', 'Nicht mehr verfügbar.', 550, true, -50, -5],
            ['Interner Entwurf: Rückenroller', 'Ausgeblendete Prämie für die Administration.', 1300, false, null, 60],
            ['Interner Entwurf: Kinder-Trinkbecher', 'Nicht für Kundinnen und Kunden sichtbar.', 450, false, null, 45],
        ];

        $created = 0;
        foreach ($items as [$title, $subtitle, $requiredPoints, $visible, $fromOffset, $untilOffset]) {
            if ($this->exists(Reward::class, $tenant, $title)) {
                continue;
            }

            $this->entityManager->persist(new Reward(
                $tenant,
                $title,
                $subtitle,
                sprintf('<p>%s</p><p>Diese Demo-Prämie kann im Warenkorb zusammen mit weiteren Prämien vorgemerkt werden.</p>', $subtitle),
                self::REWARD_IMAGE,
                $requiredPoints,
                $visible,
                $fromOffset === null ? null : $now->modify(sprintf('%+d days', $fromOffset))->setTime(0, 0),
                $now->modify(sprintf('%+d days', $untilOffset))->setTime(23, 59),
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
