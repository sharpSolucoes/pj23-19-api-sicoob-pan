<?php
class Ranking extends API_configuration
{
    public function read(
        string $sorting = null,
        bool $is_desc = null,
        int $limit = null
    ) {
        $order = '';
        if ($sorting !== null && $is_desc !== null) {
            $order = 'ORDER BY ' . ($sorting === "points" ? '`total_points`' : 'U.`name`') . ' ' . ($is_desc ? 'DESC' : 'ASC');
        }

        $sql = '
        SELECT U.`name`,
            SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) AS `total_points`
        FROM `users` U
        LEFT JOIN (
            SELECT S.`user_id`, FLOOR(COUNT(*) / P.`min_quantity`) AS `points_for_quantity`
            FROM `sales` S
            INNER JOIN `products` P ON S.`product_id` = P.`id`
            WHERE P.`is_quantity` = "true" AND S.`status` = "true"
            GROUP BY S.`user_id`
        ) AS sub1 ON U.`id` = sub1.`user_id`
        LEFT JOIN (
            SELECT S.`user_id`, SUM(FLOOR(`value` / P.`min_value`)) AS `points_for_value`
            FROM `sales` S
            INNER JOIN `products` P ON S.`product_id` = P.`id`
            WHERE P.`is_quantity` = "false" AND S.`status` = "true"
            GROUP BY S.`user_id`
        ) AS sub2 ON U.`id` = sub2.`user_id`
        GROUP BY U.`id`
        ' . $order . '
        ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
        ';
        $get_ranking = $this->db_read($sql);
        if ($get_ranking) {
            $response = [];
            while ($ranking = $this->db_object($get_ranking)) {
                $response[] = [
                    'name' => mb_convert_case($ranking->name, MB_CASE_TITLE, 'UTF-8'),
                    'points' => (int) $ranking->total_points
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
}
