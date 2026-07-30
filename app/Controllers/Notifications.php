<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;

class Notifications extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct('');  // No module check — notifications are system-wide
        $this->notification = model(Notification::class);
    }

    public function index()
    {
        $employee = $this->employee->get_logged_in_employee_info();
        if (! $employee) {
            return redirect()->to('/login');
        }

        $personId   = (int) $employee->person_id;
        $notifications = $this->notification->where('person_id', $personId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->findAll();

        return $this->response->setJSON([
            'notifications' => $notifications,
            'unread_count'  => $this->notification->getUnreadCount($personId),
        ]);
    }

    public function unread_count()
    {
        $employee = $this->employee->get_logged_in_employee_info();
        if (! $employee) {
            return $this->response->setJSON(['count' => 0]);
        }

        return $this->response->setJSON([
            'count' => $this->notification->getUnreadCount((int) $employee->person_id),
        ]);
    }

    public function stream()
    {
        $employee = $this->employee->get_logged_in_employee_info();
        if (! $employee) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $personId  = (int) $employee->person_id;
        $sinceRaw  = $this->request->getGet('since') ?: date('c', strtotime('-5 seconds'));
        // Convert ISO 8601 to MySQL datetime format
        $lastCheck = date('Y-m-d H:i:s', strtotime($sinceRaw));

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');

        echo "event: connected\ndata: {\"status\":\"ok\",\"person_id\":{$personId}}\n\n";
        if (ob_get_level()) ob_flush();
        flush();

        $startTime = time();
        $timeout   = 25;

        while ((time() - $startTime) < $timeout) {
            $notifications = $this->notification->getRecentSince($personId, $lastCheck);

            if (! empty($notifications)) {
                $count = $this->notification->getUnreadCount($personId);
                echo "event: notifications\ndata: " . json_encode([
                    'notifications' => $notifications,
                    'unread_count'  => $count,
                ]) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
                // Update lastCheck to newest notification
                $lastCheck = $notifications[0]->created_at;
            }

            echo "event: heartbeat\ndata: {\"time\":\"" . date('c') . "\"}\n\n";
            if (ob_get_level()) ob_flush();
            flush();

            sleep(3);
        }

        echo "event: close\ndata: {\"reason\":\"timeout\"}\n\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    public function read(int $id = 0)
    {
        $this->notification->markRead($id);

        return $this->response->setJSON(['success' => true]);
    }

    public function read_all()
    {
        $employee = $this->employee->get_logged_in_employee_info();
        if (! $employee) {
            return $this->response->setJSON(['success' => false]);
        }

        $this->notification->markAllRead((int) $employee->person_id);

        return $this->response->setJSON(['success' => true]);
    }
}
