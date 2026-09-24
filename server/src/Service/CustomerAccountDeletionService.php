<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CustomerAccountDeletionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RevokeRefreshTokenManagerInterface $refreshTokens,
        private readonly UsernameReservation $usernameReservation,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function delete(User $user, Tenant $tenant): void
    {
        $userId = $user->getId();
        $tenantId = $tenant->getId();
        if ($userId === null || $tenantId === null) {
            throw new \LogicException('A persisted customer and tenant are required.');
        }

        /** @var list<string> $files */
        $files = $this->connection->transactional(function () use ($user, $userId, $tenantId): array {
            $this->connection->fetchOne('SELECT id FROM app_user WHERE id = ? FOR UPDATE', [$userId]);
            $membership = $this->connection->fetchAssociative(
                'SELECT id, roles FROM tenant_membership WHERE tenant_id = ? AND user_id = ? FOR UPDATE',
                [$tenantId, $userId],
            );
            $roles = $membership === false ? [] : json_decode((string) $membership['roles'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($roles) || !in_array('ROLE_CUSTOMER', $roles, true)) {
                throw new \DomainException('Customer membership not found.');
            }

            $files = $this->connection->fetchFirstColumn(
                "SELECT path FROM media_asset WHERE tenant_id = ? AND owner_id = ? AND visibility = 'private'",
                [$tenantId, $userId],
            );
            $profileImage = $user->getProfileImagePath();
            $conversationIds = array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT id FROM chat_conversation WHERE tenant_id = ? AND customer_id = ?',
                [$tenantId, $userId],
            ));
            if ($conversationIds !== []) {
                $this->connection->executeStatement(
                    'DELETE FROM chat_message WHERE conversation_id IN (?)',
                    [$conversationIds],
                    [\Doctrine\DBAL\ArrayParameterType::INTEGER],
                );
            }
            $this->connection->executeStatement('DELETE FROM chat_conversation WHERE tenant_id = ? AND customer_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM appointment WHERE tenant_id = ? AND customer_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM coupon_redemption WHERE tenant_id = ? AND customer_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM family_connection WHERE tenant_id = ? AND (participant_one_id = ? OR participant_two_id = ? OR invited_by_id = ?)', [$tenantId, $userId, $userId, $userId]);
            $this->connection->executeStatement('DELETE FROM medication WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM web_push_subscription WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM app_analytics_event WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM email_verification_token WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM email_change_token WHERE tenant_id = ? AND user_id = ?', [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM password_reset_token WHERE user_id = ?', [$userId]);
            $this->connection->executeStatement("DELETE FROM media_asset WHERE tenant_id = ? AND owner_id = ? AND visibility = 'private'", [$tenantId, $userId]);
            $this->connection->executeStatement('DELETE FROM point_account WHERE tenant_id = ? AND owner_id = ?', [$tenantId, $userId]);

            $remainingRoles = array_values(array_filter($roles, static fn (mixed $role): bool => $role !== 'ROLE_CUSTOMER'));
            if ($remainingRoles === []) {
                $this->connection->executeStatement('DELETE FROM tenant_membership WHERE id = ?', [$membership['id']]);
            } else {
                $this->connection->executeStatement(
                    "UPDATE tenant_membership SET roles = ?, newsletter_enabled = 0, chat_push_enabled = 0, reward_push_enabled = 0, news_push_enabled = 0, medication_push_enabled = 0, appointment_push_enabled = 0, family_push_enabled = 0, morning_reminder_time = '08:00', noon_reminder_time = '12:00', evening_reminder_time = '18:00', night_reminder_time = '22:00', footer_home_enabled = 0, footer_chat_enabled = 0, footer_rewards_enabled = 0, footer_website_enabled = 0, footer_navigation_items = JSON_ARRAY() WHERE id = ?",
                    [json_encode($remainingRoles, JSON_THROW_ON_ERROR), $membership['id']],
                );
            }

            $this->refreshTokens->revokeAllForUser($user);
            if (!$this->hasRemainingMembershipOrData($userId)) {
                $this->usernameReservation->reserve($user->getUsername());
                $this->connection->executeStatement('DELETE FROM app_user WHERE id = ?', [$userId]);
                if ($profileImage !== null) {
                    $files[] = $profileImage;
                }
            }

            return array_values(array_filter($files, 'is_string'));
        });

        foreach ($files as $path) {
            $this->deleteOwnedFile($path);
        }
    }

    private function hasRemainingMembershipOrData(int $userId): bool
    {
        foreach ([
            ['tenant_membership', 'user_id'],
            ['appointment', 'customer_id'],
            ['chat_conversation', 'customer_id'],
            ['chat_message', 'sender_id'],
            ['coupon_redemption', 'customer_id'],
            ['family_connection', 'participant_one_id'],
            ['family_connection', 'participant_two_id'],
            ['family_connection', 'invited_by_id'],
            ['family_connection', 'point_sharing_requested_by_id'],
            ['medication', 'user_id'],
            ['point_account', 'owner_id'],
            ['web_push_subscription', 'user_id'],
            ['app_analytics_event', 'user_id'],
            ['email_verification_token', 'user_id'],
            ['email_change_token', 'user_id'],
        ] as [$table, $column]) {
            if ($this->connection->fetchOne("SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1", [$userId]) !== false) {
                return true;
            }
        }
        return $this->connection->fetchOne("SELECT 1 FROM media_asset WHERE owner_id = ? AND visibility = 'private' LIMIT 1", [$userId]) !== false;
    }

    private function deleteOwnedFile(string $path): void
    {
        if (preg_match('#^/uploads/(?:media|profiles)/[A-Za-z0-9._-]+$#D', $path) !== 1) {
            $this->logger->warning('Skipped unexpected customer upload path during account deletion.');
            return;
        }
        $file = $this->projectDir.'/public'.$path;
        if (is_file($file) && !unlink($file)) {
            $this->logger->warning('A customer upload could not be removed after account deletion.');
        }
    }
}
