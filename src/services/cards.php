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
            SELECT product_id, goal
            FROM goals_products
            WHERE goal_id IN (
                SELECT U.goal_id
                FROM users U
                WHERE U.id = ' . (int) $user->id . '
            )
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
                SELECT product_id, goal
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
}
