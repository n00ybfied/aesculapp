<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Entity\NewsPost;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\NewsPushDispatcher;

function audienceCheck(bool $condition): void
{
    if (!$condition) throw new RuntimeException('News push audience check failed.');
}

$tenant = new Tenant('Test', 'test');
$post = new NewsPost($tenant, 'Test', 'Test', '<p>Test</p>', null, true, new DateTimeImmutable(), null, null);
$post->setCategoryIds([1, 2]);
$post->setNotificationSalutations(['frau']);
$user = new User('test@example.test', 'test@example.test', 'Test');
$user->setSalutation('frau');
$membership = new TenantMembership($tenant, $user);
$membership->setNewsPushEnabled(true);
$membership->setNewsCategoryIds([2]);
audienceCheck(NewsPushDispatcher::matchesAudience($post, $membership));

$user->setSalutation('herr');
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$post->setNotificationSalutations([]);
audienceCheck(NewsPushDispatcher::matchesAudience($post, $membership));
$membership->setNewsCategoryIds([3]);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$membership->setNewsCategoryIds([1]);
$membership->setNewsPushEnabled(false);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$membership->setNewsPushEnabled(true);
$user->setActive(false);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$user->setActive(true);
$post->setCategoryIds([]);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$post->setCategoryIds([1]);
$post->setNotificationSalutations(['frau']);
$user->setSalutation(null);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $membership));
$otherTenant = new Tenant('Other', 'other');
$otherMembership = new TenantMembership($otherTenant, $user);
$otherMembership->setNewsPushEnabled(true);
$otherMembership->setNewsCategoryIds([1]);
audienceCheck(!NewsPushDispatcher::matchesAudience($post, $otherMembership));

echo "News push audience checks passed.\n";
