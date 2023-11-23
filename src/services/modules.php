<?php
class Modules extends API_configuration
{
    public function create(
        string $description,
        string $status,
        array $products
    ) {
        $sql = 'INSERT INTO `modules`(`description`, `status`) VALUES ("' . $description . '","' . $status . '")';
        $create_module = $this->db_create($sql);
        if ($create_module) {
            if ($this->create_products($create_module, $products)) {
                $sql = 'UPDATE `modules` SET `slug` = "' . $this->slugify($create_module . '-' . $description) . '" WHERE `id` = ' . $create_module;
                $this->db_update($sql);

                return [
                    'id' => $create_module,
                    'description' => $description,
                    'status' => $status,
                    'products' => $products,
                    'slug' => $this->slugify($create_module . '-' . $description)
                ];
            } else {
                $sql = 'DELETE FROM `modules` WHERE `id` = ' . $create_module;
                $this->db_delete($sql);
                return false;
            }
        } else {
            return false;
        }
    }

    private function create_products(
        int $module_id,
        array $products
    ) {
        $sql = 'DELETE FROM `modules_products` WHERE `module_id` = ' . $module_id;
        if ($this->db_delete($sql)) {
            $sql = 'INSERT INTO `modules_products`(`module_id`, `product_id`) VALUES';
            foreach ($products as $product) {
                $sql .= '(' . $module_id . ',' . $product->productId . '),';
            }
            $sql = substr($sql, 0, -1);
            if ($this->db_create($sql)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function read(
        string $sorting = null,
        bool $desc = false,
        string $status = null
    ) {
        $query_params = '';

        if ($status !== null) {
            $query_params = 'WHERE `status` = "' . $status . '"';
        }

        if ($sorting !== null) {
            $query_params .= 'ORDER BY ' . ($sorting === "description" ? '`description`' : '`status`') . ' ' . ($desc ? 'DESC' : 'ASC');
        }

        $sql = 'SELECT `id`, `description`, `status`, `slug` FROM `modules` ' . $query_params;
        $get_modules = $this->db_read($sql);
        if ($this->db_num_rows($get_modules) > 0) {
            $response = [];
            while ($module = $this->db_object($get_modules)) {
                $response[] = [
                    'id' => (int) $module->id,
                    'description' => $module->description,
                    'status' => $module->status,
                    'slug' => $module->slug
                ];
            }

            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(
        string $slug
    ) {
        $sql = 'SELECT `id`, `description`, `status`, `slug` FROM `modules` WHERE `slug` = "' . $slug . '"';
        $get_module = $this->db_read($sql);
        if ($this->db_num_rows($get_module) == 1) {
            $module = $this->db_object($get_module);

            return [
                'id' => (int) $module->id,
                'description' => $module->description,
                'status' => $module->status,
                'products' => $this->read_products((int) $module->id),
                'slug' => $module->slug
            ];
        } else {
            return false;
        }
    }

    public function read_by_id(
        int $id
    ) {
        $sql = 'SELECT `id`, `description`, `status`, `slug` FROM `modules` WHERE `id` = "' . $id . '"';
        $get_module = $this->db_read($sql);
        if ($this->db_num_rows($get_module) == 1) {
            $module = $this->db_object($get_module);

            return [
                'id' => (int) $module->id,
                'description' => $module->description,
                'status' => $module->status,
                'products' => $this->read_products((int) $module->id),
                'slug' => $module->slug
            ];
        } else {
            return false;
        }
    }

    private function read_products(
        int $module_id
    ) {
        $sql = 'SELECT `product_id` FROM `modules_products` WHERE `module_id` = ' . $module_id;
        $get_products = $this->db_read($sql);
        if ($this->db_num_rows($get_products) > 0) {
            $response = [];
            while ($product = $this->db_object($get_products)) {
                $response[] = [
                    'productId' => $product->product_id
                ];
            }

            return $response;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $description,
        string $status,
        array $products
    ) {
        $sql = 'UPDATE `modules` SET `description` = "' . $description . '", `status` = "' . $status . '", `slug` = "' . $this->slugify($id . '-' . $description) . '" WHERE `id` = ' . $id;
        if ($this->db_update($sql)) {
            if ($this->create_products($id, $products)) {
                return [
                    'id' => $id,
                    'description' => $description,
                    'status' => $status,
                    'products' => $products,
                    'slug' => $this->slugify($id . '-' . $description)
                ];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function delete(
        string $slug
    ) {
        $module = $this->read_by_slug($slug);

        if ($module) {
            $sql = 'DELETE FROM `modules` WHERE `id` = ' . $module['id'];
            if ($this->db_delete($sql)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
