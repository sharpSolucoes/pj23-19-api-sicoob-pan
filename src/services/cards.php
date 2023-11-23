<?php
require_once "users.php";
class Cards extends API_configuration
{
    private $users;
    public function __construct()
    {
        parent::__construct();
        $this->users = new Users();
    }

    public function read(
        string $slug,
        string $initial_date = null,
        string $final_date = null
    ) {
        $user = $this->users->read_user_by_slug($slug);
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        }

        $sql = '
        SELECT 
            G.`global_goal`,
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
        INNER JOIN `goals` G ON U.`goal_id` = G.`id`
        WHERE U.`id` = ' . $user->id . '
        GROUP BY U.`id`
        ';
        $get_global_goal = $this->db_read($sql);
        if ($get_global_goal) {
            $global_goal = $this->db_object($get_global_goal);
            return [
                'globalGoal' => (int) $global_goal->global_goal,
                'totalPoints' => (int) $global_goal->total_points
            ];
        } else {
            return false;
        }
    }

    public function read_primary(
        string $slug,
        string $sorting = "description",
        bool $desc = false,
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

        $user = $this->users->read_user_by_slug($slug);
        $order = '';
        if ($sorting == "description") {
            $order = ' ORDER BY P.description' . ($desc ? ' DESC' : '');
        } else if ($sorting == "goal") {
            $order = ' ORDER BY GP.goal' . ($desc ? ' DESC' : '');
        } else if ($sorting == "points") {
            $order = ' ORDER BY points' . ($desc ? ' DESC' : '');
        }

        $sql = '
        SELECT 
            P.`id`,
            P.`description`,
            P.`is_quantity`,
            IFNULL(GP.goal, 0) AS goal,
            CASE 
                WHEN P.`is_quantity` = "true" THEN 
                    COALESCE((SELECT COUNT(*) / P.`min_quantity`
                            FROM `sales` S
                            WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                            AND `user_id` = ' . (int) $user->id . ' AND S.`status` = "true"), 0)
                WHEN P.`is_quantity` = "false" THEN 
                    COALESCE((SELECT SUM(`value`) / P.`min_value`
                            FROM `sales` S
                            WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                            AND `user_id` = ' . (int) $user->id . ' AND S.`status` = "true"), 0)
                ELSE 0
            END AS `points`
        FROM `products` P
        LEFT JOIN (
            SELECT MP.product_id AS product_id, GMLJ.goal AS goal
            FROM users U
            JOIN goals G ON U.goal_id = G.id
            JOIN goals_modules GMLJ ON G.id = GMLJ.goal_id
            JOIN modules_products MP ON GMLJ.module_id = MP.module_id
            WHERE U.id = ' . (int) $user->id . '
        ) GP ON P.`id` = GP.product_id
        LEFT JOIN users U ON U.id = ' . (int) $user->id . '
        LEFT JOIN goals G ON U.goal_id = G.id
        WHERE P.`card` = "Cartela Primária"
         ' . $order . ';';
        $products = $this->db_read($sql);
        if ($products) {
            $response = [];
            while ($product = $this->db_object($products)) {
                $response[] = [
                    'description' => mb_convert_case($product->description, MB_CASE_TITLE, 'UTF-8'),
                    'goal' => (int) $product->goal,
                    'points' => (int) $product->points,
                    'pointsDifference' => (int) $product->goal - (int) $product->points
                ];
            }

            return $response;
        } else {
            return [];
        }
    }

    public function read_secondary(
        string $slug,
        string $sorting = "description",
        bool $desc = false,
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

        $user = $this->users->read_user_by_slug($slug);
        $order = '';
        if ($sorting == "description") {
            $order = ' ORDER BY P.description' . ($desc ? ' DESC' : '');
        } else if ($sorting == "goal") {
            $order = ' ORDER BY GP.goal' . ($desc ? ' DESC' : '');
        } else if ($sorting == "points") {
            $order = ' ORDER BY points' . ($desc ? ' DESC' : '');
        }

        $sql = '
            SELECT 
                P.`id`,
                P.`description`,
                P.`is_quantity`,
                CASE 
                    WHEN P.`is_quantity` = "true" THEN 
                        COALESCE((SELECT COUNT(*) / P.`min_quantity`
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                                AND `user_id` = ' . (int) $user->id . ' AND S.`status` = "true"), 0)
                    WHEN P.`is_quantity` = "false" THEN 
                        COALESCE((SELECT SUM(`value`) / P.`min_value`
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                                AND `user_id` = ' . (int) $user->id . ' AND S.`status` = "true"), 0)
                    ELSE 0
                END AS `points`
            FROM `products` P
            LEFT JOIN (
                SELECT product_id
                FROM goals_products
                WHERE goal_id IN (
                    SELECT U.goal_id
                    FROM users U
                    WHERE U.id = ' . (int) $user->id . '
                )
            ) GP ON P.`id` = GP.product_id
            LEFT JOIN users U ON U.id = ' . (int) $user->id . '
            LEFT JOIN goals G ON U.goal_id = G.id
            WHERE P.`card` = "Cartela Secundária"
            ' . $order . ';
        ';

        $products = $this->db_read($sql);
        if ($products) {
            $response = [];
            while ($product = $this->db_object($products)) {
                $response[] = [
                    'description' => mb_convert_case($product->description, MB_CASE_TITLE, 'UTF-8'),
                    'points' => (int) $product->points
                ];
            }

            return $response;
        } else {
            return [];
        }
    }
}
