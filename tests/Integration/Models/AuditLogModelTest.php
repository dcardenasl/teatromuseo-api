<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use Tests\Support\IntegrationTestCase;

/**
 * AuditLogModel Integration Tests
 */
class AuditLogModelTest extends IntegrationTestCase
{
    protected AuditLogModel $model;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AuditLogModel();
        $this->userModel = new UserModel();
    }

    private function insertLog(array $overrides = []): int
    {
        return (int) $this->model->insert(array_merge([
            'action' => 'record_created',
            'entity_type' => 'users',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'result' => 'success',
            'severity' => 'info',
            'created_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function testGetByEntityReturnsOnlyMatchingEntityLogs(): void
    {
        $this->insertLog(['entity_type' => 'users', 'entity_id' => 42]);
        $this->insertLog(['entity_type' => 'users', 'entity_id' => 43]);
        $this->insertLog(['entity_type' => 'files', 'entity_id' => 42]);

        $logs = $this->model->getByEntity('users', 42);

        $this->assertCount(1, $logs);
        $this->assertSame('users', $logs[0]->entity_type);
        $this->assertSame(42, (int) $logs[0]->entity_id);
    }

    public function testGetByUserReturnsLogsForThatUserOrderedByCreatedAtDesc(): void
    {
        $userId = (int) $this->userModel->insert([
            'email' => 'audit-log-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
        ]);

        $this->insertLog(['user_id' => $userId, 'action' => 'first', 'created_at' => date('Y-m-d H:i:s', time() - 10)]);
        $this->insertLog(['user_id' => $userId, 'action' => 'second', 'created_at' => date('Y-m-d H:i:s')]);
        $this->insertLog(['user_id' => null, 'action' => 'other-user']);

        $logs = $this->model->getByUser($userId);

        $this->assertCount(2, $logs);
        $this->assertSame('second', $logs[0]->action);
        $this->assertSame('first', $logs[1]->action);
    }

    public function testGetByUserRespectsLimit(): void
    {
        $userId = (int) $this->userModel->insert([
            'email' => 'audit-limit-' . uniqid('', true) . '@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->insertLog(['user_id' => $userId, 'action' => 'action_' . $i]);
        }

        $logs = $this->model->getByUser($userId, 2);

        $this->assertCount(2, $logs);
    }

    public function testGetRecentReturnsMostRecentFirst(): void
    {
        // Rows persist across test methods within this class (IntegrationTestCase
        // only purges at class boundaries), so "newer" must be unambiguously
        // newer than anything any other test method could have inserted —
        // pin it a year into the future rather than assuming index 0 of an
        // unbounded getRecent() reflects only this test's own rows.
        $olderAction = 'older_' . uniqid('', true);
        $newerAction = 'newer_' . uniqid('', true);
        $this->insertLog(['action' => $olderAction, 'created_at' => date('Y-m-d H:i:s', time() - 100)]);
        $this->insertLog(['action' => $newerAction, 'created_at' => date('Y-m-d H:i:s', strtotime('+1 year'))]);

        $logs = $this->model->getRecent(1);

        $this->assertSame($newerAction, $logs[0]->action);
    }

    public function testGetActionFacetsCountsAndOrdersByFrequency(): void
    {
        // Use unique action names so counts aren't polluted by other test
        // methods' rows (see note above) — assert the two actions' relative
        // order to each other, not their absolute position in the facet list.
        $loginAction = 'login_' . uniqid('', true);
        $logoutAction = 'logout_' . uniqid('', true);
        $this->insertLog(['action' => $loginAction]);
        $this->insertLog(['action' => $loginAction]);
        $this->insertLog(['action' => $logoutAction]);

        $facets = $this->model->getActionFacets(90, 500);

        $values = array_column($facets, 'value');
        $byValue = array_column($facets, 'count', 'value');

        $this->assertSame(2, $byValue[$loginAction]);
        $this->assertSame(1, $byValue[$logoutAction]);
        // The more frequent action must be ordered before the less frequent one.
        $this->assertLessThan(array_search($logoutAction, $values, true), array_search($loginAction, $values, true));
    }

    public function testGetActionFacetsExcludesRecordsOutsideTheWindow(): void
    {
        $staleAction = 'stale_action_' . uniqid('', true);
        $this->insertLog([
            'action' => $staleAction,
            'created_at' => date('Y-m-d H:i:s', strtotime('-200 days')),
        ]);

        $facets = $this->model->getActionFacets(1, 100);

        $values = array_column($facets, 'value');
        $this->assertNotContains($staleAction, $values);
    }

    public function testGetEntityTypeFacetsCountsAndOrdersByFrequency(): void
    {
        $this->insertLog(['entity_type' => 'events']);
        $this->insertLog(['entity_type' => 'events']);
        $this->insertLog(['entity_type' => 'pages']);

        $facets = $this->model->getEntityTypeFacets(90, 100);

        $byValue = [];
        foreach ($facets as $facet) {
            $byValue[$facet['value']] = $facet['count'];
        }

        $this->assertSame(2, $byValue['events']);
        $this->assertSame(1, $byValue['pages']);
    }
}
