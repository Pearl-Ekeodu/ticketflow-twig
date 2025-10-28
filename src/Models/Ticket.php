<?php

namespace App\Models;

use PDO;

class Ticket
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tickets (title, description, status, priority, user_id) VALUES (?, ?, ?, ?, ?)'
        );
        
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['status'] ?? 'open',
            $data['priority'] ?? null,
            $data['user_id']
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tickets WHERE id = ?');
        $stmt->execute([$id]);
        
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    public function findByUserId(int $userId, array $filters = []): array
    {
        $sql = 'SELECT * FROM tickets WHERE user_id = ?';
        $params = [$userId];
        
        // Add status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        
        // Add search filter
        if (!empty($filters['search'])) {
            $sql .= ' AND (title LIKE ? OR description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        // Add pagination
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int) $filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= ' OFFSET ?';
                $params[] = (int) $filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = []): array
    {
        $sql = 'SELECT * FROM tickets';
        $params = [];
        $conditions = [];
        
        // Add status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        // Add search filter
        if (!empty($filters['search'])) {
            $conditions[] = '(title LIKE ? OR description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        // Add pagination
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ?';
            $params[] = (int) $filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= ' OFFSET ?';
                $params[] = (int) $filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function update(int $id, array $data): bool
    {
        $allowedFields = ['title', 'description', 'status', 'priority'];
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $fields[] = "$field = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = 'UPDATE tickets SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tickets WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getStats(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed
             FROM tickets WHERE user_id = ?'
        );
        
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        return [
            'total' => (int) $stats['total'],
            'open' => (int) $stats['open'],
            'inProgress' => (int) $stats['in_progress'],
            'closed' => (int) $stats['closed']
        ];
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->findByUserId($userId, ['limit' => $limit]);
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM tickets';
        $params = [];
        $conditions = [];
        
        // Add status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        // Add search filter
        if (!empty($filters['search'])) {
            $conditions[] = '(title LIKE ? OR description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
}
