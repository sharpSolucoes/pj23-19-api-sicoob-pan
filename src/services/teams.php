<?php
require 'products.php';
class Teams extends API_configuration
{
    private $products;
    public function __construct()
    {
        parent::__construct();
        $this->products = new Products();
    }

    public function create(
        string $name,
        int $accountable,
        int $team_manager,
        array $users
    ) {
        $sql = 'INSERT INTO `teams`(`accountable`, `team_manager`, `name`) VALUES (' . $accountable . ', ' . $team_manager . ', "' . $name . '")';
        $team_created = $this->db_create($sql);
        if ($team_created) {
            $sql = 'INSERT INTO `teams_users`(`team_id`, `user_id`) VALUES ';
            $values = [];
            foreach ($users as $user) {
                $values[] = '(' . $team_created . ',' . $user->userId . ')';
            }
            $sql .= implode(',', $values);
            $new_team_user = $this->db_create($sql);
            if (!$new_team_user) {
                return false;
            }

            $sql = 'UPDATE `teams` SET `slug`="' . $this->slugify($team_created . '-' . $name) . '" WHERE `id`=' . $team_created;
            $this->db_update($sql);

            return true;
        } else {
            return false;
        }
    }

    public function read()
    {
        $sql = 'SELECT T.`id`, T.`name`, (SELECT U.`name` FROM `users` U WHERE U.`id` = T.`accountable`) AS `accountable`, (SELECT U.`name` FROM `users` U WHERE U.`id` = T.`team_manager`) AS `teamManager`, T.`slug` FROM `teams` T ORDER BY `name`';
        $teams = $this->db_read($sql);
        if ($teams) {
            $response = [];
            while ($team = $this->db_object($teams)) {
                $team->name = mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8');
                $team->accountable = mb_convert_case($team->accountable, MB_CASE_TITLE, 'UTF-8');
                $team->teamManager = mb_convert_case($team->teamManager, MB_CASE_TITLE, 'UTF-8');
                $response[] = $team;
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug)
    {
        $sql = 'SELECT `id`, `accountable`, `team_manager`, `name` FROM `teams` WHERE `slug` = "' . $slug . '"';
        $team = $this->db_read($sql);
        if ($this->db_num_rows($team) == 1) {
            $team = $this->db_object($team);
            $team->id = (int) $team->id;
            return [
                'id' => $team->id,
                'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                'accountable' => (int) $team->accountable,
                'teamManager' => (int) $team->team_manager,
                'users' => $this->read_teams_users_by_team_id($team->id)
            ];
        } else {
            return false;
        }
    }

    private function read_teams_users_by_team_id(int $team_id)
    {
        $sql = 'SELECT `user_id` FROM `teams_users` WHERE `team_id` = ' . $team_id;
        $teams_users = $this->db_read($sql);
        if ($this->db_num_rows($teams_users) > 0) {
            $response = [];
            while ($team_user = $this->db_object($teams_users)) {
                $response[] = [
                    'userId' => (int) $team_user->user_id
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `accountable`, `name` FROM `teams` WHERE `id` = ' . $id;
        $team = $this->db_read($sql);
        if ($this->db_num_rows($team) == 1) {
            $team = $this->db_object($team);
            $team->id = (int) $team->id;
            return [
                'id' => $team->id,
                'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                'accountable' => (int) $team->accountable,
                'users' => $this->read_teams_users_by_team_id($team->id)
            ];
        } else {
            return false;
        }
    }

    public function update(
        int $id,
        string $name,
        int $accountable,
        int $team_manager,
        array $users
    ) {
        $old_team = $this->read_by_id($id);
        $sql = '
        UPDATE `teams` SET
            `name`="' . $name . '",
            `accountable`=' . $accountable . ',
            `team_manager`=' . $team_manager . ',
            `slug`="' . $this->slugify($id . '-' . $name) . '"
        WHERE `id`=' . $id;
        $team_updated = $this->db_update($sql);
        if ($team_updated) {
            $sql = 'DELETE FROM `teams_users` WHERE `team_id`=' . $id;
            $this->db_delete($sql);
            $sql = 'INSERT INTO `teams_users`(`team_id`, `user_id`) VALUES ';
            $values = [];
            foreach ($users as $user) {
                $values[] = '(' . $id . ',' . $user->userId . ')';
            }
            $sql .= implode(',', $values);
            $new_team_user = $this->db_create($sql);
            if (!$new_team_user) {
                return false;
            }

            return [
                'old' => $old_team,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug)
    {
        $old_team = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `teams` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_team;
        } else {
            return false;
        }
    }
}
