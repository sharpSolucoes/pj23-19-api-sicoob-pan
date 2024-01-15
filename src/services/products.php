<?php
require_once 'modules.php';
require_once 'goals.php';
require_once 'users.php';
class Products extends API_configuration
{
    private $users;
    private $goals;
    private $modules;
    public function __construct()
    {
        parent::__construct();
        $this->users = new Users();
        $this->goals = new Goals($this);
        $this->modules = new Modules();
    }
    public function create(
        string $description,
        string $card,
        string $status,
        string $points,
        bool $is_quantity,
        bool $is_punctuation,
        bool $is_accumulated,
        int $min_quantity,
        string $min_value
    ) {
        $sql = 'INSERT INTO `products`(`description`, `card`, `status`, `points`, `is_quantity`, `is_punctuation`, `is_accumulated`, `min_value`, `min_quantity`) VALUES ("' . $description . '", "' . $card . '", "' . $status . '", "' . $this->value_formatted_for_save($points) . '", "' . ($is_quantity ? "true" : "false") . '", "' . ($is_punctuation ? "true" : "false") . '", "' . ($is_accumulated ? "true" : "false") . '", "' . $this->value_formatted_for_save($min_value) . '", "' . $min_quantity . '")';
        $product_created = $this->db_create($sql);
        if ($product_created) {
            $slug = $this->slugify($product_created . '-' . $description);
            $sql = 'UPDATE `products` SET `slug`="' . $slug . '" WHERE `id`=' . $product_created;
            $this->db_update($sql);

            return $this->read_by_slug($slug);
        } else {
            return false;
        }
    }

    private function value_formatted_for_save(string $value)
    {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return $value;
    }

    public function read(
        int $user_id,
        string $status = null,
        string $card = null,
        bool $just_your_goal = false,
        string $sorting = null,
        bool $desc = false
    ) {
        if ($just_your_goal) {
            $user = $this->users->read_by_id($user_id);
            $products = [];
            $goals = $this->goals->read_by_id($user->goalId);
            foreach ($goals['products'] as $product) {
                $products[] = $this->read_by_id($product['productId']);
            }

            foreach ($goals['modules'] as $goal) {
                $modules_products = $this->modules->read_by_id($goal['moduleId'])['products'];

                foreach ($modules_products as $product) {
                    $products[] = $this->read_by_id($product['productId']);
                }
            }
            return $products;
        } else {
            $query_parms = ($status ? ' WHERE `status` = "' . $status . '"' : '');
            $query_parms .= ($card ? ($status ? ' AND `card` = "' . ($card == 'primary' ? 'Cartela Primária' : 'Cartela Secundária') . '"' : ' WHERE `card` = "' . ($card == 'primary' ? 'Cartela Primária' : 'Cartela Secundária') . '"') : '');

            if ($sorting) {
                $query_parms .= ' ORDER BY `' . $sorting . '` ' . ($desc ? 'DESC' : 'ASC');
            }

            $sql = 'SELECT `id`, `description`, `card`, `status`, `is_punctuation`, `is_accumulated`, `slug` FROM `products` ' . $query_parms;
            $get_products = $this->db_read($sql);
            if ($get_products) {
                $response = [];
                while ($product = $this->db_object($get_products)) {
                    $response[] = [
                        'id' => (int) $product->id,
                        'description' => mb_convert_case($product->description, MB_CASE_TITLE, 'UTF-8'),
                        'card' => $product->card,
                        'status' => $product->status,
                        'isPunctuation' => $product->is_punctuation == "true" ? true : false,
                        'isAccumulated' => $product->is_accumulated == "true" ? true : false,
                        'slug' => $product->slug
                    ];
                }
                return $response;
            } else {
                return [];
            }
        }
    }

    public function read_by_slug(string $slug)
    {
        $sql = 'SELECT `id`, `description`, `card`, `status`, `points`, `slug`, `is_quantity` AS `isQuantity`, `is_punctuation` AS `isPunctuation`, `is_accumulated` AS `isAccumulated`, `min_value` AS `minValue`, `min_quantity` AS `minQuantity` FROM `products` WHERE `slug` = "' . $slug . '"';
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            $product->points = number_format((float) $product->points, 2, ',', '.');
            $product->isQuantity = ($product->isQuantity == "true" ? true : false);
            $product->isPunctuation = ($product->isPunctuation == "true" ? true : false);
            $product->isAccumulated = ($product->isAccumulated == "true" ? true : false);
            $product->minValue = $product->isQuantity == "true" ? 0.00 : number_format((float) $product->minValue, 2, ',', '.');
            $product->minQuantity = $product->isQuantity == "true" ? (int) $product->minQuantity : 0;
            return $product;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `description`, `card`, `status`, `slug`, `is_quantity` AS `isQuantity`, `is_punctuation` AS `isPunctuation`, `is_accumulated` AS `isAccumulated`, `min_value` AS `minValue`, `min_quantity` AS `minQuantity` FROM `products` WHERE `id` = ' . $id;
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            $product->isQuantity = ($product->isQuantity == "true" ? true : false);
            $product->isPunctuation = ($product->isPunctuation == "true" ? true : false);
            $product->isAccumulated = ($product->isAccumulated == "true" ? true : false);
            $product->minValue = $product->isQuantity == "true" ? 0.00 : number_format((float) $product->minValue, 2, ',', '.');
            $product->minQuantity = $product->isQuantity == "true" ? (int) $product->minQuantity : 0;
            return $product;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $description,
        string $card,
        string $status,
        string $points,
        bool $is_quantity,
        bool $is_punctuation,
        bool $is_accumulated,
        int $min_quantity,
        string $min_value
    ) {
        $old_product = $this->read_by_id($id);
        $sql = '
        UPDATE `products` SET
            `description`="' . $description . '",
            `card`="' . $card . '",
            `status`="' . $status . '",
            `points`="' . $this->value_formatted_for_save($points) . '",
            `slug`="' . $this->slugify($id . '-' . $description) . '",
            `is_quantity`="' . ($is_quantity ? "true" : "false") . '",
            `is_punctuation`="' . ($is_punctuation ? "true" : "false") . '",
            `is_accumulated`="' . ($is_accumulated ? "true" : "false") . '",
            `min_value`="' . $this->value_formatted_for_save($min_value) . '",
            `min_quantity`="' . $min_quantity . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            return [
                'old' => $old_product,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug)
    {
        $old_product = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `products` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_product;
        } else {
            return false;
        }
    }
}
