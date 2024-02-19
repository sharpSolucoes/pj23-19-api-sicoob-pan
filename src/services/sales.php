<?php
require_once "agencies.php";
require_once "products.php";
require_once "users.php";
require_once "prospects.php";
require_once "notifications.php";
class Sales extends API_configuration
{
  private $products;
  private $agencies;
  private $users;
  private $prospects;
  private $notifications;
  public function __construct()
  {
    parent::__construct();
    $this->products = new Products();
    $this->agencies = new Agencies();
    $this->users = new Users();
    $this->prospects = new Prospects();
    $this->notifications = new Notifications();
  }
  public function create(
    int $user_id,
    int $agency_id,
    int $product_id,
    bool $is_associate,
    bool $is_employee,
    bool $change_punctuation,
    string $product_for_punctuation,
    string $legal_nature,
    string $value,
    string $description,
    array $associate,
    array $physical_person,
    array $legal_person,
    $attachment = null
  ) {
    $verify_prospect = $this->prospects->verify($user_id, $associate, $product_id);

    $values = '
        ' . $user_id . ',
        ' . $agency_id . ',
        ' . $product_id . ',
        "' . date('Y-m-d H:i:s') . '",
        "' . $description . '",
        "' . $this->value_formatted_for_save($value) . '",
        "' . ($is_associate ? "true" : "false") . '",
        "' . ($is_employee ? "true" : "false") . '",
        "' . ($change_punctuation ? "true" : "false") . '",
        "' . $product_for_punctuation . '",
        "' . $legal_nature . '",
        "' . ($associate['name'] ? $associate['name'] : "") . '",
        "' . ($associate['numberAccount'] ? $associate['numberAccount'] : "") . '",
        "' . ($legal_person['socialReason'] ? $legal_person['socialReason'] : "") . '",
        "' . ($legal_person['cnpj'] ? $legal_person['cnpj'] : "") . '",
        "' . ($physical_person['name'] ? $physical_person['name'] : "") . '",
        "' . ($physical_person['cpf'] ? $physical_person['cpf'] : "") . '",
        "' . ($verify_prospect === true ? "true" : "false") . '"
        ';
    $sql = 'INSERT INTO `sales`(`user_id`, `agency_id`, `product_id`, `date`, `description`, `value`, `is_associate`, `is_employee`, `change_punctuation`, `product_for_punctuation`, `legal_nature`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `status`) VALUES (' . $values . ')';
    $sale_created = $this->db_create($sql);
    if ($sale_created) {
      if ($attachment != null) {
        $file_name = pathinfo($attachment->name, PATHINFO_FILENAME);
        $file_extension = pathinfo($attachment->name, PATHINFO_EXTENSION);
        $file_extension = strtolower($file_extension);
        $file_name = $sale_created . '-' . $this->slugify($file_name) . '.' . $file_extension;

        $directory = '/../../public/sales/';
        $file_path = $directory . $file_name;

        $file_uploaded = $this->base64_to_file($attachment->content, __DIR__ . $file_path);

        if ($file_uploaded) {
          $sql = 'INSERT INTO `sales_attachments`(`sale_id`, `file_name`, `file_old_name`) VALUES (' . $sale_created . ', "' . $file_name . '", "' . $attachment->name . '")';
          $this->db_update($sql);
        } else {
          $sql = 'DELETE FROM `sales` WHERE `id`=' . $sale_created;
          $this->db_delete($sql);
          return false;
        }
      }

      $create_prospect = $this->prospects->create($user_id, $product_id, "Venda", "", 10, "", ["name" => ($associate['name'] != "" ? $associate['name'] : ($physical_person['name'] != "" ? $physical_person['name'] : $legal_person['socialReason'])), "numberAccount" => ($associate['numberAccount'] != "" ? $associate['numberAccount'] : ($physical_person['cpf'] != "" ? $physical_person['cpf'] : $legal_person['cnpj']))]);

      if ($create_prospect) {
        $slug = $this->slugify($sale_created . '-' . ($associate['name'] != "" ? $associate['name'] : ($physical_person['name'] != "" ? $physical_person['name'] : $legal_person['socialReason'])));
        $sql = 'UPDATE `sales` SET `slug`="' . $slug . '" WHERE `id`=' . $sale_created;
        $this->db_update($sql);

        $this->notifications->create(
          'Nova venda',
          'O usuário ' . $this->users->read_by_id($user_id)->name . ' criou uma nova venda.',
          '/sales/' . $slug
        );

        return $this->read_by_slug($slug);
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  public function read(
    int $user_id,
    string $initial_date = null,
    string $final_date = null,
    string $associate_name = null,
    string $associate_number_account = null,
    int $user_find = null,
    int $agency = null
  ) {
    $user = $this->users->read_by_id($user_id);
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

    if ($associate_name) {
      $query_parm .= ' AND `associate_name` LIKE "%' . $associate_name . '%"';
    }

    if ($associate_number_account) {
      $query_parm .= ' AND `associate_number_account` LIKE "%' . $associate_number_account . '%"';
    }

    if ($user_find) {
      $query_parm .= ' AND `user_id` = ' . $user_find;
    }

    if ($agency) {
      $query_parm .= ' AND `agency_id` = ' . $agency;
    }

    if ($user->position == "Administrador" || $user->position == "Suporte") {
      $sql = 'SELECT `user_id`, `slug`, `date`, `agency_id`, `product_id`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `status` FROM `sales` ' . $query_parm . ' ORDER BY `date` DESC';
    } else if ($user->position == "Gestor") {
      $sql = 'SELECT `team_id` FROM `teams_users` WHERE `user_id` = ' . $user->id . ' LIMIT 1';
      $teams = $this->db_read($sql);
      $teams = $this->db_object($teams);

      $sql = '
                SELECT 
                    S.`user_id`,
                    S.`slug`, 
                    S.`date`, 
                    S.`agency_id`, 
                    S.`product_id`, 
                    S.`associate_name`, 
                    S.`associate_number_account`, 
                    S.`legal_person_social_reason`, 
                    S.`legal_person_cnpj`, 
                    S.`physical_person_name`, 
                    S.`physical_person_cpf`, 
                    S.`status` 
                FROM 
                    `sales` S
                INNER JOIN `teams_users` TU ON S.`user_id` = TU.`user_id`
                INNER JOIN `teams` T ON TU.`team_id` = T.`id`
                ' . $query_parm . ' AND T.`id` = ' . (int) $teams->team_id . ' 
                ORDER BY `date` DESC';
    } else if ($user->position == "Usuário") {
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

      if ($associate_name) {
        $query_parm .= ' AND `associate_name` LIKE "%' . $associate_name . '%"';
      }

      if ($associate_number_account) {
        $query_parm .= ' AND `associate_number_account` LIKE "%' . $associate_number_account . '%"';
      }

      $query_parm .= ' AND `user_id` = ' . $user_id;

      $sql = 'SELECT `user_id`, `slug`, `date`, `agency_id`, `product_id`, `associate_name`, `associate_number_account`, `legal_person_social_reason`, `legal_person_cnpj`, `physical_person_name`, `physical_person_cpf`, `status` FROM `sales` ' . $query_parm . ' ORDER BY `date` DESC';
    } else {
      return [];
    }
    $sales = $this->db_read($sql);
    if ($sales) {
      $response = [];
      while ($sale = $this->db_object($sales)) {
        $response[] = [
          'user' => $this->users->read_by_id((int) $sale->user_id),
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
    int $user_id,
    string $initial_date = null,
    string $final_date = null,
    string $associate_name = null,
    string $associate_number_account = null,
    string $has_exchange = null,
    int $user_find = null,
    int $agency = null
  ) {
    $user = $this->users->read_by_id($user_id);

    if (!$user) {
      return [
        'data' => [],
        'pointsForProducts' => []
      ];
    }

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

    if ($associate_name) {
      $query_parm .= ' AND `associate_name` LIKE "' . $associate_name . '"';
    }

    if ($associate_number_account) {
      $query_parm .= ' AND `associate_number_account` LIKE "' . $associate_number_account . '"';
    }

    if ($has_exchange) {
      $query_parm .= ' AND `change_punctuation` = "' . $has_exchange . '"';
    }

    if ($user_find) {
      $query_parm .= ' AND S1.`user_id` = ' . $user_find;
    }

    if ($agency) {
      $query_parm .= ' AND S1.`agency_id` = ' . $agency;
    } else if ($agency == 'undefined') {
      $query_parm .= ' AND S1.`agency_id` = ' . $user->agency_id;
    }

    $points_for_products = [];
    if ($user->position == "Usuário") {
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

      if ($associate_name) {
        $query_parm .= ' AND `associate_name` LIKE "' . $associate_name . '"';
      }

      if ($associate_number_account) {
        $query_parm .= ' AND `associate_number_account` LIKE "' . $associate_number_account . '"';
      }

      if ($has_exchange) {
        $query_parm .= ' AND `change_punctuation` = "' . $has_exchange . '"';
      }
      $query_parm .= ' AND `user_id` = ' . $user_id;
      $sql = '
                SELECT 
                    S1.`id`, 
                    S1.`date`, 
                    S1.`user_id`, 
                    S1.`agency_id`, 
                    S1.`product_id`, 
                    S1.`associate_name`, 
                    S1.`associate_number_account`, 
                    S1.`legal_person_social_reason`, 
                    S1.`legal_person_cnpj`, 
                    S1.`physical_person_name`, 
                    S1.`physical_person_cpf`, 
                    S1.`value`, 
                    S1.`status`,
                    S1.`change_punctuation`,
                    S1.`product_for_punctuation`,
                    (
                        SELECT
                            CASE 
                                WHEN P.`is_quantity` = "true" THEN 
                                    COALESCE((
                                        SELECT 
                                            (COUNT(*) / P.`min_quantity`) * P.`points`
                                        FROM 
                                            `sales` S2
                                        WHERE 
                                            S2.`product_id` = P.`id` AND S2.`id` = S1.`id`
                                    ), 0)
                                WHEN P.`is_quantity` = "false" THEN
                                    CASE
                                        WHEN S1.`value` >= P.`min_value` THEN
                                        COALESCE((
                                            SELECT 
                                            P.`points`
                                            FROM 
                                            `sales` S4
                                            WHERE 
                                            S4.`product_id` = P.`id` AND S4.`id` = S1.`id`
                                        ), 0)
                                        ELSE 0
                                    END
                                ELSE 0
                            END AS `points`
                        FROM 
                            `products` P 
                        WHERE 
                            P.`id` = S1.`product_id`
                    ) AS `points`
                FROM 
                    `sales` S1 
               ' . $query_parm . '
                ORDER BY `date` DESC
            ';
      // return $sql;
      $sales = $this->db_read($sql);
      $query_parms_sales = '';

      if ($initial_date && $final_date) {
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      } else {
        $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
        $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      }

      if ($associate_name) {
        $query_parms_sales .= ' AND `associate_name` LIKE "' . $associate_name . '"';
      }

      if ($associate_number_account) {
        $query_parms_sales .= ' AND `associate_number_account` LIKE "' . $associate_number_account . '"';
      }

      if ($has_exchange) {
        $query_parms_sales .= ' AND `change_punctuation` = "' . $has_exchange . '"';
      }

      if ($user_find) {
        $query_parms_sales .= ' AND `user_id` = ' . $user_find;
      }

      if ($agency) {
        $query_parms_sales .= ' AND `agency_id` = ' . $agency;
      }

      $sql = '
                SELECT 
                    product_id,
                    SUM(points) AS points
                FROM (
                    SELECT 
                        S1.`product_id`,
                        CASE 
                            WHEN P.`is_quantity` = "true" THEN 
                                COALESCE((COUNT(*) / P.`min_quantity`) * P.`points`, 0)
                            WHEN P.`is_quantity` = "false" AND S1.`value` >= P.`min_value` THEN 
                                (SELECT COUNT(`id`) FROM `sales` WHERE `value` >= P.`min_value` AND P.id = `product_id` ' . $query_parms_sales . ') * P.`points`
                            ELSE 0
                        END AS `points`
                    FROM 
                        `sales` S1
                    INNER JOIN 
                        `products` P ON P.`id` = S1.`product_id` 
                    ' . $query_parm . '
                    GROUP BY 
                        S1.`product_id`, P.`is_quantity`, P.`min_quantity`, P.`min_value`
                ) AS `sales`
                GROUP BY 
                product_id;
            ';
      // return $sql;
      $get_points_for_products = $this->db_read($sql);
      if ($get_points_for_products) {
        while ($get_point_for_product = $this->db_object($get_points_for_products)) {
          $points_for_products[] = [
            'product' => $this->products->read_by_id((int) $get_point_for_product->product_id)->description,
            'points' => (float) $get_point_for_product->points
          ];
        }
        array_push($points_for_products, ['product' => 'Total', 'points' => array_sum(array_column($points_for_products, 'points'))]);
      }
    } else if ($user->position == "Gestor") {
      $sql = 'SELECT `team_id` FROM `teams_users` WHERE `user_id` = ' . $user->id . ' LIMIT 1';
      $teams = $this->db_read($sql);
      $teams = $this->db_object($teams);

      $sql = '
                SELECT 
                    S1.`id`, 
                    S1.`date`, 
                    S1.`user_id`, 
                    S1.`agency_id`, 
                    S1.`product_id`, 
                    S1.`associate_name`, 
                    S1.`associate_number_account`, 
                    S1.`legal_person_social_reason`, 
                    S1.`legal_person_cnpj`, 
                    S1.`physical_person_name`, 
                    S1.`physical_person_cpf`, 
                    S1.`value`, 
                    S1.`status`,
                    S1.`change_punctuation`,
                    S1.`product_for_punctuation`,
                    (
                        SELECT
                            CASE 
                                WHEN P.`is_quantity` = "true" THEN 
                                    COALESCE((
                                        SELECT 
                                            (COUNT(*) / P.`min_quantity`) * P.`points`
                                        FROM 
                                            `sales` S2
                                        WHERE 
                                            S2.`product_id` = P.`id` AND S2.`id` = S1.`id`
                                    ), 0)
                                WHEN P.`is_quantity` = "false" THEN
                                    CASE
                                        WHEN S1.`value` >= P.`min_value` THEN
                                        COALESCE((
                                            SELECT 
                                            P.`points`
                                            FROM 
                                            `sales` S4
                                            WHERE 
                                            S4.`product_id` = P.`id` AND S4.`id` = S1.`id`
                                        ), 0)
                                        ELSE 0
                                    END
                                ELSE 0
                            END AS `points`
                        FROM 
                            `products` P 
                        WHERE 
                            P.`id` = S1.`product_id`
                    ) AS `points`
                FROM 
                    `sales` S1 
                ' . $query_parm . '
                GROUP BY 
                    S1.`id`
                ORDER BY `date` DESC
            ';
      $sales = $this->db_read($sql);

      $query_parms_sales = '';

      if ($initial_date && $final_date) {
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      } else {
        $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
        $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      }

      if ($associate_name) {
        $query_parms_sales .= ' AND `associate_name` LIKE "' . $associate_name . '"';
      }

      if ($associate_number_account) {
        $query_parms_sales .= ' AND `associate_number_account` LIKE "' . $associate_number_account . '"';
      }

      if ($has_exchange) {
        $query_parms_sales .= ' AND `change_punctuation` = "' . $has_exchange . '"';
      }

      if ($user_find) {
        $query_parms_sales .= ' AND `user_id` = ' . $user_find;
      }

      if ($agency) {
        $query_parms_sales .= ' AND `agency_id` = ' . $agency;
      } else if ($agency == 'undefined') {
        $query_parms_sales .= ' AND `agency_id` = ' . $user->agency_id;
      }

      $sql = '
                SELECT 
                    product_id,
                    SUM(points) AS points
                FROM (
                    SELECT 
                        S1.`product_id`,
                        CASE 
                            WHEN P.`is_quantity` = "true" THEN 
                                COALESCE((COUNT(*) / P.`min_quantity`) * P.`points`, 0)
                            WHEN P.`is_quantity` = "false" AND S1.`value` >= P.`min_value` THEN 
                                (SELECT COUNT(`id`) FROM `sales` WHERE `value` >= P.`min_value` AND P.id = `product_id` ' . $query_parms_sales . ') * P.`points`
                            ELSE 0
                        END AS `points`
                    FROM 
                        `sales` S1
                    INNER JOIN 
                        `products` P ON P.`id` = S1.`product_id` 
                    ' . $query_parm . '
                    GROUP BY 
                        S1.`product_id`, P.`is_quantity`, P.`min_quantity`, P.`min_value`
                ) AS `sales`
                GROUP BY 
                product_id;
            ';
      $get_points_for_products = $this->db_read($sql);
      if ($get_points_for_products) {
        while ($get_point_for_product = $this->db_object($get_points_for_products)) {
          $points_for_products[] = [
            'product' => $this->products->read_by_id((int) $get_point_for_product->product_id)->description,
            'points' => (float) $get_point_for_product->points
          ];
        }
        array_push($points_for_products, ['product' => 'Total', 'points' => array_sum(array_column($points_for_products, 'points'))]);
      }
    } else {
      $sql = '
                SELECT 
                    S1.`id`, 
                    S1.`date`, 
                    S1.`user_id`, 
                    S1.`agency_id`, 
                    S1.`product_id`, 
                    S1.`associate_name`, 
                    S1.`associate_number_account`, 
                    S1.`legal_person_social_reason`, 
                    S1.`legal_person_cnpj`, 
                    S1.`physical_person_name`, 
                    S1.`physical_person_cpf`, 
                    S1.`value`, 
                    S1.`status`,
                    S1.`change_punctuation`,
                    S1.`product_for_punctuation`,
                    (
                        SELECT
                            CASE 
                                WHEN P.`is_quantity` = "true" THEN 
                                    COALESCE((
                                        SELECT 
                                            (COUNT(*) / P.`min_quantity`) * P.`points`
                                        FROM 
                                            `sales` S2
                                        WHERE 
                                            S2.`product_id` = P.`id` AND S2.`id` = S1.`id`
                                    ), 0)
                                WHEN P.`is_quantity` = "false" THEN
                                    CASE
                                        WHEN S1.`value` >= P.`min_value` THEN
                                        COALESCE((
                                            SELECT 
                                            P.`points`
                                            FROM 
                                            `sales` S4
                                            WHERE 
                                            S4.`product_id` = P.`id` AND S4.`id` = S1.`id`
                                        ), 0)
                                        ELSE 0
                                    END
                                ELSE 0
                            END AS `points`
                        FROM 
                            `products` P 
                        WHERE 
                            P.`id` = S1.`product_id`
                    ) AS `points`
                FROM 
                    `sales` S1 
                ' . $query_parm . ' 
                ORDER BY `date` DESC
            ';
      $sales = $this->db_read($sql);
      $query_parms_sales = '';

      if ($initial_date && $final_date) {
        $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
        $final_date = date('Y-m-d 23:59:59', strtotime($final_date));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      } else {
        $initial_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
        $final_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        $query_parms_sales = ' AND `date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"';
      }

      if ($associate_name) {
        $query_parms_sales .= ' AND `associate_name` LIKE "' . $associate_name . '"';
      }

      if ($associate_number_account) {
        $query_parms_sales .= ' AND `associate_number_account` LIKE "' . $associate_number_account . '"';
      }

      if ($has_exchange) {
        $query_parms_sales .= ' AND `change_punctuation` = "' . $has_exchange . '"';
      }

      if ($user_find) {
        $query_parms_sales .= ' AND `user_id` = ' . $user_find;
      }

      if ($agency) {
        $query_parms_sales .= ' AND `agency_id` = ' . $agency;
      }

      $sql = '
                SELECT 
                    product_id,
                    SUM(points) AS points
                FROM (
                    SELECT 
                        S1.`product_id`,
                        CASE 
                            WHEN P.`is_quantity` = "true" THEN 
                                COALESCE((COUNT(*) / P.`min_quantity`) * P.`points`, 0)
                            WHEN P.`is_quantity` = "false" AND S1.`value` >= P.`min_value` THEN 
                                (SELECT COUNT(`id`) FROM `sales` WHERE `value` >= P.`min_value` AND P.id = `product_id` ' . $query_parms_sales . ') * P.`points`
                            ELSE 0
                        END AS `points`
                    FROM 
                        `sales` S1
                    INNER JOIN 
                        `products` P ON P.`id` = S1.`product_id` 
                    ' . $query_parm . '
                    GROUP BY 
                        S1.`product_id`, P.`is_quantity`, P.`min_quantity`, P.`min_value`
                ) AS `sales`
                GROUP BY 
                product_id;
            ';
      // return $sql;
      $get_points_for_products = $this->db_read($sql);
      if ($get_points_for_products) {
        while ($product = $this->db_object($get_points_for_products)) {
          $points_for_products[] = [
            'product' => $this->products->read_by_id((int) $product->product_id)->description,
            'points' => (float) $product->points
          ];
        }
        array_push($points_for_products, ['product' => 'Total', 'points' => array_sum(array_column($points_for_products, 'points'))]);
      }
    }

    if ($sales) {
      $data = [];
      while ($sale = $this->db_object($sales)) {
        $data[] = [
          'date' => $sale->date,
          'user' => $this->users->read_by_id((int) $sale->user_id),
          'agency' => $this->agencies->read_by_id((int) $sale->agency_id),
          'product' => $this->products->read_by_id((int) $sale->product_id),
          'buyer' => [
            'nameOrSocialReason' => ($sale->associate_name ? $sale->associate_name : ($sale->physical_person_name ? $sale->physical_person_name : $sale->legal_person_social_reason)),
            'numberAccountOrDocument' => ($sale->associate_number_account ? $sale->associate_number_account : ($sale->physical_person_cpf ? $sale->physical_person_cpf : $sale->legal_person_cnpj))
          ],
          'points' => (float) $sale->points,
          'value' => $sale->value,
          'status' => $sale->status == "true" ? true : false,
          'changePunctuation' => $sale->change_punctuation == "true" ? true : false,
          'productForPunctuation' => $sale->product_for_punctuation != 0 ? $this->products->read_by_id((int) $sale->product_for_punctuation) : null,
        ];
      }
      return [
        'data' => $data,
        'pointsForProducts' => $points_for_products
      ];
    } else {
      return [
        'data' => [],
        'pointsForProducts' => $points_for_products
      ];
    }
  }

  public function read_by_slug(string $slug)
  {
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
        'changePunctuation' => $sales->change_punctuation == "true" ? true : false,
        'productForPunctuation' => $sales->product_for_punctuation,
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
        'attachment' => $this->read_attachment_by_sale_id((int) $sales->id),
        'status' => $sales->status == "true" ? true : false,
        'slug' => $sales->slug
      ];
    } else {
      return [];
    }
  }

  public function read_by_id(int $id)
  {
    $sql = 'SELECT * FROM `sales` WHERE `id` = ' . $id;
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
        'changePunctuation' => $sales->change_punctuation == "true" ? true : false,
        'productForPunctuation' => $sales->product_for_punctuation,
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
        'attachment' => $this->read_attachment_by_sale_id((int) $sales->id),
        'status' => $sales->status == "true" ? true : false,
        'slug' => $sales->slug
      ];
    } else {
      return [];
    }
  }

  private function read_attachment_by_sale_id(int $sale_id)
  {
    $sql = 'SELECT `file_name`, `file_old_name` FROM `sales_attachments` WHERE `sale_id`=' . $sale_id;
    $attachment = $this->db_read($sql);
    if ($this->db_num_rows($attachment) == 1) {
      $attachment = $this->db_object($attachment);
      return [
        'name' => $attachment->file_name,
        'oldName' => $attachment->file_old_name
      ];
    } else {
      return null;
    }
  }

  public function update(
    int $id,
    int $agency_id,
    int $product_id,
    bool $is_associate,
    bool $is_employee,
    bool $change_punctuation,
    string $product_for_punctuation,
    bool $status,
    string $legal_nature,
    string $value,
    string $description,
    array $associate,
    array $physical_person,
    array $legal_person,
    $attachment = null
  ) {
    $old_sale = $this->read_by_id($id);

    $sql = '
        UPDATE `sales` SET
            `agency_id`=' . $agency_id . ',
            `product_id`=' . $product_id . ',
            `description`="' . $description . '",
            `value`="' . $this->value_formatted_for_save($value) . '",
            `is_associate`="' . ($is_associate ? "true" : "false") . '",
            `is_employee`="' . ($is_employee ? "true" : "false") . '",
            `change_punctuation`="' . ($change_punctuation ? "true" : "false") . '",
            `status`="' . ($status ? "true" : "false") . '",
            `legal_nature`="' . $legal_nature . '",
            `associate_name`="' . ($associate['name'] ? $associate['name'] : "") . '",
            `associate_number_account`="' . ($associate['numberAccount'] ? $associate['numberAccount'] : "") . '",
            `legal_person_social_reason`="' . ($legal_person['socialReason'] ? $legal_person['socialReason'] : "") . '",
            `legal_person_cnpj`="' . ($legal_person['cnpj'] ? $legal_person['cnpj'] : "") . '",
            `physical_person_name`="' . ($physical_person['name'] ? $physical_person['name'] : "") . '",
            `physical_person_cpf`="' . ($physical_person['cpf'] ? $physical_person['cpf'] : "") . '",
            `slug`= "' . $this->slugify($id . '-' . ($associate['name'] != "" ? $associate['name'] : ($physical_person['name'] != "" ? $physical_person['name'] : $legal_person['socialReason']))) . '", `product_for_punctuation`="' . $product_for_punctuation . '" 
        WHERE `id`=' . $id;
    $product_updated = $this->db_update($sql);
    if ($product_updated) {
      if ($attachment != null) {
        $file_name = pathinfo($attachment->name, PATHINFO_FILENAME);
        $file_extension = pathinfo($attachment->name, PATHINFO_EXTENSION);
        $file_extension = strtolower($file_extension);
        $file_name = $id . '-' . $this->slugify($file_name) . '.' . $file_extension;

        $directory = '/../../public/sales/';
        $file_path = $directory . $file_name;

        $file_uploaded = $this->base64_to_file($attachment->content, __DIR__ . $file_path);

        if ($file_uploaded) {
          $sql = 'INSERT INTO `sales_attachments`(`sale_id`, `file_name`, `file_old_name`) VALUES (' . $id . ', "' . $file_name . '", "' . $attachment->name . '")';
          $this->db_update($sql);
        } else {
          $sql = 'DELETE FROM `sales` WHERE `id`=' . $id;
          $this->db_delete($sql);
          return false;
        }
      }

      return [
        'old' => $old_sale,
        'new' => $this->read_by_id($id)
      ];
    } else {
      return false;
    }
  }

  public function delete(string $slug)
  {
    $old_sale = $this->read_by_slug($slug);
    $sql = 'DELETE FROM `sales` WHERE `slug`="' . $slug . '"';
    if ($this->db_delete($sql)) {
      return $old_sale;
    } else {
      return false;
    }
  }

  public function delete_attachment_by_sale_id(int $sale_id)
  {
    $attachment = $this->read_attachment_by_sale_id($sale_id);

    if ($attachment == null) {
      return true;
    }

    if (file_exists(__DIR__ . '/../../public/sales/' . $attachment['name'])) {
      unlink(__DIR__ . '/../../public/sales/' . $attachment['name']);
    }

    $sql = 'DELETE FROM `sales_attachments` WHERE `sale_id` = ' . $sale_id;
    if ($this->db_delete($sql)) {
      return true;
    } else {
      return false;
    }
  }

  private function value_formatted_for_save(string $value)
  {
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);
    return $value;
  }
}
