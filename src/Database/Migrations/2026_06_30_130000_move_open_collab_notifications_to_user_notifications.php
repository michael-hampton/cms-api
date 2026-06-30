<?php

use App\Framework\Database\Database;
use App\Framework\Migration\Migration;

class MoveOpenCollabNotificationsToUserNotifications extends Migration
{
    private const TITLE_PATTERNS = [
        'Your payout of % has been approved',
        'Payout request received%',
        'Your payout of % has been sent',
        'Your payout request could not be processed',
        'Your earnings dispute has been resolved in your favour',
        'Update on your earnings dispute',
        'Earnings adjustment applied:%',
        'A policy violation has been recorded on your account',
        'A new contract is available for you to review',
        'Guidelines have been updated%',
        'Your article % was approved%',
        'Your article % was not approved',
        'Payment failed%',
        'Ready to retry your payment%',
        'Welcome % your account is ready',
        'Let% finish setting up your contributor account',
        'You% all set%',
        '% actions required:%',
        '% action is required:%',
        'Onboarding step completed:%',
    ];

    public function up(): void
    {
        $database = Database::getInstance();
        $where = $this->openCollabWhereClause();

        $database->query("
            INSERT INTO user_notifications (user_id, type, data, read_at, created_at, updated_at)
            SELECT
                n.member_id,
                'open_collab_notification',
                JSON_OBJECT(
                    'title', n.title,
                    'message', n.body,
                    'migrated_from', 'notifications',
                    'notification_id', n.id
                ),
                CASE WHEN n.is_read = 1 THEN n.updated_at ELSE NULL END,
                n.created_at,
                n.updated_at
            FROM notifications n
            INNER JOIN users u ON u.id = n.member_id
            WHERE {$where}
        ");

        $database->query("
            DELETE n
            FROM notifications n
            INNER JOIN users u ON u.id = n.member_id
            WHERE {$where}
        ");
    }

    public function down(): void
    {
        Database::getInstance()->query("
            DELETE FROM user_notifications
            WHERE type = 'open_collab_notification'
              AND JSON_EXTRACT(data, '$.migrated_from') = 'notifications'
        ");
    }

    private function openCollabWhereClause(): string
    {
        return '(' . implode(' OR ', array_map(
            fn(string $pattern) => "n.title LIKE " . $this->quote($pattern),
            self::TITLE_PATTERNS
        )) . ')';
    }

    private function quote(string $value): string
    {
        return Database::getInstance()->getConnection()->quote($value);
    }
}
