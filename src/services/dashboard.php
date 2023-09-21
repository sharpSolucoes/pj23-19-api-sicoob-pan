<?php
require_once "users.php";
require_once "agencies.php";
require_once "products.php";
class Dashboard extends API_configuration {
    private $users;
    private $agencies;
    private $products;
    public function __construct() {
        parent::__construct();
        $this->users = new Users();
        $this->agencies = new Agencies();
        $this->products = new Products();
    }

    public function read(
        string $initial_date = null,
        string $final_date = null
    ) {
        $query_parm = '';
        if ($initial_date && $final_date) {
            $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
            $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        } else {
            $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
            $query_parm = ' WHERE `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
        }

        
        return [
            'salesForAgency' => $this->read_for_agency($query_parm),
            'salesForDay' => $this->read_for_day($query_parm),
            'salesForProduct' => $this->read_for_product($query_parm),
        ];
        
    }

    private function read_for_agency (
        string $query_parms
    ) {
        $sql = 'SELECT `agency_id`, COUNT(*) AS `number` FROM `sales` ' . $query_parms . ' GROUP BY `agency_id`';
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

    private function read_for_day (
        string $query_parms
    ) {
        $sql = 'SELECT `date`, COUNT(*) AS `number` FROM `sales` ' . $query_parms . ' GROUP BY DATE(`date`) ORDER BY DATE(`date`);';
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
    
    private function read_for_product (
        string $query_parms
    ) {
        $sql = 'SELECT `product_id`, COUNT(*) AS `number` FROM `sales` ' . $query_parms . ' GROUP BY `product_id`;';
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
}