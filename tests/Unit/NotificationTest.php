<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\Notification;
use Tests\TestDatabase;

class NotificationTest extends TestCase
{
    private Notification $notif;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->notif = new Notification();
        // Créer un utilisateur de test
        $pdo = Database::getInstance();
        $pdo->exec("INSERT INTO users (id, username, email, password, global_role, status) VALUES
            (1, 'alice',     'alice@test.com',     'hash', 'user',      'active'),
            (2, 'bob',       'bob@test.com',       'hash', 'user',      'active'),
            (3, 'moderator', 'mod@test.com',       'hash', 'moderator', 'active')");
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    // ── Création ──────────────────────────────────────────────────────────

    public function testNotifyCreatesRecord(): void
    {
        $id = $this->notif->notify(1, 'transfer_received', 'Virement reçu', 'Détails', '/transfers');
        $this->assertGreaterThan(0, $id);

        $record = $this->notif->find($id);
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record['user_id']);
        $this->assertSame('transfer_received', $record['type']);
        $this->assertSame('Virement reçu', $record['title']);
        $this->assertSame('Détails', $record['body']);
        $this->assertSame('/transfers', $record['link']);
        $this->assertSame(0, (int) $record['is_read']);
    }

    public function testNotifyWithNullBodyAndLink(): void
    {
        $id = $this->notif->notify(1, 'account_frozen', 'Compte gelé');
        $record = $this->notif->find($id);
        $this->assertNull($record['body']);
        $this->assertNull($record['link']);
    }

    public function testNotifyModeratorsCreatesOneNotifPerModerator(): void
    {
        $this->notif->notifyModerators('mod_new_ticket', 'Nouveau ticket', 'Détails', '/mod/tickets/1');

        $pdo  = Database::getInstance();
        $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'mod_new_ticket'");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(1, $count); // un seul modérateur dans la fixture
    }

    public function testNotifyModeratorsDoesNothingWhenNoModerators(): void
    {
        $pdo = Database::getInstance();
        $pdo->exec("DELETE FROM users WHERE global_role = 'moderator'");

        $this->notif->notifyModerators('mod_new_ticket', 'Titre', null, null);

        $stmt  = $pdo->query("SELECT COUNT(*) FROM notifications");
        $count = (int) $stmt->fetchColumn();
        $this->assertSame(0, $count);
    }

    // ── Lecture ─────────────────────────────────────────────────────────────

    public function testGetForUserReturnsOwnNotifications(): void
    {
        $this->notif->notify(1, 'transfer_received', 'A');
        $this->notif->notify(1, 'account_frozen',    'B');
        $this->notif->notify(2, 'ticket_replied',    'C'); // autre user

        $results = $this->notif->getForUser(1);
        $this->assertCount(2, $results);
    }

    public function testGetForUserReturnsNewestFirst(): void
    {
        $id1 = $this->notif->notify(1, 'transfer_received', 'Premier');

        // Forcer un écart de timestamp via une mise à jour directe
        $pdo = Database::getInstance();
        $pdo->exec("UPDATE notifications SET created_at = '2000-01-01 00:00:00' WHERE id = $id1");

        $this->notif->notify(1, 'account_frozen', 'Deuxième');

        $results = $this->notif->getForUser(1);
        $this->assertSame('Deuxième', $results[0]['title']);
        $this->assertSame('Premier',  $results[1]['title']);
    }

    public function testGetForUserRespectsLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->notif->notify(1, 'transfer_received', 'Notif ' . $i);
        }

        $results = $this->notif->getForUser(1, 3);
        $this->assertCount(3, $results);
    }

    public function testCountUnreadReturnsOnlyUnread(): void
    {
        $id1 = $this->notif->notify(1, 'transfer_received', 'A');
        $id2 = $this->notif->notify(1, 'account_frozen',    'B');
        $this->notif->notify(2, 'ticket_replied', 'C');

        $this->assertSame(2, $this->notif->countUnread(1));

        $this->notif->markRead($id1, 1);
        $this->assertSame(1, $this->notif->countUnread(1));

        $this->notif->markRead($id2, 1);
        $this->assertSame(0, $this->notif->countUnread(1));
    }

    public function testCountUnreadIgnoresOtherUsers(): void
    {
        $this->notif->notify(2, 'transfer_received', 'Pour bob');
        $this->assertSame(0, $this->notif->countUnread(1));
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    public function testMarkReadSetsIsRead(): void
    {
        $id = $this->notif->notify(1, 'transfer_received', 'Test');
        $this->notif->markRead($id, 1);

        $record = $this->notif->find($id);
        $this->assertSame(1, (int) $record['is_read']);
    }

    public function testMarkReadDoesNotMarkOtherUsersNotif(): void
    {
        $id = $this->notif->notify(2, 'transfer_received', 'Pour bob');
        $this->notif->markRead($id, 1); // alice essaie de marquer la notif de bob

        $record = $this->notif->find($id);
        $this->assertSame(0, (int) $record['is_read']); // toujours non lu
    }

    public function testMarkAllReadMarksAllUnread(): void
    {
        $this->notif->notify(1, 'transfer_received', 'A');
        $this->notif->notify(1, 'account_frozen',    'B');
        $this->notif->notify(2, 'ticket_replied',    'C'); // autre user

        $this->notif->markAllRead(1);

        $this->assertSame(0, $this->notif->countUnread(1));
        $this->assertSame(1, $this->notif->countUnread(2)); // bob non touché
    }

    public function testDeleteForUserRemovesNotification(): void
    {
        $id = $this->notif->notify(1, 'transfer_received', 'A supprimer');
        $this->notif->deleteForUser($id, 1);

        $this->assertNull($this->notif->find($id));
    }

    public function testDeleteForUserCannotDeleteOtherUsersNotif(): void
    {
        $id = $this->notif->notify(2, 'transfer_received', 'Pour bob');
        $this->notif->deleteForUser($id, 1); // alice essaie de supprimer

        $this->assertNotNull($this->notif->find($id)); // encore présent
    }

    public function testDeleteReadForUserDeletesOnlyRead(): void
    {
        $id1 = $this->notif->notify(1, 'transfer_received', 'Lu');
        $id2 = $this->notif->notify(1, 'account_frozen',    'Non lu');
        $this->notif->markRead($id1, 1);

        $this->notif->deleteReadForUser(1);

        $this->assertNull($this->notif->find($id1));   // supprimé
        $this->assertNotNull($this->notif->find($id2)); // conservé
    }

    // ── iconClass ────────────────────────────────────────────────────────────

    public function testIconClassReturnsCorrectClass(): void
    {
        $this->assertSame('bi-arrow-down-circle-fill text-success', Notification::iconClass('transfer_received'));
        $this->assertSame('bi-snow text-primary',                   Notification::iconClass('account_frozen'));
        $this->assertSame('bi-bell-fill text-muted',                Notification::iconClass('unknown_type')); // fallback
    }
}
