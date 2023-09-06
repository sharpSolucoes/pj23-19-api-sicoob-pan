<?php
class Products extends API_configuration {
    public function create(
        string $description,
        string $status
    ) {
        $sql = 'INSERT INTO `products`(`description`, `status`) VALUES ("' . $description . '", "' . $status . '")';
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

    public function read($status = null) {
        $query_parms = ($status ? ' WHERE `status` = "' . $status .'"' : '');
        $sql = 'SELECT `id`, `description`, `status`, `slug` FROM `products` ' . $query_parms . ' ORDER BY `description`';
        $products = $this->db_read($sql);
        if ($products) {
            $response = [];
            while ($product = $this->db_object($products)) {
                $response[] = [
                    'id' => (int) $product->id,
                    'description' => mb_convert_case($product->description, MB_CASE_TITLE, 'UTF-8'),
                    'status' => $product->status,
                    'slug' => $product->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT `id`, `description`, `status`, `slug` FROM `products` WHERE `slug` = "' . $slug . '"';
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            return $product;
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `products` WHERE `id`=' . $id;
        $product = $this->db_read($sql);
        if ($product) {
            $product = $this->db_object($product);
            $product->id = (int) $product->id;
            $product->status = ($product->status == "true" ? true : false);
            return $product;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $description,
        string $status
    ) {
        $old_product = $this->read_by_id($id);
        $sql = '
        UPDATE `products` SET
            `description`="' . $description . '",
            `status`="' . $status . '",
            `slug`="' . $this->slugify($id . '-' . $description) . '"
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

    public function delete(string $slug) {
        $old_product = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `products` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_product;
        } else {
            return false;
        }
    }
}