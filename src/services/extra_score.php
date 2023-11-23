<?php
class Extra_score extends API_configuration
{
    public function create(
        string $description,
        string $punctuation,
        array $users
    ) {
        $sql = 'INSERT INTO `extra_score`(`description`, `punctuation`, `created_at`) VALUES ("' . $description . '",' . (float) $this->real_to_float($punctuation) . ',"' . date('Y-m-d H:i:s') . '")';
        $create_extra_score = $this->db_create($sql);
        if ($create_extra_score) {
            if ($this->create_users($create_extra_score, $users)) {
                $sql = 'UPDATE `extra_score` SET `slug` = "' . $this->slugify($create_extra_score . '-' . $description) . '" WHERE `id` = ' . $create_extra_score;
                $this->db_update($sql);

                return [
                    'id' => $create_extra_score,
                    'description' => $description,
                    'punctuation' => $punctuation,
                    'users' => $users,
                    'slug' => $this->slugify($create_extra_score . '-' . $description)
                ];
            } else {
                $sql = 'DELETE FROM `extra_scores` WHERE `id` = ' . $create_extra_score;
                $this->db_delete($sql);
                return false;
            }
        } else {
            return false;
        }
    }

    private function create_users(
        int $extra_score_id,
        array $users
    ) {
        $sql = 'DELETE FROM `extra_score_users` WHERE `extra_score_id` = ' . $extra_score_id;
        if ($this->db_delete($sql)) {
            $sql = 'INSERT INTO `extra_score_users`(`extra_score_id`, `user_id`, `created_at`) VALUES';
            foreach ($users as $product) {
                $sql .= '(' . $extra_score_id . ',' . $product->userId . ', "' . date("Y-m-d H:i:s") . '"),';
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
        bool $desc = false
    ) {
        $query_params = '';
        if ($sorting !== null && $sorting !== 'numberUsers') {
            $query_params = 'ORDER BY ' . ($sorting === "description" ? '`description`' : '`punctuation`') . ' ' . ($desc ? 'DESC' : 'ASC');
        }

        $sql = 'SELECT `id`, `description`, `punctuation`, `slug` FROM `extra_score` ' . $query_params;
        $get_extra_score = $this->db_read($sql);
        if ($this->db_num_rows($get_extra_score) > 0) {
            $response = [];
            while ($extra_score = $this->db_object($get_extra_score)) {
                $users = $this->read_name_users((int) $extra_score->id);

                $response[] = [
                    'id' => (int) $extra_score->id,
                    'description' => $extra_score->description,
                    'punctuation' => number_format($extra_score->punctuation, 2, ',', '.'),
                    'numberUsers' => count($users),
                    'users' => $users,
                    'slug' => $extra_score->slug
                ];
            }

            return $response;
        } else {
            return [];
        }
    }

    private function read_name_users(
        $extra_score_id
    ) {
        $sql = 'SELECT U.`name` FROM `extra_score_users` ES, `users` U WHERE ES.`user_id` = U.`id` AND `extra_score_id` = ' . $extra_score_id;
        $get_users = $this->db_read($sql);
        if ($this->db_num_rows($get_users) > 0) {
            $response = [];
            while ($user = $this->db_object($get_users)) {
                $response[] = [
                    'name' => $user->name
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
        $sql = 'SELECT `id`, `description`, `punctuation`, `slug` FROM `extra_score` WHERE `slug` = "' . $slug . '"';
        $get_extra_score = $this->db_read($sql);
        if ($this->db_num_rows($get_extra_score) == 1) {
            $extra_score = $this->db_object($get_extra_score);

            return [
                'id' => (int) $extra_score->id,
                'description' => $extra_score->description,
                'punctuation' => (float) $extra_score->punctuation,
                'users' => $this->read_users((int) $extra_score->id),
                'slug' => $extra_score->slug
            ];
        } else {
            return false;
        }
    }

    public function read_by_id(
        int $id
    ) {
        $sql = 'SELECT `id`, `description`, `punctuation`, `slug` FROM `extra_score` WHERE `id` = ' . $id;
        $get_extra_score = $this->db_read($sql);
        if ($this->db_num_rows($get_extra_score) == 1) {
            $extra_score = $this->db_object($get_extra_score);

            return [
                'id' => (int) $extra_score->id,
                'description' => $extra_score->description,
                'punctuation' => (float) $extra_score->punctuation,
                'users' => $this->read_users((int) $extra_score->id),
                'slug' => $extra_score->slug
            ];
        } else {
            return false;
        }
    }

    private function read_users(
        int $extra_score_id
    ) {
        $sql = 'SELECT `user_id` FROM `extra_score_users` WHERE `extra_score_id` = ' . $extra_score_id;
        $get_users = $this->db_read($sql);
        if ($this->db_num_rows($get_users) > 0) {
            $response = [];
            while ($user = $this->db_object($get_users)) {
                $response[] = [
                    'userId' => $user->user_id
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
        string $punctuation,
        array $users
    ) {
        $old_extra_score = $this->read_by_id($id);
        if ($old_extra_score) {
            $sql = 'UPDATE `extra_score` SET `description` = "' . $description . '", `punctuation` = "' . (float) $this->real_to_float($punctuation) . '", `slug` = "' . $this->slugify($id . '-' . $description) . '" WHERE `id` = ' . $id;
            if ($this->db_update($sql)) {
                if ($this->create_users($id, $users)) {
                    return [
                        'id' => $id,
                        'description' => $description,
                        'punctuation' => $punctuation,
                        'users' => $users,
                        'slug' => $this->slugify($id . '-' . $description)
                    ];
                } else {
                    return false;
                }
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
        $extra_score = $this->read_by_slug($slug);

        if ($extra_score) {
            $sql = 'DELETE FROM `extra_score` WHERE `id` = ' . $extra_score['id'];
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
