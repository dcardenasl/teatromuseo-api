<?php

declare(strict_types=1);

namespace App\Repositories\Users;

use App\Interfaces\Users\AdminUserListRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;

final class AdminUserListRepository implements AdminUserListRepositoryInterface
{
    /** @param BaseConnection<mixed, mixed> $db */
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int}
     */
    public function paginateAdminList(array $criteria, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(1000, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = ['u.deleted_at IS NULL'];
        $binds = [];
        $status = $this->criteriaValue($criteria, 'status');
        if (is_array($status) && array_key_exists('eq', $status)) {
            $status = $status['eq'];
        }
        if (is_string($status) && $status !== '') {
            $where[] = 'u.status = ?';
            $binds[] = $status;
        }
        if (($criteria['exclude_superadmins'] ?? false) === true) {
            $where[] = <<<'SQL'
NOT EXISTS (
    SELECT 1 FROM user_roles ur_hidden
    INNER JOIN role_permissions rp_hidden ON rp_hidden.role_id = ur_hidden.role_id
    INNER JOIN permissions p_hidden ON p_hidden.id = rp_hidden.permission_id
    WHERE ur_hidden.user_id = u.id AND p_hidden.code = 'iam.superadmin-access'
)
SQL;
        }
        $search = trim((string) ($criteria['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
            $needle = '%' . $search . '%';
            array_push($binds, $needle, $needle, $needle);
        }
        $sort = (string) ($criteria['sort'] ?? '-created_at');
        $sorts = [
            'email' => ['u.email ASC, u.id ASC', 'p.email ASC, p.id ASC'], '-email' => ['u.email DESC, u.id DESC', 'p.email DESC, p.id DESC'],
            'first_name' => ['u.first_name ASC, u.id ASC', 'p.first_name ASC, p.id ASC'], '-first_name' => ['u.first_name DESC, u.id DESC', 'p.first_name DESC, p.id DESC'],
            'last_name' => ['u.last_name ASC, u.id ASC', 'p.last_name ASC, p.id ASC'], '-last_name' => ['u.last_name DESC, u.id DESC', 'p.last_name DESC, p.id DESC'],
            'status' => ['u.status ASC, u.id ASC', 'p.status ASC, p.id ASC'], '-status' => ['u.status DESC, u.id DESC', 'p.status DESC, p.id DESC'],
            'created_at' => ['u.created_at ASC, u.id ASC', 'p.created_at ASC, p.id ASC'], '-created_at' => ['u.created_at DESC, u.id DESC', 'p.created_at DESC, p.id DESC'],
        ];
        [$innerOrder, $outerOrder] = $sorts[$sort] ?? $sorts['-created_at'];
        $whereSql = implode("\n      AND ", $where);
        $sql = <<<SQL
WITH filtered_users AS (
    SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.avatar_url, u.created_at, u.updated_at, COUNT(*) OVER () AS total_items
    FROM users u
    WHERE {$whereSql}
    ORDER BY {$innerOrder}
    LIMIT {$perPage} OFFSET {$offset}
)
SELECT p.*, GROUP_CONCAT(CONCAT(r.id, ':', HEX(r.code), ':', HEX(r.name)) ORDER BY r.name, r.id SEPARATOR '|') AS roles_data,
       MAX(p.total_items) AS total_items
FROM filtered_users p
LEFT JOIN user_roles ur ON ur.user_id = p.id
LEFT JOIN roles r ON r.id = ur.role_id
GROUP BY p.id, p.email, p.first_name, p.last_name, p.status, p.avatar_url, p.created_at, p.updated_at
ORDER BY {$outerOrder}
SQL;
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('SET SESSION group_concat_max_len = 1048576');
        }

        $query = $this->db->query($sql, $binds);
        if (! $query instanceof ResultInterface) {
            throw new \RuntimeException('Unable to execute the admin user list projection.');
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $query->getResultArray();
        $total = $rows !== [] ? (int) ($rows[0]['total_items'] ?? 0) : 0;
        foreach ($rows as &$row) {
            $roles = [];
            if (is_string($row['roles_data'] ?? null) && $row['roles_data'] !== '') {
                foreach (explode('|', $row['roles_data']) as $serialized) {
                    $parts = explode(':', $serialized);
                    if (count($parts) !== 3 || ! ctype_digit($parts[0])) {
                        continue;
                    }
                    $code = self::decodeHex($parts[1]);
                    $name = self::decodeHex($parts[2]);
                    if ($code !== null && $name !== null) {
                        $roles[] = ['id' => (int) $parts[0], 'code' => $code, 'name' => $name];
                    }
                }
            }
            unset($row['roles_data'], $row['total_items']);
            $row['roles'] = $roles;
        }
        unset($row);
        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'from' => $rows === [] ? 0 : $offset + 1, 'to' => $rows === [] ? 0 : $offset + count($rows)];
    }

    /** @param array<string, mixed> $criteria */
    private function criteriaValue(array $criteria, string $key): mixed
    {
        $filter = $criteria['filter'] ?? null;
        return is_array($filter) && array_key_exists($key, $filter) ? $filter[$key] : ($criteria[$key] ?? null);
    }

    private static function decodeHex(string $encoded): ?string
    {
        if ($encoded === '') {
            return '';
        }

        if (strlen($encoded) % 2 !== 0 || ! ctype_xdigit($encoded)) {
            return null;
        }

        $decoded = hex2bin($encoded);

        return $decoded === false ? null : $decoded;
    }
}
