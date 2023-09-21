<?php
require_once "agencies.php";
require 'users.php';
class Ideas extends API_configuration {
    private $users;
    private $agencies;
    public function __construct() {
        parent::__construct();
        $this->users = new Users();
        $this->agencies = new Agencies();
    }
    public function create(
        int $user_id,
        int $agency_id,
        bool $urgent,
        string $description
    ) {
        $user = $this->users->read_by_id($user_id);
        $values = '
        ' . $user->id . ',
        ' . $agency_id . ',
        "' . ($urgent ? "true" : "false") . '",
        "' . $description . '",
        "' . date('Y-m-d H:i:s') . '",
        "Em andamento"
        ';
        $sql = 'INSERT INTO `ideas`(`user_id`, `agency_id`, `urgent`, `description`, `opening_date`, `status`) VALUES (' . $values . ')';
        $idea_created = $this->db_create($sql);
        if ($idea_created) {
            $slug = $this->slugify($idea_created . '-' . $user->name . '-' . ($urgent ? "true" : "false"));
            $sql = 'UPDATE `ideas` SET `slug`="' . $slug . '" WHERE `id`=' . $idea_created;
            $this->db_update($sql);

            return $this->read_by_slug($slug);
        } else {
            return false;
        }
    }

    public function read(
        int $user_id,
        string $initial_date = null,
        string $final_date = null
    ) {
        $query_parm = '';
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
            $query_parm = ' WHERE `opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
            $query_parm = ' WHERE `opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        }

        $user = $this->users->read_by_id($user_id);
        $sql = 'SELECT `opening_date`, `closing_date`, `user_id`, `agency_id`, `status`, `urgent`, `slug` FROM `ideas` ' . $query_parm . ' ' . ($user->position == "Usuário" ? "AND `user_id` = " . $user->id . " AND `status` <> 'Encerrada'" : '') . ' ORDER BY `opening_date` DESC';
        $ideas = $this->db_read($sql);
        if ($ideas) {
            $response = [];
            while ($idea = $this->db_object($ideas)) {
                $response[] = [
                    'urgent' => $idea->urgent == "true" ? true : false,
                    'openingDate' => $idea->opening_date,
                    'closingDate' => $idea->closing_date ? $idea->closing_date : null,
                    'employee' => $this->users->read_by_id($idea->user_id),
                    'agency' => $this->agencies->read_by_id($idea->agency_id),
                    'status' => $idea->status,
                    'slug' => $idea->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT * FROM `ideas` WHERE `slug` = "' . $slug . '"';
        $ideas = $this->db_read($sql);
        if ($ideas) {
            $ideas = $this->db_object($ideas);

            return [
                'id' => (int) $ideas->id,
                'agency' => $this->agencies->read_by_id((int) $ideas->agency_id),
                'employee' => $this->users->read_by_id((int) $ideas->user_id),
                'urgent' => $ideas->urgent == "true" ? true : false,
                'description' => $ideas->description,
                'openingDate' => $ideas->opening_date,
                'closingDate' => $ideas->closing_date ? $ideas->closing_date : null,
                'status' => $ideas->status,
                'slug' => $ideas->slug
            ];
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `ideas` WHERE `id`=' . $id;
        $idea = $this->db_read($sql);
        if ($idea) {
            $idea = $this->db_object($idea);
            $idea->id = (int) $idea->id;
            $idea->user_id = (int) $idea->user_id;
            $idea->agency_id = (int) $idea->agency_id;
            return $idea;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        int $agency_id,
        string $description,
        string $status,
        bool $urgent
    ) {
        $old_idea = $this->read_by_id($id);

        if ($status == "Encerrada") {
            $sql = 'SELECT `status` FROM `ideas` WHERE `id`=' . $id;
            $idea = $this->db_read($sql);
            $idea = $this->db_object($idea);
            if ($idea->status != "Encerrada") {
                $sql = 'UPDATE `ideas` SET `closing_date`="' . date('Y-m-d H:i:s') . '" WHERE `id`=' . $id;
                $this->db_update($sql);
            }
        }

        $sql = '
        UPDATE `ideas` SET
            `description`="' . $description . '",
            `status`="' . $status . '",
            `agency_id`="' . $agency_id . '",
            `urgent`="' . ($urgent ? "true" : "false") . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            return [
                'old' => $old_idea,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug) {
        $old_idea = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `ideas` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_idea;
        } else {
            return false;
        }
    }
}