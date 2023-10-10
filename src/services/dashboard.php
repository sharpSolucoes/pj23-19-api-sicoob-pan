<?php
require_once "users.php";
require_once "agencies.php";
require_once "products.php";
class Dashboard extends API_configuration
{
    private $agencies;
    private $products;
    public function __construct()
    {
        parent::__construct();
        $this->agencies = new Agencies();
        $this->products = new Products();
    }

    public function read(
        string $initial_date = null,
        string $final_date = null
    ) {
        $query_params = '';
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
            $query_params = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
            $query_params = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        }


        return [
            'salesForAgency' => $this->read_for_agency($query_params),
            'salesForDay' => $this->read_for_day($query_params),
            'salesForProduct' => $this->read_for_product($query_params)
        ];
    }

    protected function read_for_agency(
        string $query_params
    ) {
        $sql = 'SELECT `agency_id`, COUNT(*) AS `number` FROM `sales` ' . $query_params . ' GROUP BY `agency_id`';
        $sales = $this->db_read($sql);
        if ($sales) {
            $response = [];
            while ($sale = $this->db_object($sales)) {
                $response[] = [
                    'agency' => $this->agencies->read_by_id((int) $sale->agency_id)->name,
                    'number' => (int) $sale->number,
                ];
            }

            return [
                'sales' => $response,
                'total' => array_sum(array_column($response, 'number')),
            ];
        } else {
            return [];
        }
    }

    protected function read_for_day(
        string $query_params
    ) {
        $sql = 'SELECT `date`, COUNT(*) AS `number` FROM `sales` ' . $query_params . ' GROUP BY DATE(`date`) ORDER BY DATE(`date`);';
        $sales = $this->db_read($sql);
        if ($sales) {
            $response = [];
            while ($sale = $this->db_object($sales)) {
                $response[] = [
                    'date' => $sale->date,
                    'number' => (int) $sale->number,
                ];
            }

            return [
                'sales' => $response,
                'total' => array_sum(array_column($response, 'number')),
            ];
        } else {
            return [];
        }
    }

    protected function read_for_product(
        string $query_params
    ) {
        $sql = 'SELECT `product_id`, COUNT(*) AS `number` FROM `sales` ' . $query_params . ' GROUP BY `product_id`;';
        $sales = $this->db_read($sql);
        if ($sales) {
            $response = [];
            while ($sale = $this->db_object($sales)) {
                $response[] = [
                    'product' => $this->products->read_by_id((int) $sale->product_id)->description,
                    'number' => (int) $sale->number,
                ];
            }

            return [
                'sales' => $response,
                'total' => array_sum(array_column($response, 'number')),
            ];
        } else {
            return [];
        }
    }

    protected function read_ranking_employees()
    {
        $sql = '
        SELECT U.`name`,
            SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) AS `total_points`
        FROM `users` U
        LEFT JOIN (
            SELECT S.`user_id`, COUNT(*) / P.`min_quantity` AS `points_for_quantity`
            FROM `sales` S
            INNER JOIN `products` P ON S.`product_id` = P.`id`
            WHERE P.`is_quantity` = "true"
            GROUP BY S.`user_id`
        ) AS sub1 ON U.`id` = sub1.`user_id`
        LEFT JOIN (
            SELECT S.`user_id`, SUM(`value` / P.`min_value`) AS `points_for_value`
            FROM `sales` S
            INNER JOIN `products` P ON S.`product_id` = P.`id`
            WHERE P.`is_quantity` = "false"
            GROUP BY S.`user_id`
        ) AS sub2 ON U.`id` = sub2.`user_id`
        GROUP BY U.`id`
        LIMIT 10
        ';
        $get_sales = $this->db_read($sql);
        if ($get_sales) {
            $response = [];
            $position = 1;
            while ($sale = $this->db_object($get_sales)) {
                $response[] = [
                    'position' => (string) $position++,
                    'employee' => $sale->name,
                    'totalPoints' => (int) $sale->total_points,
                ];
            }

            array_push($response, [
                'position' => '',
                'employee' => 'Total',
                'totalPoints' => array_sum(array_column($response, 'totalPoints')),
            ]);

            return $response;
        } else {
            return [];
        }
    }
}
