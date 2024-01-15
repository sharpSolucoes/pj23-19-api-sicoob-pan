<?php
require_once "agencies.php";
class Users extends API_configuration
{
    private $agencies;

    public function __construct()
    {
        parent::__construct();
        $this->agencies = new Agencies();
    }

    public function create(
        int $agency_id,
        int $goal_id,
        string $name,
        string $email,
        string $position,
        string $password_confirmation,
        string $status,
        array $permissions
    ) {
        $values = '
        ' . $agency_id . ',
        ' . $goal_id . ',
        "' . $name . '",
        "' . $email . '",
        "' . $position . '",
        "' . password_hash($password_confirmation, PASSWORD_BCRYPT, ['cost' => 12]) . '",
        "' . $status . '"
        ';
        $sql = 'INSERT INTO `users` (`agency_id`, `goal_id`, `name`, `email`, `position`, `password`, `status`) VALUES (' . $values . ')';
        $create_user = $this->db_create($sql);
        if ($create_user) {
            $user_id = $create_user;

            // CREATE USER SLUG
            $slug = $this->slugify($user_id . '-' . $name);
            $sql = 'UPDATE `users` SET `slug` = "' . $slug . '" WHERE `id` = ' . $user_id;
            $this->db_update($sql);

            // UPDATE USER PERMISSIONS STATUS
            for ($i = 0; $i < count($permissions); $i++) {
                $permission_key = array_keys($permissions);
                $permission_data = (array) $permissions[$permission_key[$i]];

                for ($j = 0; $j < count($permission_data); $j++) {
                    $permission_data_key = array_keys($permission_data);
                    $permission_data_value = $permission_data[$permission_data_key[$j]] ? "true" : "false";

                    $permission = $permission_key[$i] . '.' . $permission_data_key[$j];
                    $sql = 'UPDATE `users_permissions` SET `status` = "' . $permission_data_value . '" WHERE `user_id` = ' . $user_id . ' AND `permission` = "' . $permission . '"';
                    $this->db_update($sql);
                }
            }
            return [
                'id' => $user_id,
                'name' => $name,
                'permissions' => $permissions,
            ];
        } else {
            return $create_user;
        }
    }

    public function read(
        $user_id = null,
        bool $no_team = false
    ) {
        $user_position = '';
        if ($user_id) {
            $user_position = $this->read_by_id($user_id);
        } else {
            $user_position = $this->read_by_id($this->user_id);
        }

        if ($no_team) {
            $sql = 'SELECT `id`, `name`, `position`, `agency_id` AS `agencyId`, `slug`, `status` FROM `users` WHERE `id` NOT IN (SELECT DISTINCT `user_id` FROM `teams_users`)';
        } else {
            $sql = 'SELECT `id`, `name`, `position`, `agency_id` AS `agencyId`, `slug`, `status` FROM `users` ORDER BY `name`';
        }
        $get_users_data = $this->db_read($sql);
        if ($this->db_num_rows($get_users_data) > 0) {
            $users_data = [];
            while ($user_data = $this->db_object($get_users_data)) {
                $user_data->id = (int) $user_data->id;
                $user_data->agency = $this->agencies->read_by_id((int) $user_data->agencyId);
                if ($user_data->position == 'Suporte' && $user_position->position != 'Suporte') {
                    continue;
                } else {
                    $user_data->status = $user_data->status == 'true' ? true : false;
                    array_push($users_data, [
                        'id' => $user_data->id,
                        'name' => mb_convert_case($user_data->name, MB_CASE_TITLE, 'UTF-8'),
                        'position' => $user_data->position,
                        'agency' => $user_data->agency->number . ' - ' . $user_data->agency->name,
                        'slug' => $user_data->slug,
                        'status' => $user_data->status
                    ]);
                }
            }
            return $users_data;
        } else {
            return [];
        }
    }

    public function read_user_by_slug(string $slug)
    {
        $sql = 'SELECT `id`, `goal_id` AS "goalId", `agency_id` AS "agencyId", `name`, `email`, `position`, `status` FROM `users` WHERE `slug` = "' . $slug . '"';
        $get_user_data = $this->db_read($sql);
        if ($this->db_num_rows($get_user_data) > 0) {
            $user_data = $this->db_object($get_user_data);
            $user_data->id = (int) $user_data->id;
            $user_data->agencyId = (int) $user_data->agencyId;
            $user_data->goalId = (int) $user_data->goalId;

            $sql = 'SELECT `permission`, `status` FROM `users_permissions` WHERE `user_id` = ' . $user_data->id;
            $user_permissions = $this->db_read($sql);
            if ($this->db_num_rows($user_permissions) > 0) {
                $permissions = [];
                while ($user_permission = $this->db_object($user_permissions)) {
                    $permission = explode('.', $user_permission->permission);
                    $permissions[$permission[0]][$permission[1]] = $user_permission->status == 'true' ? true : false;
                }
                $user_data->permissions = $permissions;
            }

            return $user_data;
        } else {
            return false;
        }
    }

    public function read_by_id(int $id)
    {
        $sql = 'SELECT `id`, `name`, `agency_id`, `goal_id`, `email`, `position`, `slug` FROM `users` WHERE `id` = ' . $id;
        $get_user_data = $this->db_read($sql);
        if ($this->db_num_rows($get_user_data) > 0) {
            $user_data = $this->db_object($get_user_data);
            $user_data->id = (int) $user_data->id;
            $user_data->agencyId = (int) $user_data->agency_id;
            $user_data->goalId = (int) $user_data->goal_id;

            $sql = 'SELECT `permission`, `status` FROM `users_permissions` WHERE `user_id` = ' . $user_data->id;
            $user_permissions = $this->db_read($sql);
            if ($this->db_num_rows($user_permissions) > 0) {
                $permissions = [];
                while ($user_permission = $this->db_object($user_permissions)) {
                    $permission = explode('.', $user_permission->permission);
                    $permissions[$permission[0]][$permission[1]] = $user_permission->status == 'true' ? true : false;
                }
                $user_data->permissions = $permissions;
            }

            return $user_data;
        } else {
            return false;
        }
    }

    protected function read_all_data_by_id(int $id)
    {
        $sql = 'SELECT `id`, `name`, `avatar`, `email`, `position`, `slug`, `status` FROM `users` WHERE `id` = ' . $id;
        $get_user_data = $this->db_read($sql);
        if ($this->db_num_rows($get_user_data) > 0) {
            $user_data = $this->db_object($get_user_data);
            $user_data->id = (int) $user_data->id;
            $user_data->status = $user_data->status == 'true' ? true : false;

            $sql = 'SELECT `permission`, `status` FROM `users_permissions` WHERE `user_id` = ' . $user_data->id;
            $user_permissions = $this->db_read($sql);
            if ($this->db_num_rows($user_permissions) > 0) {
                $permissions = [];
                while ($user_permission = $this->db_object($user_permissions)) {
                    $permission = explode('.', $user_permission->permission);
                    $permissions[$permission[0]][$permission[1]] = $user_permission->status == 'true' ? true : false;
                }
                $user_data->permissions = $permissions;
            }

            return $user_data;
        } else {
            return [];
        }
    }

    public function read_logs(
        string $action = null,
        string $final_date = null,
        string $initial_date = null,
        string $user_id = null
    ) {
        $sql = 'SELECT `id`, `user_id`, `date`, `action`, `description` FROM `users_logs`';
        if ($action || $final_date || $initial_date || $user_id) {
            $sql .= ' WHERE';
            if ($action) {
                $sql .= ' `action` = "' . $action . '"';
            }
            if ($final_date) {
                $sql .= ' `date` <= "' . $final_date . '"';
            }
            if ($initial_date) {
                $sql .= ' `date` >= "' . $initial_date . '"';
            }
            if ($user_id) {
                $sql .= ' `user_id` = ' . $user_id;
            }
        }
        $sql .= ' ORDER BY `date` DESC';
        $get_logs_data = $this->db_read($sql);
        if ($this->db_num_rows($get_logs_data) > 0) {
            $logs_data = [];
            while ($log_data = $this->db_object($get_logs_data)) {
                $log_data->id = (int) $log_data->id;
                $log_data->user = $this->read_by_id($log_data->user_id);
                $log_data->description = json_decode($log_data->description);
                unset($log_data->user->permissions);
                unset($log_data->user_id);
                array_push($logs_data, $log_data);
            }
            return $logs_data;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        string $name,
        int $agency_id,
        int $goal_id,
        string $email,
        string $position,
        bool $changePassword,
        string $password_confirmation,
        string $status,
        array $permissions
    ) {
        $old_user_data = $this->read_all_data_by_id($id);

        $values = '
        `name` = "' . $name . '",
        `agency_id` = ' . $agency_id . ',
        `goal_id` = ' . $goal_id . ',
        `email` = "' . $email . '",
        `position` = "' . $position . '",
        ' . ($changePassword ? '`password` = "' . password_hash($password_confirmation, PASSWORD_BCRYPT, ['cost' => 12]) . '",' : '') . '
        `status` = "' . $status . '",
        `slug` = "' . $this->slugify($id . '-' . $name) . '"';
        $sql = 'UPDATE `users` SET ' . $values . ' WHERE `id` = ' . $id;
        $update_user = $this->db_update($sql);
        if ($update_user) {
            // UPDATE USER PERMISSIONS STATUS
            for ($i = 0; $i < count($permissions); $i++) {
                $permission_key = array_keys($permissions);
                $permission_data = (array) $permissions[$permission_key[$i]];

                for ($j = 0; $j < count($permission_data); $j++) {
                    $permission_data_key = array_keys($permission_data);
                    $permission_data_value = $permission_data[$permission_data_key[$j]] ? "true" : "false";

                    $permission = $permission_key[$i] . '.' . $permission_data_key[$j];
                    $sql = 'UPDATE `users_permissions` SET `status` = "' . $permission_data_value . '" WHERE `user_id` = ' . $id . ' AND `permission` = "' . $permission . '"';
                    $this->db_update($sql);
                }
            }

            if ($status == "false") {
                $sql = 'DELETE FROM `api_sessions` WHERE `user_id` = ' . $id;
                $this->db_update($sql);
            }

            return [
                'old' => $old_user_data,
                'new' => $this->read_all_data_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug)
    {
        $sql = 'SELECT `id`, `name`, `avatar`, `email`, `password`, `position`, `status` FROM `users` WHERE `slug` = "' . $slug . '"';
        $get_user_data = $this->db_read($sql);
        $user_data = $this->db_object($get_user_data);

        $sql = 'SELECT `permission`, `status` FROM `users_permissions` WHERE `user_id` = ' . $user_data->id;
        $get_user_permissions = $this->db_read($sql);
        $user_permissions = $this->db_assoc($get_user_permissions);

        $sql = 'DELETE FROM `users` WHERE `slug` = "' . $slug . '"';
        if ($this->db_delete($sql)) {
            return [
                'name' => $user_data->name,
                'avatar' => $user_data->avatar,
                'email' => $user_data->email,
                'password' => $user_data->password,
                'position' => $user_data->position,
                'status' => $user_data->status == 'true' ? true : false,
                'permissions' => $user_permissions
            ];
        } else {
            return false;
        }
    }
}
