<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Notification extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'person_id', 'title', 'message', 'type', 'link', 'is_read', 'created_at',
    ];

    public function createNotification(int $personId, string $title, string $message = '', string $type = 'system', ?string $link = null): int|false
    {
        return $this->insert([
            'person_id'  => $personId,
            'title'      => $title,
            'message'    => $message,
            'type'       => $type,
            'link'       => $link,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getUnread(int $personId, int $limit = 20): array
    {
        return $this->where('person_id', $personId)
            ->where('is_read', 0)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getUnreadCount(int $personId): int
    {
        return $this->where('person_id', $personId)
            ->where('is_read', 0)
            ->countAllResults();
    }

    public function markRead(int $id): bool
    {
        return $this->update($id, ['is_read' => 1]);
    }

    public function markAllRead(int $personId): bool
    {
        return $this->where('person_id', $personId)
            ->where('is_read', 0)
            ->set(['is_read' => 1])
            ->update();
    }

    public function getRecentSince(int $personId, string $since, int $limit = 20): array
    {
        $sinceDate = date('Y-m-d H:i:s', strtotime($since));

        return $this->where('person_id', $personId)
            ->where('created_at >', $sinceDate)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function broadcastToAll(string $title, string $message = '', string $type = 'system', ?string $link = null): int
    {
        $db = db_connect();
        $employees = $db->table('employees')->where('deleted', 0)->get()->getResult();
        $count = 0;
        foreach ($employees as $emp) {
            $result = $this->createNotification((int) $emp->person_id, $title, $message, $type, $link);
            if ($result !== false) {
                $count++;
            }
        }

        return $count;
    }

    public function broadcastToPerson(int $personId, string $title, string $message = '', string $type = 'system', ?string $link = null): int|false
    {
        return $this->createNotification($personId, $title, $message, $type, $link);
    }
}
