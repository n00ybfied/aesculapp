<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$connection = $em->getConnection();
$tenant = (new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']))->get();
$requests = new Symfony\Component\HttpFoundation\RequestStack();
$tokens = new Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
$services = new Symfony\Component\DependencyInjection\Container();
$services->set('security.token_storage', $tokens);
$security = new Symfony\Bundle\SecurityBundle\Security($services);
$listener = new App\EventListener\AdminChangeLogListener($requests, $security, $connection, $_ENV['APP_TENANT_SLUG']);
$connection->beginTransaction();

try {
    $email = 'audit-'.bin2hex(random_bytes(6)).'@example.invalid';
    $actor = new App\Entity\User($email, $email, 'Audit Tester');
    $actor->setPassword('not-a-login');
    $em->persist($actor);
    $em->persist(new App\Entity\TenantMembership($tenant, $actor, ['ROLE_TENANT_ADMIN']));
    $em->flush();

    $tokens->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($actor, 'api', ['ROLE_USER']));
    $requests->push(Symfony\Component\HttpFoundation\Request::create('/api/v1/admin/news/categories', 'POST'));
    $em->getEventManager()->addEventListener(['postPersist', 'postUpdate', 'preRemove', 'postRemove'], $listener);
    $category = new App\Entity\NewsCategory($tenant, 'Audit Test '.bin2hex(random_bytes(4)));
    $em->persist($category);
    $em->flush();

    $category->setName($category->getName().' geändert');
    $em->flush();

    $id = $category->getId();
    $em->remove($category);
    $em->flush();

    $rows = $connection->fetchAllAssociative('SELECT actor_user_id, actor_name, action, entity_id FROM admin_change_log WHERE tenant_id = ? AND entity_type = ? AND entity_id = ? ORDER BY id', [$tenant->getId(), 'NewsCategory', (string) $id]);
    if (array_column($rows, 'action') !== ['created', 'updated', 'deleted']) {
        throw new RuntimeException('Create/update/delete audit trail incomplete: '.json_encode($rows, JSON_THROW_ON_ERROR));
    }
    foreach ($rows as $row) {
        if ((int) $row['actor_user_id'] !== $actor->getId() || $row['actor_name'] !== 'Audit Tester') {
            throw new RuntimeException('Audit actor does not match the authenticated backend user.');
        }
    }
    $controller = new App\Controller\ApiAdminChangeLogController(
        new App\Service\ActiveTenantProvider($em, $_ENV['APP_TENANT_SLUG']),
        $em->getRepository(App\Entity\TenantMembership::class),
        new App\Service\AdminAreaPermissions(),
        $security,
        $connection,
    );
    $result = json_decode($controller->list(new Symfony\Component\HttpFoundation\Request(['entityType' => 'NewsCategory', 'entityId' => (string) $id]))->getContent(), true, 512, JSON_THROW_ON_ERROR);
    if (array_column($result['changes'], 'action') !== ['deleted', 'updated', 'created']) {
        throw new RuntimeException('The admin history endpoint did not return the three changes in reverse order.');
    }
    echo "Admin audit: create, update, delete and actor attribution passed.\n";
} finally {
    $requests->pop();
    $tokens->setToken(null);
    $connection->rollBack();
    $em->clear();
    $kernel->shutdown();
}
