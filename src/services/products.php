<?php
class Products extends API_configuration
{
    public function create(
        string $description,
        string $card,
        string $status,
        bool $is_quantity,
        bool $is_punctuation,
        int $min_quantity,
        string $min_value
    ) {
        $sql = 'INSERT INTO `products`(`description`, `card`, `status`, `is_quantity`, `is_punctuation`, `min_value`, `min_quantity`) VALUES ("' . $description . '", "' . $card . '", "' . $status . '", "' . ($is_quantity ? "true" : "false") . '", "' . ($is_punctuation ? "true" : "false") . '", "' . $this->value_formatted_for_save($min_value) . '", "' . $min_quantity . '")';
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

    public function read($status = null, $card = null)
    {
        $query_parms = ($status ? ' WHERE `status` = "' . $status . '"' : '');
        $query_parms .= ($card ? ($status ? ' AND `card` = "' . ($card == 'primary' ? 'Cartela Primária' : 'Cartela Secundária') . '"' : ' WHERE `card` = "' . ($card == 'primary' ? 'Cartela Primária' : 'Cartela Secundária') . '"') : '');
        $sql = 'SELECT `id`, `description`, `card`, `status`, `is_punctuation`, `slug` FROM `products` ' . $query_parms . ' ORDER BY `description`';
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
                    'slug' => $product->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug)
    {
        $sql = 'SELECT `id`, `description`, `card`, `status`, `slug`, `is_quantity` AS `isQuantity`, `is_punctuation` AS `isPunctuation`, `min_value` AS `minValue`, `min_quantity` AS `minQuantity` FROM `products` WHERE `slug` = "' . $slug . '"';
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            $product->isQuantity = ($product->isQuantity == "true" ? true : false);
            $product->isPunctuation = ($product->isPunctuation == "true" ? true : false);
            $product->minValue = $product->isQuantity == "true" ? 0.00 : number_format((float) $product->minValue, 2, ',', '.');
            $product->minQuantity = $product->isQuantity == "true" ? (int) $product->minQuantity : 0;
            return $product;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `description`, `card`, `status`, `slug`, `is_quantity` AS `isQuantity`, `is_punctuation` AS `isPunctuation`, `min_value` AS `minValue`, `min_quantity` AS `minQuantity` FROM `products` WHERE `id` = ' . $id;
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            $product->isQuantity = ($product->isQuantity == "true" ? true : false);
            $product->isPunctuation = ($product->isPunctuation == "true" ? true : false);
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
        bool $is_quantity,
        bool $is_punctuation,
        int $min_quantity,
        string $min_value
    ) {
        $old_product = $this->read_by_id($id);
        $sql = '
        UPDATE `products` SET
            `description`="' . $description . '",
            `card`="' . $card . '",
            `status`="' . $status . '",
            `slug`="' . $this->slugify($id . '-' . $description) . '",
            `is_quantity`="' . ($is_quantity ? "true" : "false") . '",
            `is_punctuation`="' . ($is_punctuation ? "true" : "false") . '",
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
