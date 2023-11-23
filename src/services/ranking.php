<?php
require_once "users.php";
class Ranking extends API_configuration
{
    private $users;
    public function __construct()
    {
        parent::__construct();
        $this->users = new Users();
    }

    public function read(
        int $user_id,
        string $sorting = null,
        bool $is_desc = null,
        int $limit = null,
        string $initial_date = null,
        string $final_date = null
    ) {
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        }

        $user = $this->users->read_by_id($user_id);
        $order = '';
        if ($sorting !== null && $is_desc !== null) {
            $order = 'ORDER BY ' . ($sorting === "points" ? '`total_points`' : 'U.`name`') . ' ' . ($is_desc ? 'DESC' : 'ASC');
        }

        if ($user->position == "Administrador" || $user->position == "Suporte") {
            $sql = '
            SELECT U.`name`, U.`slug`,
                SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
            FROM `users` U
            LEFT JOIN (
                SELECT S.`user_id`, FLOOR(COUNT(*) / P.`min_quantity`) AS `points_for_quantity`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub1 ON U.`id` = sub1.`user_id`
            LEFT JOIN (
                SELECT S.`user_id`, SUM(FLOOR(`value` / P.`min_value`)) AS `points_for_value`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub2 ON U.`id` = sub2.`user_id`
            LEFT JOIN (
                SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
            ) AS idea1 ON U.`id` = idea1.`user_id` 
            LEFT JOIN (
                SELECT
                    `user_id`,
                    SUM(`punctuation`) AS `points`
                FROM `extra_score` ES
                INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
                WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY `user_id`
            ) AS extra_score ON U.`id` = extra_score.`user_id`
            GROUP BY U.`id`
            ' . $order . '
            ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
            ';
        } else if ($user->position == "Gestor") {
            $sql = '
            SELECT U.`name`, U.`slug`,
                SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
            FROM `users` U
            LEFT JOIN (
                SELECT S.`user_id`, FLOOR(COUNT(*) / P.`min_quantity`) AS `points_for_quantity`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`

            ) AS sub1 ON U.`id` = sub1.`user_id`
            LEFT JOIN (
                SELECT S.`user_id`, SUM(FLOOR(`value` / P.`min_value`)) AS `points_for_value`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub2 ON U.`id` = sub2.`user_id`
            LEFT JOIN (
                SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
            ) AS idea1 ON U.`id` = idea1.`user_id` 
            LEFT JOIN (
                SELECT
                    `user_id`,
                    SUM(`punctuation`) AS `points`
                FROM `extra_score` ES
                INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
                WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY `user_id`
            ) AS extra_score ON U.`id` = extra_score.`user_id`
            WHERE U.`agency_id` = ' . $user->agency_id . '
            GROUP BY U.`id`
            ' . $order . '
            ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
            ';
        } else {
            $sql = 'SELECT `team_id` FROM `teams_users` WHERE `user_id` = ' . $user->id . ' LIMIT 1;';
            $teams = $this->db_read($sql);
            $teams = $this->db_object($teams);
            $sql = '
            SELECT U.`name`, U.`slug`,
                SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
            FROM `users` U
            LEFT JOIN (
                SELECT S.`user_id`, FLOOR(COUNT(*) / P.`min_quantity`) AS `points_for_quantity`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub1 ON U.`id` = sub1.`user_id`
            LEFT JOIN (
                SELECT S.`user_id`, SUM(FLOOR(`value` / P.`min_value`)) AS `points_for_value`
                FROM `sales` S
                INNER JOIN `products` P ON S.`product_id` = P.`id`
                WHERE P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub2 ON U.`id` = sub2.`user_id`
            LEFT JOIN (
                SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
            ) AS idea1 ON U.`id` = idea1.`user_id` 
            LEFT JOIN (
                SELECT
                    `user_id`,
                    SUM(`punctuation`) AS `points`
                FROM `extra_score` ES
                INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
                WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY `user_id`
            ) AS extra_score ON U.`id` = extra_score.`user_id`
            INNER JOIN `teams_users` TU ON U.`id` = TU.`user_id`
            INNER JOIN `teams` T ON TU.`team_id` = T.`id`
            WHERE T.`id` = ' . (int) $teams->team_id . '
            GROUP BY U.`id`
            ' . $order . '
            ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
            ';
        }


        $get_ranking = $this->db_read($sql);
        if ($get_ranking) {
            $response = [];
            while ($ranking = $this->db_object($get_ranking)) {
                $response[] = [
                    'name' => mb_convert_case($ranking->name, MB_CASE_TITLE, 'UTF-8'),
                    'points' => (int) $ranking->total_points,
                    'slug' => $ranking->slug
                ];
            }

            if ($limit !== null) {
                array_push($response, [
                    'name' => 'Total',
                    'points' => array_sum(array_column($response, 'points')),
                ]);
            }

            return $response;
        } else {
            return [];
        }
    }

    public function is_accountable(int $user)
    {
        $sql = 'SELECT `id` FROM `teams` WHERE `accountable` = ' . $user . ' LIMIT 1;';
        $team = $this->db_read($sql);
        if ($this->db_num_rows($team) > 0) {
            return [
                'isAccountable' => true
            ];
        } else {
            return [
                'isAccountable' => false
            ];
        }
    }
}
