<?php
require_once 'products.php';
class Goals extends API_configuration
{
    private $products;
    public function __construct(Products $products)
    {
        parent::__construct();
        $this->products = $products;
    }
    public function create(
        string $description,
        string $global_goal,
        array $modules,
        array $products
    ) {
        $sql = 'INSERT INTO `goals` (`description`, `global_goal`) VALUES ("' . $description . '", "' . $global_goal . '")';
        $goal_created = $this->db_create($sql);
        if ($goal_created) {
            if ($this->create_modules($goal_created, $modules) && $this->create_products($goal_created, $products)) {
                $sql = 'UPDATE `goals` SET `slug`="' . $this->slugify($goal_created . '-' . $description) . '" WHERE `id`=' . $goal_created;
                $this->db_update($sql);
                return [
                    'id' => $goal_created,
                    'description' => $description,
                    'global_goal' => $global_goal,
                    'modules' => $modules,
                    'products' => $products
                ];
            } else {
                $sql = 'DELETE FROM `goals` WHERE `id`=' . $goal_created;
                $this->db_delete($sql);
                return false;
            }
        } else {
            return false;
        }
    }

    private function create_modules(
        int $goal_id,
        array $modules
    ) {
        $sql = 'INSERT INTO `goals_modules` (`goal_id`, `module_id`, `goal`) VALUES ';
        $values = '';
        foreach ($modules as $module) {
            $values .= '(' . $goal_id . ', ' . $module->moduleId . ', ' . $module->goal . '),';
        }
        $values = substr($values, 0, -1);
        $sql .= $values;
        return $this->db_create($sql);
    }

    private function create_products(int $goal_id, array $products)
    {
        if (count($products) === 0) return true;
        $sql = 'INSERT INTO `goals_products` (`goal_id`, `product_id`) VALUES ';
        $values = '';
        foreach ($products as $product) {
            $values .= '(' . $goal_id . ', ' . $product->productId . '),';
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
        $sql = 'SELECT `id`, `description`, `global_goal` FROM `goals` WHERE `slug` = "' . $slug . '"';
        $goals = $this->db_read($sql);
        if ($goals) {
            $goals = $this->db_object($goals);

            return [
                'id' => (int) $goals->id,
                'description' => $goals->description,
                'globalGoal' => $goals->global_goal,
                'modules' => $this->read_modules_and_goals_by_goal_id((int) $goals->id),
                'products' => $this->read_products_and_goals_by_goal_id((int) $goals->id)
            ];
        } else {
            return [];
        }
    }

    private function read_modules_and_goals_by_goal_id(int $goal_id)
    {
        $sql = 'SELECT `module_id`, `goal` FROM `goals_modules` WHERE `goal_id` = ' . $goal_id;
        $get_modules = $this->db_read($sql);
        if ($get_modules) {
            $response = [];
            while ($module = $this->db_object($get_modules)) {
                $response[] = [
                    'moduleId' => $module->module_id,
                    'goal' => $module->goal
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    private function read_products_and_goals_by_goal_id(int $goal_id)
    {
        $sql = 'SELECT `product_id` FROM `goals_products` WHERE `goal_id` = ' . $goal_id;
        $get_products = $this->db_read($sql);
        if ($get_products) {
            $response = [];
            while ($product = $this->db_object($get_products)) {
                $response[] = [
                    'productId' => $this->products->read_by_id($product->product_id)->id
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `description`, `global_goal` FROM `goals` WHERE `id` = ' . $id;
        $goals = $this->db_read($sql);
        if ($goals) {
            $goals = $this->db_object($goals);

            return [
                'id' => (int) $goals->id,
                'description' => $goals->description,
                'globalGoal' => $goals->global_goal,
                'modules' => $this->read_modules_and_goals_by_goal_id((int) $goals->id),
                'products' => $this->read_products_and_goals_by_goal_id((int) $goals->id)
            ];
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $description,
        string $global_goal,
        array $modules,
        array $products
    ) {
        $old_goal = $this->read_by_id($id);

        $sql = '
        UPDATE `goals` SET
            `description`="' . $description . '",
            `global_goal`="' . $global_goal . '",
            `slug`="' . $this->slugify($id . '-' . $description) . '"
        WHERE `id`=' . $id;
        if ($this->db_update($sql)) {
            $sql = 'DELETE FROM `goals_modules` WHERE `goal_id`=' . $id;
            $this->db_delete($sql);

            $modules_created = $this->create_modules($id, $modules);

            $sql = 'DELETE FROM `goals_products` WHERE `goal_id`=' . $id;
            $this->db_delete($sql);

            $products_created = $this->create_products($id, $products);

            if ($products_created && $modules_created) {
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
