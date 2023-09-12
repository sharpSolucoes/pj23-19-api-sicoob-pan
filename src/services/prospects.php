<?php
require_once "users.php";
class Prospects extends API_configuration {
    private $users;
    public function __construct() {
        parent::__construct();
        $this->users = new Users();
    }
    public function create(
        int $user_id,
        string $action,
        string $channel,
        int $interest,
        string $description,
        array $associate
    ) {
        $values = '
            ' . $user_id . ',
            "' . date('Y-m-d H:i:s') . '",
            "' . $action . '",
            "' . $channel . '",
            ' . $interest . ',
            "' . $description . '",
            "' . $associate['name'] . '",
            "' . $associate['numberAccount'] . '"
        ';
        $sql = 'INSERT INTO `prospects`(`user_id`, `date`, `action`, `channel`, `interest`, `description`, `associate_name`, `associate_number_account`) VALUES (' . $values . ')';
        $prospection_created = $this->db_create($sql);
        if ($prospection_created) {
            $slug = $this->slugify($prospection_created . '-' . $associate['name']);
            $sql = 'UPDATE `prospects` SET `slug`="' . $slug . '" WHERE `id`=' . $prospection_created;
            $this->db_update($sql);

            return $this->read_by_slug($slug);
        } else {
            return false;
        }
    }

    public function read(
        string $initial_date = null,
        string $final_date = null
    ) {
        $query_parm = '';
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        }

        $sql = 'SELECT `slug`, `date`, `user_id`, `action`, `associate_name`, `associate_number_account` FROM `prospects` ' . $query_parm . ' ORDER BY `date` DESC';
        $prospections = $this->db_read($sql);
        if ($prospections) {
            $response = [];
            while ($prospection = $this->db_object($prospections)) {
                $response[] = [
                    'date' => $prospection->date,
                    'user' => $this->users->read_by_id((int) $prospection->user_id),
                    'action' => $prospection->action,
                    'associate' => [
                        'name' => $prospection->associate_name,
                        'numberAccount' => $prospection->associate_number_account
                    ],
                    'slug' => $prospection->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }
    
    public function read_reports(
        string $initial_date = null,
        string $final_date = null
    ) {
        $query_parm = '';
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        }

        $sql = 'SELECT `date`, `user_id`, `action`, `associate_name`, `associate_number_account`, `description` FROM `prospects` ' . $query_parm . ' ORDER BY `date` DESC';
        $prospections = $this->db_read($sql);
        if ($prospections) {
            $response = [];
            while ($prospection = $this->db_object($prospections)) {
                $response[] = [
                    'date' => $prospection->date,
                    'user' => $this->users->read_by_id((int) $prospection->user_id),
                    'action' => $prospection->action,
                    'associate' => [
                        'name' => $prospection->associate_name,
                        'numberAccount' => $prospection->associate_number_account
                    ],
                    'description' => $prospection->description
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT * FROM `prospects` WHERE `slug` = "' . $slug . '"';
        $prospections = $this->db_read($sql);
        if ($prospections) {
            $prospections = $this->db_object($prospections);

            return [
                'id' => (int) $prospections->id,
                'user' => $this->users->read_by_id((int) $prospections->user_id),
                'date' => $prospections->date,
                'action' => $prospections->action,
                'channel' => $prospections->channel,
                'interest' => (int) $prospections->interest,
                'description' => $prospections->description,
                'associate' => [
                    'name' => $prospections->associate_name,
                    'numberAccount' => $prospections->associate_number_account
                ],
                'slug' => $prospections->slug
            ];
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `prospects` WHERE `id`=' . $id;
        $prospection = $this->db_read($sql);
        if ($prospection) {
            $prospection = $this->db_object($prospection);
            return [
                'id' => (int) $prospection->id,
                'user' => $this->users->read_by_id((int) $prospection->user_id),
                'date' => $prospection->date,
                'action' => $prospection->action,
                'channel' => $prospection->channel,
                'interest' => (int) $prospection->interest,
                'description' => $prospection->description,
                'associate' => [
                    'name' => $prospection->associate_name,
                    'numberAccount' => $prospection->associate_number_account
                ],
                'slug' => $prospection->slug
            ];
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $action,
        string $channel,
        int $interest,
        string $description,
        array $associate
    ) {
        $old_prospection = $this->read_by_id($id);

        $sql = '
        UPDATE `prospects` SET
            `action`="' . $action . '",
            `channel`="' . $channel . '",
            `interest`=' . $interest . ',
            `description`="' . $description . '",
            `associate_name`="' . $associate['name'] . '",
            `associate_number_account`="' . $associate['numberAccount'] . '",
            `slug`="' . $this->slugify($id . '-' . $associate['name']) . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            return [
                'old' => $old_prospection,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug) {
        $old_prospection = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `prospects` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_prospection;
        } else {
            return false;
        }
    }
}