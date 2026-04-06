<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\Mandate;
use App\Models\Account;
use App\Models\User;
use Tests\TestDatabase;

class MandateTest extends TestCase
{
    private Mandate $mandate;
    private Account $account;
    private User $user;
    private int $proAccountId;
    private int $standardAccountId;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->mandate = new Mandate();
        $this->account = new Account();
        $this->user    = new User();

        // Créer un utilisateur professionnel et un standard
        $proUserId = $this->user->create([
            'username'        => 'pro_user',
            'email'           => 'pro@test.com',
            'password'        => password_hash('secret', PASSWORD_DEFAULT),
            'global_role'     => 'user',
            'is_professional' => 1,
            'company_name'    => 'Test Corp',
            'siret'           => '12345678901234',
        ]);
        $stdUserId = $this->user->create([
            'username'    => 'std_user',
            'email'       => 'std@test.com',
            'password'    => password_hash('secret', PASSWORD_DEFAULT),
            'global_role' => 'user',
        ]);

        $this->proAccountId = $this->account->create([
            'user_id'  => $proUserId,
            'name'     => 'Compte Pro',
            'currency' => 'EUR',
            'type'     => 'pro',
        ]);
        $this->standardAccountId = $this->account->create([
            'user_id'  => $stdUserId,
            'name'     => 'Compte Standard',
            'currency' => 'EUR',
            'type'     => 'standard',
        ]);
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateMandateOneTime(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-001',
            $this->proAccountId,
            $this->standardAccountId,
            'Test mandat ponctuel',
            100.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        $this->assertGreaterThan(0, $id);

        $m = $this->mandate->find($id);
        $this->assertSame('MAND-001', $m['number']);
        $this->assertEquals($this->proAccountId, $m['emitter_account_id']);
        $this->assertEquals($this->standardAccountId, $m['recipient_account_id']);
        $this->assertSame('Test mandat ponctuel', $m['description']);
        $this->assertEquals(100.00, (float) $m['amount']);
        $this->assertSame(Mandate::TYPE_ONE_TIME, $m['type']);
        $this->assertNull($m['interval_days']);
        $this->assertSame(Mandate::STATUS_ACTIVE, $m['status']);
    }

    public function testCreateMandateRecurring(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-002',
            $this->proAccountId,
            $this->standardAccountId,
            'Abonnement mensuel',
            49.99,
            Mandate::TYPE_RECURRING,
            30,
            1
        );

        $m = $this->mandate->find($id);
        $this->assertSame(Mandate::TYPE_RECURRING, $m['type']);
        $this->assertEquals(30, $m['interval_days']);
    }

    public function testCreateRecurringIgnoresIntervalWhenOneTime(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-003',
            $this->proAccountId,
            $this->standardAccountId,
            'Ponctuel avec interval ignoré',
            25.00,
            Mandate::TYPE_ONE_TIME,
            15,
            1
        );

        $m = $this->mandate->find($id);
        $this->assertNull($m['interval_days']);
    }

    public function testNumberExists(): void
    {
        $this->assertFalse($this->mandate->numberExists('MAND-999'));

        $this->mandate->createMandate(
            'MAND-999',
            $this->proAccountId,
            $this->standardAccountId,
            'Test',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        $this->assertTrue($this->mandate->numberExists('MAND-999'));
    }

    public function testRevoke(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-REV',
            $this->proAccountId,
            $this->standardAccountId,
            'A révoquer',
            50.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        $this->assertSame(Mandate::STATUS_ACTIVE, $this->mandate->find($id)['status']);

        $result = $this->mandate->revoke($id);
        $this->assertTrue($result);

        $this->assertSame(Mandate::STATUS_REVOKED, $this->mandate->find($id)['status']);
    }

    public function testGetAllWithAccounts(): void
    {
        $this->mandate->createMandate(
            'MAND-A',
            $this->proAccountId,
            $this->standardAccountId,
            'Premier',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );
        $this->mandate->createMandate(
            'MAND-B',
            $this->proAccountId,
            $this->standardAccountId,
            'Second',
            20.00,
            Mandate::TYPE_RECURRING,
            7,
            1
        );

        $all = $this->mandate->getAllWithAccounts();
        $this->assertCount(2, $all);

        // Vérifier que les JOINs renvoient les noms
        $first = $all[0];
        $this->assertArrayHasKey('emitter_name', $first);
        $this->assertArrayHasKey('emitter_owner', $first);
        $this->assertArrayHasKey('recipient_name', $first);
        $this->assertArrayHasKey('recipient_owner', $first);
        $this->assertSame('Compte Pro', $first['emitter_name']);
        $this->assertSame('pro_user', $first['emitter_owner']);
    }

    public function testGetByAccount(): void
    {
        // Mandat où le pro est émetteur
        $this->mandate->createMandate(
            'MAND-E1',
            $this->proAccountId,
            $this->standardAccountId,
            'Emetteur',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        // Vérifier depuis le pro account
        $mandates = $this->mandate->getByAccount($this->proAccountId);
        $this->assertCount(1, $mandates);
        $this->assertSame('MAND-E1', $mandates[0]['number']);

        // Vérifier depuis le standard account (en tant que destinataire)
        $mandates = $this->mandate->getByAccount($this->standardAccountId);
        $this->assertCount(1, $mandates);
        $this->assertSame('MAND-E1', $mandates[0]['number']);
    }

    public function testGetByAccountReturnsEmpty(): void
    {
        $mandates = $this->mandate->getByAccount(999);
        $this->assertSame([], $mandates);
    }

    public function testConstants(): void
    {
        $this->assertSame('one_time', Mandate::TYPE_ONE_TIME);
        $this->assertSame('recurring', Mandate::TYPE_RECURRING);
        $this->assertSame('active', Mandate::STATUS_ACTIVE);
        $this->assertSame('executed', Mandate::STATUS_EXECUTED);
        $this->assertSame('revoked', Mandate::STATUS_REVOKED);

        $this->assertArrayHasKey(Mandate::TYPE_ONE_TIME, Mandate::TYPES);
        $this->assertArrayHasKey(Mandate::TYPE_RECURRING, Mandate::TYPES);
        $this->assertArrayHasKey(Mandate::STATUS_ACTIVE, Mandate::STATUSES);
        $this->assertArrayHasKey(Mandate::STATUS_EXECUTED, Mandate::STATUSES);
        $this->assertArrayHasKey(Mandate::STATUS_REVOKED, Mandate::STATUSES);
    }

    public function testSearchByQueryWithTypeFilter(): void
    {
        // Tester le filtre de type dans searchByQuery
        $results = $this->account->searchByQuery('Compte', 15, 'pro');
        $this->assertCount(1, $results);
        $this->assertSame('Compte Pro', $results[0]['name']);

        // Sans filtre, les deux comptes apparaissent
        $results = $this->account->searchByQuery('Compte', 15);
        $this->assertCount(2, $results);

        // Filtre sur type inexistant
        $results = $this->account->searchByQuery('Compte', 15, 'minor');
        $this->assertCount(0, $results);
    }

    public function testCreateMandateSetsNextExecutionAt(): void
    {
        $before = date('Y-m-d H:i:s');
        $id = $this->mandate->createMandate(
            'MAND-EXEC',
            $this->proAccountId,
            $this->standardAccountId,
            'Test',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        $m = $this->mandate->find($id);
        $this->assertNotNull($m['next_execution_at']);
        $this->assertGreaterThanOrEqual($before, $m['next_execution_at']);
    }

    public function testGetDueReturnsActiveWithPastNextExecution(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-DUE',
            $this->proAccountId,
            $this->standardAccountId,
            'Echue',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        // next_execution_at est à maintenant, donc getDue() doit le retourner
        $due = $this->mandate->getDue();
        $this->assertCount(1, $due);
        $this->assertEquals($id, $due[0]['id']);
    }

    public function testGetDueExcludesRevokedMandates(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-REV2',
            $this->proAccountId,
            $this->standardAccountId,
            'Révoqué',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );
        $this->mandate->revoke($id);

        $due = $this->mandate->getDue();
        $this->assertCount(0, $due);
    }

    public function testGetDueExcludesFutureExecution(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-FUT',
            $this->proAccountId,
            $this->standardAccountId,
            'Future',
            10.00,
            Mandate::TYPE_RECURRING,
            30,
            1
        );

        // Placer next_execution_at dans le futur
        $this->mandate->update($id, [
            'next_execution_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $due = $this->mandate->getDue();
        $this->assertCount(0, $due);
    }

    public function testMarkExecutedOneTimeSetStatusExecuted(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-OT',
            $this->proAccountId,
            $this->standardAccountId,
            'Ponctuel',
            50.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        $this->mandate->markExecuted($id);

        $m = $this->mandate->find($id);
        $this->assertSame(Mandate::STATUS_EXECUTED, $m['status']);
        $this->assertNotNull($m['last_executed_at']);
        $this->assertNull($m['next_execution_at']);
    }

    public function testMarkExecutedRecurringSetsNextExecution(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-REC',
            $this->proAccountId,
            $this->standardAccountId,
            'Récurrent',
            25.00,
            Mandate::TYPE_RECURRING,
            7,
            1
        );

        $before = date('Y-m-d H:i:s');
        $this->mandate->markExecuted($id);

        $m = $this->mandate->find($id);
        $this->assertSame(Mandate::STATUS_ACTIVE, $m['status']);
        $this->assertNotNull($m['last_executed_at']);
        $this->assertNotNull($m['next_execution_at']);
        // Prochaine exécution doit être dans ~7 jours
        $nextTs = strtotime($m['next_execution_at']);
        $this->assertGreaterThan(strtotime('+6 days'), $nextTs);
        $this->assertLessThanOrEqual(strtotime('+8 days'), $nextTs);
    }

    public function testGetUpcomingByAccountEmitter(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-UP1',
            $this->proAccountId,
            $this->standardAccountId,
            'Upcoming',
            30.00,
            Mandate::TYPE_RECURRING,
            10,
            1
        );

        // Le mandat actif avec next_execution_at apparaît pour l'émetteur
        $upcoming = $this->mandate->getUpcomingByAccount($this->proAccountId);
        $this->assertCount(1, $upcoming);
        $this->assertSame('MAND-UP1', $upcoming[0]['number']);
        $this->assertArrayHasKey('emitter_name', $upcoming[0]);
        $this->assertArrayHasKey('recipient_name', $upcoming[0]);
    }

    public function testGetUpcomingByAccountRecipient(): void
    {
        $this->mandate->createMandate(
            'MAND-UP2',
            $this->proAccountId,
            $this->standardAccountId,
            'Upcoming recipient',
            15.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );

        // Le mandat apparaît aussi côté destinataire
        $upcoming = $this->mandate->getUpcomingByAccount($this->standardAccountId);
        $this->assertCount(1, $upcoming);
        $this->assertSame('MAND-UP2', $upcoming[0]['number']);
    }

    public function testGetUpcomingByAccountExcludesRevoked(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-UP3',
            $this->proAccountId,
            $this->standardAccountId,
            'Revoked',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );
        $this->mandate->revoke($id);

        $upcoming = $this->mandate->getUpcomingByAccount($this->proAccountId);
        $this->assertCount(0, $upcoming);
    }

    public function testGetUpcomingByAccountExcludesExecuted(): void
    {
        $id = $this->mandate->createMandate(
            'MAND-UP4',
            $this->proAccountId,
            $this->standardAccountId,
            'Executed',
            10.00,
            Mandate::TYPE_ONE_TIME,
            null,
            1
        );
        $this->mandate->markExecuted($id);

        $upcoming = $this->mandate->getUpcomingByAccount($this->proAccountId);
        $this->assertCount(0, $upcoming);
    }
}
