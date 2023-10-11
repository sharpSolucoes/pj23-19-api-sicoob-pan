<?php
class Cards extends API_configuration
{
    public function read_primary(
        int $user_id,
        string $sorting = "description",
        bool $desc = false
    ) {
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
                            WHERE `product_id` = P.`id`
                            AND `user_id` = ' . $user_id . ' AND S.`status` = "true"), 0)
                WHEN P.`is_quantity` = "false" THEN 
                    COALESCE((SELECT SUM(`value`) / P.`min_value`
                            FROM `sales` S
                            WHERE `product_id` = P.`id`
                            AND `user_id` = ' . $user_id . ' AND S.`status` = "true"), 0)
                ELSE 0
            END AS `points`
        FROM `products` P
        LEFT JOIN (
            SELECT product_id, goal
            FROM goals_products
            WHERE goal_id IN (
                SELECT U.goal_id
                FROM users U
                WHERE U.id = ' . $user_id . '
            )
        ) GP ON P.`id` = GP.product_id
        LEFT JOIN users U ON U.id = ' . $user_id . '
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
        int $user_id,
        string $sorting = "description",
        bool $desc = false
    ) {
        $order = '';
        if ($sorting == "description") {
            $order = ' ORDER BY P.description' . ($desc ? ' DESC' : '');
        } else if ($sorting == "points") {
            $order = ' ORDER BY points' . ($desc ? ' DESC' : '');
        }

        $sql = '
        SELECT 
            P.`description`,
            CASE 
                WHEN P.`is_quantity` = "true" THEN 
                    COALESCE((SELECT COUNT(*) / P.`min_quantity`
                            FROM `sales` S
                            WHERE `product_id` = P.`id`
                            AND `user_id` = ' . $user_id . ' AND S.`status` = "true"), 0)
                WHEN P.`is_quantity` = "false" THEN 
                    COALESCE((SELECT SUM(`value`) / P.`min_value`
                            FROM `sales` S
                            WHERE `product_id` = P.`id`
                            AND `user_id` = ' . $user_id . ' AND S.`status` = "true"), 0)
                ELSE 0
            END AS `points`
        FROM `products` P
        LEFT JOIN users U ON U.id = ' . $user_id . '
        WHERE P.card = "Cartela Secundária"
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

// SELECT SUM(`value`) / P.`min_value` AS `points_for_value` FROM `sales` S, `products` P, `users` U WHERE `product_id` = P.`id` AND `user_id` = U.`id` AND P.`is_quantity` = "false" AND P.`id` = 10 AND U.`id` = 1 GROUP BY S.`product_id`;