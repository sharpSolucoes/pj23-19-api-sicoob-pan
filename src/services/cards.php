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
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));

        $sql = '
            SELECT 
                G.`global_goal`,
                COALESCE(sub1.`points_for_quantity`, 0) + COALESCE(sub2.`points_for_value`, 0) + COALESCE(idea1.`total_ideas`, 0) + COALESCE(extra_score.`points`, 0) AS `total_points`
            FROM `users` U
            LEFT JOIN (
                SELECT S.`user_id`, SUM((P.`min_quantity`) * P.`points`) AS `points_for_quantity`
                FROM `sales` S
                INNER JOIN `products` P ON 
                (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
                OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
                WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                GROUP BY S.`user_id`
            ) AS sub1 ON U.`id` = sub1.`user_id`
            LEFT JOIN (
                SELECT 
                    S.`user_id`, 
                    SUM(
                        CASE 
                            WHEN `value` >= P.`min_value` THEN 
                                P.`points`
                            WHEN `value` < P.`min_value` THEN 
                                CASE
                                    WHEN P.`is_accumulated` = "true" THEN 
                                        (`value` / P.`min_value`) * P.`points`
                                    ELSE 
                                        0
                                END
                            ELSE 
                                0
                        END
                    ) AS `points_for_value`
                FROM 
                    `sales` S
                INNER JOIN `products` P ON 
                    (S.`product_id` = P.`id` AND S.`change_punctuation` = "false" AND P.`is_quantity` = "false")
                    OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true" AND P.`is_quantity` = "false")
                WHERE S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
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
                'name' => mb_convert_case($user->name, MB_CASE_TITLE, 'UTF-8'),
                'globalGoal' => (int) $global_goal->global_goal,
                'totalPoints' => (float) $global_goal->total_points
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
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));

        $user = $this->users->read_user_by_slug($slug);
        $order = '';
        if ($sorting == "description") {
            $order = ' ORDER BY GP.description' . ($desc ? ' DESC' : '');
        } else if ($sorting == "goal") {
            $order = ' ORDER BY GP.goal' . ($desc ? ' DESC' : '');
        } else if ($sorting == "points") {
            $order = ' ORDER BY points' . ($desc ? ' DESC' : '');
        }

        $sql = '
            SELECT 
                GP.description,
                IFNULL(GP.goal, 0) AS goal,
                SUM(CASE 
                    WHEN P.`is_quantity` = "true" THEN 
                        COALESCE(
                            (
                                SELECT (COUNT(*) / P.`min_quantity`) * P.`points`
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '" AND `change_punctuation` = "false" AND `status` = "true" AND `user_id` = ' . (int) $user->id . '
                            ), 
                            0
                        )
                    WHEN P.`is_quantity` = "false" THEN 
                        COALESCE(
                            (
                                SELECT
                                    SUM(
                                        CASE 
                                            WHEN S.`value` >= P.`min_value` THEN 
                                                P.`points`
                                            ELSE 
                                                CASE
                                                    WHEN P.`is_accumulated` = "true" THEN 
                                                        (S.`value` / P.`min_value`) * P.`points`
                                                    ELSE 
                                                        0
                                                END
                                        END
                                    )
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '" AND `change_punctuation` = "false" AND `status` = "true" AND `user_id` = ' . (int) $user->id . '
                                LIMIT 1
                            ), 
                            0
                        )
                    ELSE 0
                END) AS `points`
            FROM `products` P
            LEFT JOIN (
                SELECT MP.product_id AS product_id, GMLJ.goal AS goal, GMLJ.module_id, M.description AS description
                FROM users U
                JOIN goals G ON U.goal_id = G.id
                JOIN goals_modules GMLJ ON G.id = GMLJ.goal_id
                JOIN modules_products MP ON GMLJ.module_id = MP.module_id
                JOIN modules M ON M.id = MP.module_id
                WHERE U.id = ' . $user->id . '
            ) GP ON P.`id` = GP.product_id
            LEFT JOIN users U ON U.id = ' . $user->id . '
            LEFT JOIN goals G ON U.goal_id = G.id
            WHERE P.`card` = "Cartela Primária" AND GP.module_id IS NOT NULL
            GROUP BY GP.module_id
            ' . $order . '    
        ';
        $products = $this->db_read($sql);
        if ($products) {
            $response = [];
            while ($product = $this->db_object($products)) {
                $response[] = [
                    'description' => mb_convert_case($product->description, MB_CASE_TITLE, 'UTF-8'),
                    'goal' => (int) $product->goal,
                    'points' => (float) number_format($product->points, 2),
                    'pointsDifference' => (float) $product->goal - (float) $product->points
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
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));

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
                        COALESCE(
                            (
                                SELECT (COUNT(*) / P.`min_quantity`) * P.`points`
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
                                AND `user_id` = ' . (int) $user->id . ' AND S.`status` = "true"
                            ), 
                            0
                        )
                    WHEN P.`is_quantity` = "false" THEN
                        COALESCE(
                            (
                                SELECT
                                    SUM(
                                        CASE 
                                            WHEN S.`value` >= P.`min_value` THEN 
                                                P.`points`
                                            ELSE 
                                                CASE
                                                    WHEN P.`is_accumulated` = "true" THEN 
                                                        (S.`value` / P.`min_value`) * P.`points`
                                                    ELSE 
                                                        0
                                                END
                                        END
                                    )
                                FROM `sales` S
                                WHERE `product_id` = P.`id` AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '" AND `status` = "true" AND `user_id` = ' . (int) $user->id . '
                                LIMIT 1
                            ), 
                            0
                        )
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
                    'points' => (float) number_format($product->points, 2)
                ];
            }

            return $response;
        } else {
            return [];
        }
    }
}
