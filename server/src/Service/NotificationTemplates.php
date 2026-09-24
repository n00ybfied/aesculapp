<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationTemplate;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationTemplates
{
    public const CATALOG = NotificationTemplateCatalog::DEFINITIONS;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @param array<string, string> $values
     *  @return array{title: string, body: string}
     */
    public function render(Tenant $tenant, string $key, string $originalTitle, string $originalBody, array $values = []): array
    {
        if (!isset(self::CATALOG[$key])) {
            return ['title' => $originalTitle, 'body' => $originalBody];
        }
        $template = $this->em->getRepository(NotificationTemplate::class)->findOneBy(['tenant' => $tenant, 'key' => $key]);
        if (!$template instanceof NotificationTemplate) {
            return ['title' => $originalTitle, 'body' => $originalBody];
        }
        // Previously saved templates remain usable until edited or reset.
        $values += ['original_title' => $originalTitle, 'original_body' => $originalBody];
        $values['pharmacy_name'] = $tenant->getName();
        $replace = [];
        foreach ($values as $name => $value) {
            $replace['{{'.$name.'}}'] = $value;
        }
        return ['title' => strtr($template->title, $replace), 'body' => strtr($template->body, $replace)];
    }

    /** @return list<array{key: string, channel: string, label: string, title: string, body: string, customized: bool, tags: list<string>, required: list<string>}> */
    public function listFor(Tenant $tenant): array
    {
        $stored = [];
        foreach ($this->em->getRepository(NotificationTemplate::class)->findBy(['tenant' => $tenant]) as $template) {
            $stored[$template->key] = $template;
        }
        $result = [];
        foreach (self::CATALOG as $key => $definition) {
            $template = $stored[$key] ?? null;
            $result[] = [
                'key' => $key, 'channel' => $definition['channel'], 'label' => $definition['label'],
                'title' => str_replace('{{original_title}}', $definition['title'], $template?->title ?? $definition['title']),
                'body' => str_replace('{{original_body}}', $definition['body'], $template?->body ?? $definition['body']),
                'customized' => $template !== null,
                'tags' => [...$definition['tags'], 'pharmacy_name'],
                'required' => $definition['required'],
            ];
        }
        return $result;
    }

    public function save(Tenant $tenant, string $key, string $title, string $body): void
    {
        $definition = self::CATALOG[$key] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException('Unbekannte Nachrichtenvorlage.');
        }
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '' || mb_strlen($title) > 200 || mb_strlen($body) > 10000 || str_contains($title, "\n") || str_contains($title, "\r")) {
            throw new \InvalidArgumentException('Bitte Titel und Text prüfen (maximal 200 bzw. 10.000 Zeichen).');
        }
        foreach ([$title, $body] as $value) {
            preg_match_all('/{{([^{}]+)}}/', $value, $matches);
            foreach ($matches[1] as $tag) {
                if (!in_array($tag, [...$definition['tags'], 'pharmacy_name'], true)) {
                    throw new \InvalidArgumentException('Unbekanntes Inserttag: '.$tag);
                }
            }
            if (preg_match('/{{|}}/', preg_replace('/{{[^{}]+}}/', '', $value) ?? '')) {
                throw new \InvalidArgumentException('Bitte prüfen Sie die Schreibweise der Inserttags.');
            }
        }
        foreach ($definition['required'] as $tag) {
            if (!str_contains($body, '{{'.$tag.'}}')) {
                throw new \InvalidArgumentException('Das Inserttag {{'.$tag.'}} muss im Text bleiben.');
            }
        }
        $repo = $this->em->getRepository(NotificationTemplate::class);
        $template = $repo->findOneBy(['tenant' => $tenant, 'key' => $key]);
        if (!$template instanceof NotificationTemplate) {
            $template = new NotificationTemplate($tenant, $key, $title, $body);
            $this->em->persist($template);
        } else {
            $template->title = $title;
            $template->body = $body;
        }
        $this->em->flush();
    }

    public function reset(Tenant $tenant, string $key): void
    {
        if (!isset(self::CATALOG[$key])) {
            throw new \InvalidArgumentException('Unbekannte Nachrichtenvorlage.');
        }
        $template = $this->em->getRepository(NotificationTemplate::class)->findOneBy(['tenant' => $tenant, 'key' => $key]);
        if ($template instanceof NotificationTemplate) {
            $this->em->remove($template);
            $this->em->flush();
        }
    }
}
