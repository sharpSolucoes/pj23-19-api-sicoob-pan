<?php
require 'products.php';
class Teams extends API_configuration {
    private $products;
    public function __construct() {
        parent::__construct();
        $this->products = new Products();
    }

    public function create(
        string $name,
        array $users
    ) {
        $sql = 'INSERT INTO `teams`(`name`) VALUES ("' . $name . '")';
        $team_created = $this->db_create($sql);
        if ($team_created) {
            foreach ($users as $user) {
                $sql = 'INSERT INTO `teams_users`(`team_id`, `user_id`) VALUES (' . $team_created . ',' . $user->id . ')';
                $new_team_user = $this->db_create($sql);
                if(!$new_team_user) {
                    return false;
                }
            }

            $sql = 'UPDATE `teams` SET `slug`="' . $this->slugify($team_created . '-' . $name) . '" WHERE `id`=' . $team_created;
            $this->db_update($sql);

            return true;
        } else {    
            return false;
        }
    }

    public function read() {
        $sql = 'SELECT `id`, `name`, (SELECT COUNT(*) FROM `teams_users` WHERE `team_id` = T.`id`) AS `number_of_users`, `slug` FROM `teams` T ORDER BY `name`';
        $teams = $this->db_read($sql);
        if ($teams) {
            $response = [];
            while ($team = $this->db_object($teams)) {
                $response[] = [
                    'id' => (int) $team->id,
                    'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                    'slug' => $team->slug,
                    'numberOfUsers' => (int) $team->number_of_users
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT `id`, `name` FROM `teams` WHERE `slug` = "' . $slug . '"';
        $team = $this->db_read($sql);
        if ($team) {
            $team = $this->db_object($team);
            $team->id = (int) $team->id;
            return [
                'id' => $team->id,
                'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                'users' => $this->read_teams_users_by_team_id($team->id)
            ];
        } else {
            return [];
        }
    }

    private function read_teams_users_by_team_id(int $team_id) {
        $sql = 'SELECT `user_id` FROM `teams_users` WHERE `team_id` = ' . $team_id;
        $teams_users = $this->db_read($sql);
        if ($this->db_num_rows($teams_users) > 0) {
            $response = [];
            while ($team_user = $this->db_object($teams_users)) {
                $response[] = [
                    'id' => (int) $team_user->user_id
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT `id`, `name` FROM `teams` WHERE `id` = ' . $id;
        $team = $this->db_read($sql);
        if ($team) {
            $team = $this->db_object($team);
            $team->id = (int) $team->id;
            return [
                'id' => $team->id,
                'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                'users' => $this->read_teams_users_by_team_id($team->id)
            ];
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $name,
        array $users
    ) {
        $old_team = $this->read_by_id($id);
        $sql = '
        UPDATE `teams` SET
            `name`="' . $name . '",
            `slug`="' . $this->slugify($id . '-' . $name) . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            $sql = 'DELETE FROM `teams_users` WHERE `team_id`=' . $id;
            $this->db_delete($sql);
            foreach ($users as $user) {
                $sql = 'INSERT INTO `teams_users`(`team_id`, `user_id`) VALUES (' . $id . ',' . $user->id . ')';
                $new_team_user = $this->db_create($sql);
                if(!$new_team_user) {
                    return false;
                }
            }

            return [
                'old' => $old_team,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug) {
        $old_team = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `teams` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_team;
        } else {
            return false;
        }
    }
}