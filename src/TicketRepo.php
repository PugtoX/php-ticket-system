<?php
declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use PDO;

final class TicketRepo
{
    public const PER_PAGE_CHOICES = [10, 20, 50];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int} */
    public function paginate(?string $status, string $q, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = in_array($perPage, self::PER_PAGE_CHOICES, true) ? $perPage : 20;

        $where = [];
        $args = [];

        if ($status !== null && $status !== '' && TicketStatus::isValid($status)) {
            $where[] = 'status = :status';
            $args[':status'] = $status;
        }

        $q = trim($q);
        if ($q !== '') {
            $q = mb_substr($q, 0, 100);
            $where[] = '(subject LIKE :q_subject OR body LIKE :q_body)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $args[':q_subject'] = $like;
            $args[':q_body'] = $like;
        }

        $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM tickets' . $sqlWhere);
        $countStmt->execute($args);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT id, subject, customer_email, status, created_at, updated_at FROM tickets'
            . $sqlWhere
            . ' ORDER BY FIELD(status, "open", "in_progress", "closed"), created_at DESC'
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $subject, string $body, string $email): int
    {
        $subject = trim($subject);
        $body = trim($body);
        $email = trim($email);

        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 200) {
            throw new InvalidArgumentException('subject must be 3-200 characters');
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            throw new InvalidArgumentException('body must be 1-5000 characters');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('customer_email is not a valid address');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (subject, body, customer_email, status) VALUES (:s, :b, :e, :st)'
        );
        $stmt->execute([
            ':s' => $subject,
            ':b' => $body,
            ':e' => $email,
            ':st' => TicketStatus::OPEN,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $to, int $actorId): bool
    {
        $ticket = $this->find($id);
        if ($ticket === null) {
            return false;
        }
        $from = (string) $ticket['status'];
        if (!TicketStatus::canTransition($from, $to)) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE tickets SET status = :to WHERE id = :id');
            $update->execute([':to' => $to, ':id' => $id]);

            $audit = $this->pdo->prepare(
                'INSERT INTO audit_log (actor_id, ticket_id, from_status, to_status) VALUES (:a, :t, :f, :to)'
            );
            $audit->execute([':a' => $actorId, ':t' => $id, ':f' => $from, ':to' => $to]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return true;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $rows = $this->pdo
            ->query('SELECT status, COUNT(*) AS n FROM tickets GROUP BY status')
            ->fetchAll();

        $out = array_fill_keys(TicketStatus::STATES, 0);
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        $out['total'] = array_sum($out);

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function auditFor(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.from_status, a.to_status, a.at, u.email AS actor
             FROM audit_log a JOIN users u ON u.id = a.actor_id
             WHERE a.ticket_id = :id ORDER BY a.at DESC, a.id DESC'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll();
    }
}
