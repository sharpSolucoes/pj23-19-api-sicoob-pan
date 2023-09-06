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
        array $products
    ) {
        $sql = 'INSERT INTO `teams`(`name`) VALUES ("' . $name . '")';
        $team_created = $this->db_create($sql);
        if ($team_created) {
            foreach ($products as $product) {
                $sql = 'INSERT INTO `teams_products`(`team_id`, `product_id`, `goal`) VALUES (' . $team_created . ',' . $product->id . ', "' . $product->goal . '")';
                $new_team_product = $this->db_create($sql);
                if(!$new_team_product) {
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
        $sql = 'SELECT `id`, `name`, (SELECT COUNT(*) FROM `teams_products` WHERE `team_id` = T.`id`) AS `number_of_products`, `slug` FROM `teams` T ORDER BY `name`';
        $teams = $this->db_read($sql);
        if ($teams) {
            $response = [];
            while ($team = $this->db_object($teams)) {
                $response[] = [
                    'id' => (int) $team->id,
                    'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                    'slug' => $team->slug,
                    'numberOfProducts' => (int) $team->number_of_products
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
                'products' => $this->read_teams_products_by_team_id($team->id)
            ];
        } else {
            return [];
        }
    }

    private function read_teams_products_by_team_id(int $team_id) {
        $sql = 'SELECT `product_id`, `goal` FROM `teams_products` WHERE `team_id` = ' . $team_id;
        $teams_products = $this->db_read($sql);
        if ($this->db_num_rows($teams_products) > 0) {
            $response = [];
            while ($team_product = $this->db_object($teams_products)) {
                $response[] = [
                    'id' => (int) $team_product->product_id,
                    'goal' => $team_product->goal
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `teams` WHERE `id`=' . $id;
        $team = $this->db_read($sql);
        if ($team) {
            $team = $this->db_object($team);
            $team->id = (int) $team->id;
            return [
                'id' => $team->id,
                'name' => mb_convert_case($team->name, MB_CASE_TITLE, 'UTF-8'),
                'products' => $this->read_teams_products_by_team_id($team->id)
            ];
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $name,
        array $products
    ) {
        $old_team = $this->read_by_id($id);
        $sql = '
        UPDATE `teams` SET
            `name`="' . $name . '",
            `slug`="' . $this->slugify($id . '-' . $name) . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            $sql = 'DELETE FROM `teams_products` WHERE `team_id`=' . $id;
            $this->db_delete($sql);
            foreach ($products as $product) {
                $sql = 'INSERT INTO `teams_products`(`team_id`, `product_id`, `goal`) VALUES (' . $id . ',' . $product->id . ', "' . $product->goal . '")';
                $new_team_product = $this->db_create($sql);
                if(!$new_team_product) {
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