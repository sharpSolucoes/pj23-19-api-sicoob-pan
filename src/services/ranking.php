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
    $initial_date = date('Y-m-d 00:00:00', strtotime($initial_date));
    $final_date = date('Y-m-d 23:59:59', strtotime($final_date));

    $user = $this->users->read_by_id($user_id);
    $order = '';
    if ($sorting !== null && $is_desc !== null) {
      $order = 'ORDER BY ' . ($sorting === "points" ? '`total_points`' : 'U.`name`') . ' ' . ($is_desc ? 'DESC' : 'ASC');
    }

    if ($limit) {
      $order = 'ORDER BY `total_points` DESC';
    }

    //     if ($user->position == "Administrador" || $user->position == "Suporte") {
    //         $sql = '
    //     SELECT U.`name`, U.`slug`,
    //         SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
    //     FROM `users` U
    //     LEFT JOIN (
    //         SELECT S.`user_id`, SUM((P.`min_quantity`) * P.`points`) AS `points_for_quantity`
    //         FROM `sales` S
    //         INNER JOIN `products` P ON 
    //         (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`
    //     ) AS sub1 ON U.`id` = sub1.`user_id`
    //     LEFT JOIN (
    //         SELECT 
    //             S.`user_id`, 
    //             SUM(CASE
    //                 WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
    //                     P.`points`
    //                 ELSE
    //                     CASE
    //                         WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
    //                           (`value` / P.`min_value`) * P.`points`
    //                         ELSE
    //                             0
    //                     END
    //             END) AS `points_for_value`
    //         FROM 
    //             `sales` S
    //         INNER JOIN 
    //             `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE 
    //             P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`
    //     ) AS sub2 ON U.`id` = sub2.`user_id`
    //     LEFT JOIN (
    //         SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //     ) AS idea1 ON U.`id` = idea1.`user_id` 
    //     LEFT JOIN (
    //         SELECT
    //             `user_id`,
    //             SUM(`punctuation`) AS `points`
    //         FROM `extra_score` ES
    //         INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
    //         WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY `user_id`
    //     ) AS extra_score ON U.`id` = extra_score.`user_id`
    //     WHERE U.`position` <> "Suporte"
    //     GROUP BY U.`id`
    //     ' . $order . '
    //     ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
    //   ';
    //     } else if ($user->position == "Gestor") {
    //         $sql = '
    //     SELECT U.`name`, U.`slug`,
    //         SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
    //     FROM `users` U
    //     LEFT JOIN (
    //         SELECT S.`user_id`, SUM((P.`min_quantity`) * P.`points`) AS `points_for_quantity`
    //         FROM `sales` S
    //         INNER JOIN `products` P ON 
    //         (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`

    //     ) AS sub1 ON U.`id` = sub1.`user_id`
    //     LEFT JOIN (
    //         SELECT 
    //             S.`user_id`, 
    //             SUM(CASE
    //                 WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
    //                     P.`points`
    //                 ELSE
    //                     CASE
    //                         WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
    //                           (`value` / P.`min_value`) * P.`points`
    //                         ELSE
    //                             0
    //                     END
    //             END) AS `points_for_value`
    //         FROM 
    //             `sales` S
    //         INNER JOIN 
    //             `products` P ON  (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE 
    //             P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`
    //     ) AS sub2 ON U.`id` = sub2.`user_id`
    //     LEFT JOIN (
    //         SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //     ) AS idea1 ON U.`id` = idea1.`user_id` 
    //     LEFT JOIN (
    //         SELECT
    //             `user_id`,
    //             SUM(`punctuation`) AS `points`
    //         FROM `extra_score` ES
    //         INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
    //         WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY `user_id`
    //     ) AS extra_score ON U.`id` = extra_score.`user_id`
    //     WHERE U.`agency_id` = ' . $user->agency_id . ' AND U.`position` <> "Suporte"
    //     GROUP BY U.`id`
    //     ' . $order . '
    //     ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
    //   ';
    //     } else if (isset($user->teams) && count($user->teams) > 0) {
    //         $sql = '
    //     SELECT U.`name`, U.`slug`,
    //         SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
    //     FROM `users` U
    //     LEFT JOIN (
    //         SELECT S.`user_id`, SUM((P.`min_quantity`) * P.`points`) AS `points_for_quantity`
    //         FROM `sales` S
    //         INNER JOIN `products` P ON 
    //         (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`

    //     ) AS sub1 ON U.`id` = sub1.`user_id`
    //     LEFT JOIN (
    //         SELECT 
    //             S.`user_id`, 
    //             SUM(CASE
    //                 WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
    //                     P.`points`
    //                 ELSE
    //                     CASE
    //                         WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
    //                           (`value` / P.`min_value`) * P.`points`
    //                         ELSE
    //                             0
    //                     END
    //             END) AS `points_for_value`
    //         FROM 
    //             `sales` S
    //         INNER JOIN 
    //             `products` P ON  (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
    //         OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //         WHERE 
    //             P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY S.`user_id`
    //     ) AS sub2 ON U.`id` = sub2.`user_id`
    //     LEFT JOIN (
    //         SELECT COUNT(*) AS `total_ideas`, `user_id` FROM `ideas` I WHERE I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //     ) AS idea1 ON U.`id` = idea1.`user_id` 
    //     LEFT JOIN (
    //         SELECT
    //             `user_id`,
    //             SUM(`punctuation`) AS `points`
    //         FROM `extra_score` ES
    //         INNER JOIN `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
    //         WHERE ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //         GROUP BY `user_id`
    //     ) AS extra_score ON U.`id` = extra_score.`user_id`
    //     WHERE U.`position` <> "Suporte" AND U.`id` IN (SELECT `user_id` FROM `teams_users` WHERE `team_id` IN (' . implode(', ', $user->teams) . '))
    //     GROUP BY U.`id`
    //     ' . $order . '
    //     ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
    //   ';
    //     } else {
    //         $sql = 'SELECT `team_id` FROM `teams_users` WHERE `user_id` = ' . $user->id . ' LIMIT 1';
    //         $teams = $this->db_read($sql);
    //         $teams = $this->db_object($teams);
    //         if ($teams === null) {
    //             return [];
    //         }
    //         $sql = '
    //             SELECT 
    //                 U.`name`, 
    //                 U.`slug`,
    //                 SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
    //             FROM 
    //                 `users` U
    //             LEFT JOIN (
    //                 SELECT 
    //                     S.`user_id`, 
    //                     SUM(
    //                         CASE 
    //                             WHEN P.`is_quantity` = "true" THEN P.`points`
    //                             ELSE (P.`min_quantity` * P.`points`)
    //                         END
    //                     ) AS `points_for_quantity`
    //                 FROM 
    //                     `sales` S
    //                 INNER JOIN 
    //                     `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //                 WHERE 
    //                     P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //                 GROUP BY 
    //                     S.`user_id`
    //             ) AS sub1 ON U.`id` = sub1.`user_id`
    //             LEFT JOIN (
    //                 SELECT 
    //                     S.`user_id`, 
    //                     SUM(CASE
    //                         WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
    //                             P.`points`
    //                         ELSE
    //                             CASE
    //                                 WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
    //                                   (`value` / P.`min_value`) * P.`points`
    //                                 ELSE
    //                                     0
    //                             END
    //                     END) AS `points_for_value`
    //                 FROM 
    //                     `sales` S
    //                 INNER JOIN 
    //                     `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
    //                 WHERE 
    //                     P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //                 GROUP BY 
    //                     S.`user_id`
    //             ) AS sub2 ON U.`id` = sub2.`user_id`
    //             LEFT JOIN (
    //                 SELECT 
    //                     COUNT(*) AS `total_ideas`, 
    //                     `user_id` 
    //                 FROM 
    //                     `ideas` I 
    //                 WHERE 
    //                     I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //             ) AS idea1 ON U.`id` = idea1.`user_id` 
    //             LEFT JOIN (
    //                 SELECT
    //                     `user_id`,
    //                     SUM(`punctuation`) AS `points`
    //                 FROM 
    //                     `extra_score` ES
    //                 INNER JOIN 
    //                     `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
    //                 WHERE 
    //                     ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
    //                 GROUP BY 
    //                     `user_id`
    //             ) AS extra_score ON U.`id` = extra_score.`user_id`
    //             -- INNER JOIN 
    //             --     `teams_users` TU ON U.`id` = TU.`user_id`
    //             -- INNER JOIN 
    //             --     `teams` T ON TU.`team_id` = T.`id`
    //             WHERE 
    //                 U.`position` <> "Suporte" AND U.`id` IN (SELECT `user_id` FROM `teams_users` WHERE `team_id` = ' . $teams->team_id . ')
    //             GROUP BY 
    //                 U.`id`
    //             ' . $order . '
    //             ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
    //         ';
    //     }

    if ($user->position == "Administrador" || $user->position == "Suporte") {
      $sql = '
        SELECT U.`name`, U.`slug`,
            SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
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
                SUM(CASE
                    WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
                        P.`points`
                    ELSE
                        CASE
                            WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
                            (`value` / P.`min_value`) * P.`points`
                            ELSE
                                0
                        END
                END) AS `points_for_value`
            FROM 
                `sales` S
            INNER JOIN 
                `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
            OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
            WHERE 
                P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
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
        WHERE U.`position` <> "Suporte"
        GROUP BY U.`id`
        ' . $order . '
        ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
      ';
    } else {
      if ($user->position == "Usuário") {
        $sql = '
          SELECT 
              U.`name`, 
              U.`slug`,
              SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
          FROM 
              `users` U
          LEFT JOIN (
              SELECT 
                  S.`user_id`, 
                  SUM(
                      CASE 
                          WHEN P.`is_quantity` = "true" THEN P.`points`
                          ELSE (P.`min_quantity` * P.`points`)
                      END
                  ) AS `points_for_quantity`
              FROM 
                  `sales` S
              INNER JOIN 
                  `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
              WHERE 
                  P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  S.`user_id`
          ) AS sub1 ON U.`id` = sub1.`user_id`
          LEFT JOIN (
              SELECT 
                  S.`user_id`, 
                  SUM(CASE
                      WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
                          P.`points`
                      ELSE
                          CASE
                              WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
                                (`value` / P.`min_value`) * P.`points`
                              ELSE
                                  0
                          END
                  END) AS `points_for_value`
              FROM 
                  `sales` S
              INNER JOIN 
                  `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
              WHERE 
                  P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  S.`user_id`
          ) AS sub2 ON U.`id` = sub2.`user_id`
          LEFT JOIN (
              SELECT 
                  COUNT(*) AS `total_ideas`, 
                  `user_id` 
              FROM 
                  `ideas` I 
              WHERE 
                  I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
          ) AS idea1 ON U.`id` = idea1.`user_id` 
          LEFT JOIN (
              SELECT
                  `user_id`,
                  SUM(`punctuation`) AS `points`
              FROM 
                  `extra_score` ES
              INNER JOIN 
                  `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
              WHERE 
                  ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  `user_id`
          ) AS extra_score ON U.`id` = extra_score.`user_id`
          WHERE 
              U.`position` <> "Suporte" AND U.`id` = ' . $user->id . '
          GROUP BY 
              U.`id`
          ' . $order . '
          ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
        ';
      } else {
        $users_id = [];
        $sql = '
          SELECT 
            TU.`user_id`
          FROM 
            `teams` T
          INNER JOIN 
            `teams_users` TU ON T.`id` = TU.`team_id` AND TU.`user_id` <> ' . $user_id . '
          WHERE
            T.`team_manager` = ' . $user_id . '
        ';
        $users = $this->db_read($sql);
        if ($this->db_num_rows($users) > 0) {
          while ($user = $this->db_object($users)) {
            $users_id[] = (int) $user->user_id;
          }
        }

        $sql = '
          SELECT 
            TU.`user_id`
          FROM 
            `teams` T
          INNER JOIN 
            `teams_users` TU ON T.`id` = TU.`team_id` AND TU.`user_id` <> ' . $user_id . '
          WHERE
            T.`accountable` = ' . $user_id . '
        ';
        $users = $this->db_read($sql);
        if ($this->db_num_rows($users) > 0) {
          while ($user = $this->db_object($users)) {
            $users_id[] = (int) $user->user_id;
          }
        }

        array_push($users_id, $user_id);
        // return $users_id;
        $sql = '
          SELECT U.`name`, U.`slug`,
              SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
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
                  SUM(CASE
                      WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
                          P.`points`
                      ELSE
                          CASE
                              WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
                          (`value` / P.`min_value`) * P.`points`
                              ELSE
                                  0
                          END
                  END) AS `points_for_value`
              FROM 
                  `sales` S
              INNER JOIN 
                  `products` P ON  (S.`product_id` = P.`id` AND S.`change_punctuation` = "false")
              OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
              WHERE 
                  P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY S.`user_id`
          ) AS sub2 ON U.`id` = sub2.`user_id
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
          WHERE U.`id` IN (' . implode(', ', $users_id) . ')
          GROUP BY U.`id`
          ' . $order . '
          ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
        ';
        $sql = '
          SELECT 
              U.`name`, 
              U.`slug`,
              SUM(COALESCE(sub1.`points_for_quantity`, 0)) + SUM(COALESCE(sub2.`points_for_value`, 0)) + SUM(COALESCE(idea1.`total_ideas`, 0)) + SUM(COALESCE(extra_score.`points`, 0)) AS `total_points`
          FROM 
              `users` U
          LEFT JOIN (
              SELECT 
                  S.`user_id`, 
                  SUM(
                      CASE 
                          WHEN P.`is_quantity` = "true" THEN P.`points`
                          ELSE (P.`min_quantity` * P.`points`)
                      END
                  ) AS `points_for_quantity`
              FROM 
                  `sales` S
              INNER JOIN 
                  `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
              WHERE 
                  P.`is_quantity` = "true" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  S.`user_id`
          ) AS sub1 ON U.`id` = sub1.`user_id`
          LEFT JOIN (
              SELECT 
                  S.`user_id`, 
                  SUM(CASE
                      WHEN P.`is_quantity` = "false" AND S.`value` >= P.`min_value` THEN 
                          P.`points`
                      ELSE
                          CASE
                              WHEN P.`is_quantity` = "false" AND P.`is_accumulated` = "true" THEN
                                (`value` / P.`min_value`) * P.`points`
                              ELSE
                                  0
                          END
                  END) AS `points_for_value`
              FROM 
                  `sales` S
              INNER JOIN 
                  `products` P ON (S.`product_id` = P.`id` AND S.`change_punctuation` = "false") OR (S.`product_for_punctuation` = P.`id` AND S.`change_punctuation` = "true")
              WHERE 
                  P.`is_quantity` = "false" AND S.`status` = "true" AND S.`date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  S.`user_id`
          ) AS sub2 ON U.`id` = sub2.`user_id`
          LEFT JOIN (
              SELECT 
                  COUNT(*) AS `total_ideas`, 
                  `user_id` 
              FROM 
                  `ideas` I 
              WHERE 
                  I.`status` = "Validada" AND I.`opening_date` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
          ) AS idea1 ON U.`id` = idea1.`user_id` 
          LEFT JOIN (
              SELECT
                  `user_id`,
                  SUM(`punctuation`) AS `points`
              FROM 
                  `extra_score` ES
              INNER JOIN 
                  `extra_score_users` ESU ON ES.`id` = ESU.`extra_score_id`
              WHERE 
                  ES.`created_at` BETWEEN "' . $initial_date . '" AND "' . $final_date . '"
              GROUP BY 
                  `user_id`
          ) AS extra_score ON U.`id` = extra_score.`user_id`
          WHERE 
              U.`position` <> "Suporte" AND U.`id` IN (' . implode(', ', $users_id) . ')
          GROUP BY 
              U.`id`
          ' . $order . '
          ' . ($limit !== null ? 'LIMIT ' . $limit : '') . ';
        ';
      }
    }

    $get_ranking = $this->db_read($sql);
    if ($get_ranking) {
      $response = [];
      $points = 0;
      while ($ranking = $this->db_object($get_ranking)) {
        $points += $ranking->total_points;
        $response[] = [
          'name' => mb_convert_case($ranking->name, MB_CASE_TITLE, 'UTF-8'),
          'points' => number_format((float) $ranking->total_points, 2, ',', '.'),
          'slug' => $ranking->slug
        ];
      }

      if ($limit !== null) {
        array_push($response, [
          'name' => 'Total',
          'points' => number_format((float) $points, 2, ',', '.'),
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
      $sql = 'SELECT `id` FROM `teams` WHERE `team_manager` = ' . $user . ' LIMIT 1;';
      $team = $this->db_read($sql);

      if ($this->db_num_rows($team) > 0) {
        return [
          'isAccountable' => true
        ];
      }
      return [
        'isAccountable' => false
      ];
    }
  }
}
