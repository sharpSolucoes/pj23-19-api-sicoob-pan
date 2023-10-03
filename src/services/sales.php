<?php
require_once "agencies.php";
require_once "products.php";
require_once "users.php";
require_once "prospects.php";
class Sales extends API_configuration {
    private $products;
    private $agencies;
    private $users;
    private $prospects;
    public function __construct() {
        parent::__construct();
        $this->products = new Products();
        $this->agencies = new Agencies();
        $this->users = new Users();
        $this->prospects = new Prospects();
    }
    public function create(
        int $user_id,
        int $agency_id,
        int $product_id,
        bool $is_associate,
        bool $is_employee,
        string $legal_nature,
        string $value,
        string $description,
        array $associate,
        array $physical_person,
        array $legal_person
    ) {
        $verify_prospect = $this->prospects->verify($user_id, $associate, $product_id);

        $values = '
        ' . $user_id . ',
        ' . $agency_id . ',
        ' . $product_id . ',
        "' . date('Y-m-d H:i:s') . '",
        "' . $description . '",
        "' . $value . '",
        "' . ($is_associate ? "true" : "false") . '",
        "' . ($is_employee ? "true" : "false") . '",
        "' . $legal_nature . '",
        "' . ($associate['name'] ? $associate['name'] : "") . '",
        "' . ($associate['numberAccount'] ? $associate['numberAccount'] : "") . '",
        "' . ($legal_person['socialReason'] ? $legal_person['socialReason'] : "") . '",
        "' . ($legal_person['cnpj'] ? $legal_person['cnpj'] : "") . '",
        "' . ($physical_person['name'] ? $physical_person['name'] : "") . '",
        "' . ($physical_person['cpf'] ? $physical_person['cpf'] : "") . '",
        "' . ($verify_prospect === true ? "true" : "false") . '"
        ';
        $sql = 'INSERT INTO `sales`(`user_id`, `agency_id`, `product_id`, `date`, `description`, `value`, `is_associate`, `is_employee`, `legal_nature`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `status`) VALUES (' . $values . ')';
        $sale_created = $this->db_create($sql);
        if ($sale_created) {
            $create_prospect = $this->prospects->create($user_id, $product_id, "Venda", "", 10, "", ["name" => ($associate['name'] != "" ? $associate['name'] : ($physical_person['name'] != "" ? $physical_person['name'] : $legal_person['socialReason'])), "numberAccount" => ($associate['numberAccount'] != "" ? $associate['numberAccount'] : ($physical_person['cpf'] != "" ? $physical_person['cpf'] : $legal_person['cnpj']))]);

            if ($create_prospect) {
                $slug = $this->slugify($sale_created . '-' . $user_id . '-' . $agency_id . '-' . $product_id . '-' . date('YmdHis'));
                $sql = 'UPDATE `sales` SET `slug`="' . $slug . '" WHERE `id`=' . $sale_created;
                $this->db_update($sql);
    
                return $this->read_by_slug($slug);
            } else {
                return false;
            }
        } else {
            return false;
        }
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

        $sql = 'SELECT `slug`, `date`, `agency_id`, `product_id`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `status` FROM `sales` ' . $query_parm . ' ORDER BY `date` DESC';
        $sales = $this->db_read($sql);
        if ($sales) {
            $response = [];
            while ($sale = $this->db_object($sales)) {
                $response[] = [
                    'date' => $sale->date,
                    'agency' => $this->agencies->read_by_id((int) $sale->agency_id),
                    'product' => $this->products->read_by_id((int) $sale->product_id),
                    'buyer' => [
                        'nameOrSocialReason' => ($sale->associate_name ? $sale->associate_name : ($sale->physical_person_name ? $sale->physical_person_name : $sale->legal_person_social_reason)),
                        'numberAccountOrDocument' => ($sale->associate_number_account ? $sale->associate_number_account : ($sale->physical_person_cpf ? $sale->physical_person_cpf : $sale->legal_person_cnpj))
                    ],
                    'status' => $sale->status == "true" ? true : false,
                    'slug' => $sale->slug
                ];
            }
            return $response;
        } else {
            return [];
        }
    }
    
    public function read_reports(
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

        $sql = 'SELECT `date`, `user_id`, `agency_id`, `product_id`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `value` FROM `sales` ' . $query_parm . ' ORDER BY `date` DESC';
        $sales = $this->db_read($sql);
        if ($sales) {
            $response = [];
            while ($sale = $this->db_object($sales)) {
                $response[] = [
                    'date' => $sale->date,
                    'user' => $this->users->read_by_id((int) $sale->user_id),
                    'agency' => $this->agencies->read_by_id((int) $sale->agency_id),
                    'product' => $this->products->read_by_id((int) $sale->product_id),
                    'buyer' => [
                        'nameOrSocialReason' => ($sale->associate_name ? $sale->associate_name : ($sale->physical_person_name ? $sale->physical_person_name : $sale->legal_person_social_reason)),
                        'numberAccountOrDocument' => ($sale->associate_number_account ? $sale->associate_number_account : ($sale->physical_person_cpf ? $sale->physical_person_cpf : $sale->legal_person_cnpj))
                    ],
                    'points' => 100,
                    'value' => $sale->value
                ];
            }
            return $response;
        } else {
            return [];
        }
    }

    public function read_by_slug(string $slug) {
        $sql = 'SELECT * FROM `sales` WHERE `slug` = "' . $slug . '"';
        $sales = $this->db_read($sql);
        if ($sales) {
            $sales = $this->db_object($sales);

            return [
                'id' => (int) $sales->id,
                'agencyId' => (int) $sales->agency_id,
                'productId' => (int) $sales->product_id,
                'description' => $sales->description,
                'value' => $sales->value,
                'isAssociate' => $sales->is_associate == "true" ? true : false,
                'isEmployee' => $sales->is_employee == "true" ? true : false,
                'legalNature' => $sales->legal_nature,
                'associate' => [
                    'name' => $sales->associate_name ? $sales->associate_name : null,
                    'numberAccount' => $sales->associate_number_account ? $sales->associate_number_account : null
                ],
                'legalPerson' => [
                    'socialReason' => $sales->legal_person_social_reason ? $sales->legal_person_social_reason : null,
                    'cnpj' => $sales->legal_person_cnpj ? $sales->legal_person_cnpj : null
                ],
                'physicalPerson' => [
                    'name' => $sales->physical_person_name ? $sales->physical_person_name : null,
                    'cpf' => $sales->physical_person_cpf ? $sales->physical_person_cpf : null
                ],
                'status' => $sales->status == "true" ? true : false,
                'slug' => $sales->slug
            ];
        } else {
            return [];
        }
    }

    public function read_by_id(int $id) {
        $sql = 'SELECT * FROM `sales` WHERE `id`=' . $id;
        $sale = $this->db_read($sql);
        if ($sale) {
            $sale = $this->db_object($sale);
            $sale->id = (int) $sale->id;
            $sale->user_id = (int) $sale->user_id;
            $sale->agency_id = (int) $sale->agency_id;
            return $sale;
        } else {
            return [];
        }
    }

    public function update(
        int $id,
        int $agency_id,
        int $product_id,
        bool $is_associate,
        bool $is_employee,
        bool $status,
        string $legal_nature,
        string $value,
        string $description,
        array $associate,
        array $physical_person,
        array $legal_person
    ) {
        $old_sale = $this->read_by_id($id);

        $sql = '
        UPDATE `sales` SET
            `agency_id`=' . $agency_id . ',
            `product_id`=' . $product_id . ',
            `description`="' . $description . '",
            `value`="' . $value . '",
            `is_associate`="' . ($is_associate ? "true" : "false") . '",
            `is_employee`="' . ($is_employee ? "true" : "false") . '",
            `status`="' . ($status ? "true" : "false") . '",
            `legal_nature`="' . $legal_nature . '",
            `associate_name`="' . ($associate['name'] ? $associate['name'] : "") . '",
            `associate_number_account`="' . ($associate['numberAccount'] ? $associate['numberAccount'] : "") . '",
            `legal_person_social_reason`="' . ($legal_person['socialReason'] ? $legal_person['socialReason'] : "") . '",
            `legal_person_cnpj`="' . ($legal_person['cnpj'] ? $legal_person['cnpj'] : "") . '",
            `physical_person_name`="' . ($physical_person['name'] ? $physical_person['name'] : "") . '",
            `physical_person_cpf`="' . ($physical_person['cpf'] ? $physical_person['cpf'] : "") . '",
            `slug`= "' . $this->slugify($id . '-' . $old_sale->user_id . '-' . $agency_id . '-' . $product_id . '-' . date('YmdHis')) . '"
        WHERE `id`=' . $id;
        $product_updated = $this->db_update($sql);
        if ($product_updated) {
            return [
                'old' => $old_sale,
                'new' => $this->read_by_id($id)
            ];
        } else {
            return false;
        }
    }

    public function delete(string $slug) {
        $old_sale = $this->read_by_slug($slug);
        $sql = 'DELETE FROM `sales` WHERE `slug`="' . $slug . '"';
        if ($this->db_delete($sql)) {
            return $old_sale;
        } else {
            return false;
        }
    }
}