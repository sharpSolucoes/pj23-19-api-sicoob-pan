<?php
class Notifications extends API_configuration
{
    public function create(
        string $title,
        string $message,
        string $href,
    ) {
        $sql = 'INSERT INTO `notifications`(`title`, `message`, `href`, `created_at`) VALUES ("' . $title . '", "' . $message . '", "' . $href . '", "' . date('Y-m-d H:i:s') . '")';
        $notification_created = $this->db_create($sql);
        if ($notification_created) {
            return $this->create_users($notification_created);
        } else {
            return false;
        }
    }

    protected function create_users(
        int $notification_id
    ) {
        $sql = 'SELECT `id` FROM `users` WHERE `position` = "Suporte" OR `position` = "Administrador";';
        $get_users = $this->db_read($sql);
        if ($this->db_num_rows($get_users) > 0) {
            $sql = 'INSERT INTO `notifications_users`(`notification_id`, `user_id`) VALUES ';
            while ($user = $this->db_object($get_users)) {
                $sql .= '(' . $notification_id . ', ' . $user->id . '),';
            }
            $sql = substr($sql, 0, -1);
            if ($this->db_create($sql)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function read(int $user_id)
    {
        $sql = 'SELECT N.`id`, N.`title`, N.`message`, N.`href` FROM `notifications` N INNER JOIN `notifications_users` NU ON N.`id` = NU.`notification_id` WHERE NU.`user_id` = ' . $user_id . ' AND `is_read` = "false" ORDER BY N.`created_at` DESC';
        $get_notifications = $this->db_read($sql);
        if ($get_notifications) {
            $response = [];
            while ($notification = $this->db_object($get_notifications)) {
                $response[] = [
                    'id' => (int) $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'href' => $notification->href
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_numbers(
        int $user_id
    ) {
        $sql = 'SELECT COUNT(`id`) AS `total` FROM `notifications_users` WHERE `user_id` = ' . $user_id . ' AND `is_read` = "false"';
        $get_notifications = $this->db_read($sql);
        if ($get_notifications) {
            $notification = $this->db_object($get_notifications);
            return [
                'number' => (int) $notification->total
            ];
        } else {
            return 0;
        }
    }

    public function view(
        int $user_id,
        int $notification_id
    ) {
        $sql = 'UPDATE `notifications_users` SET `is_read` = "true" WHERE `user_id` = ' . $user_id . ' AND `notification_id` = ' . $notification_id;
        if ($this->db_update($sql)) {
            return true;
        } else {
            return false;
        }
    }

    public function mark_as_read(
        int $user_id
    ) {
        $sql = 'UPDATE `notifications_users` SET `is_read` = "true" WHERE `user_id` = ' . $user_id;
        if ($this->db_update($sql)) {
            return true;
        } else {
            return false;
        }
    }
}
