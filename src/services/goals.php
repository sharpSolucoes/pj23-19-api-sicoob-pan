<?php
require 'products.php';
class Goals extends API_configuration
{
    private $products;
    public function __construct()
    {
        parent::__construct();
        $this->products = new Products();
    }
    public function create(
        string $description,
        array $products
    ) {
        $sql = 'INSERT INTO `goals` (`description`) VALUES ("' . $description . '")';
        $goal_created = $this->db_create($sql);
        if ($goal_created) {
            $products_created = $this->create_products($goal_created, $products);
            if ($products_created) {
                $sql = 'UPDATE `goals` SET `slug`="' . $this->slugify($goal_created . '-' . $description) . '" WHERE `id`=' . $goal_created;
                $this->db_update($sql);
                return [
                    'id' => $goal_created,
                    'products' => $products_created
                ];
            } else {
                return false;
            }
        }
    }

    private function create_products(int $goal_id, array $products)
    {
        $sql = 'INSERT INTO `goals_products` (`goal_id`, `product_id`, `goal`) VALUES ';
        $values = '';
        foreach ($products as $product) {
            $values .= '(' . $goal_id . ', ' . $product->productId . ', ' . $product->goal . '),';
        }
        $values = substr($values, 0, -1);
        $sql .= $values;
        return $this->db_create($sql);
    }

    public function read()
    {
        $sql = 'SELECT `description`, `slug`, `id` FROM `goals`';
        $goals = $this->db_read($sql);
        if ($this->db_num_rows($goals) > 0) {
            $response = [];
            while ($goal = $this->db_object($goals)) {
                $response[] = $goal;
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug)
    {
        $sql = 'SELECT `id`, `description` FROM `goals` WHERE `slug` = "' . $slug . '"';
        $goals = $this->db_read($sql);
        if ($goals) {
            $goals = $this->db_object($goals);

            return [
                'id' => (int) $goals->id,
                'description' => $goals->description,
                'products' => $this->read_products_and_goals_by_goal_id((int) $goals->id)
            ];
        } else {
            return [];
        }
    }

    private function read_products_and_goals_by_goal_id(int $goal_id)
    {
        $sql = 'SELECT `product_id`, `goal` FROM `goals_products` WHERE `goal_id` = ' . $goal_id;
        $get_products = $this->db_read($sql);
        if ($get_products) {
            $response = [];
            while ($product = $this->db_object($get_products)) {
                $response[] = [
                    'productId' => $this->products->read_by_id($product->product_id)->id,
                    'goal' => $product->goal
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `description` FROM `goals` WHERE `id`=' . $id;
        $goals = $this->db_read($sql);
        if ($goals) {
            $goals = $this->db_object($goals);

            return [
                'id' => (int) $goals->id,
                'description' => $goals->description,
                'products' => $this->read_products_and_goals_by_goal_id((int) $goals->id)
            ];
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $description,
        array $products
    ) {
        $old_goal = $this->read_by_id($id);

        $sql = '
        UPDATE `goals` SET
            `description`="' . $description . '",
            `slug`="' . $this->slugify($id . '-' . $description) . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {

            $sql = 'DELETE FROM `goals_products` WHERE `goal_id`=' . $id;
            $this->db_delete($sql);

            $products_created = $this->create_products($id, $products);

            if ($products_created) {
                return [
                    'old' => $old_goal,
                    'new' => $this->read_by_id($id)
                ];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function delete(string $slug)
    {
        $old_goal = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `goals` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_goal;
        } else {
            return false;
        }
    }
}
