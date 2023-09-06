<?php
if (isset($_GET['url'])) {
    $api = new API_configuration;
    $api->token = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : "");
    $user = $api->authorization();

    if ($url[0] == 'me') {
        require_once 'services/me.php';
        $authorization = $api->authorization("api");
        $me = new Me;
        if (isset($url[1]) && $url[1] == 'login') {
            $response = $me->login(
                addslashes($request->email),
                addslashes($request->password)
            );
            if ($authorization && $response) {
                $api->generate_user_log(
                    $response['user']['id'],
                    'login'
                );
                echo json_encode($response);
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid email or password']);
            }
        } else if (isset($url[1]) && $url[1] == 'session') {
            $response = $me->session(addslashes($headers['email']));
            if ($authorization && $response) {
                echo json_encode($response);
            } else {
                http_response_code(401);
            }
        } else if (isset($url[1]) && $url[1] == 'logout') {
            if ($authorization) {
                $response = $me->logout(addslashes($headers['token']));
                if ($response) {
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else {
                http_response_code(401);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid URL']);
        }
    } else if ($user) {
        if ($url[0] == 'users') {
            require_once 'services/users.php';
            $users = new Users;
            if (!isset($url[1])) {
                $users->user_id = $user;
                $response = $users->read();
                if ($response || $response == []) {
                    $api->generate_user_log(
                        $api->user_id,
                        'users.read'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                }
            } else if ($url[1] == 'create') {
                $response = $users->create(
                    (int) $request->agencyId,
                    (int) $request->teamId,
                    addslashes($request->name),
                    addslashes($request->email),
                    addslashes($request->position),
                    addslashes($request->passwordConfirmation),
                    addslashes($request->status),
                    (array) $request->permissions
                );
                if ($response) {
                    http_response_code(201);
                    $api->generate_user_log(
                        $api->user_id,
                        'users.create',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'User created']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'update') {
                $response = $users->update(
                    addslashes($request->id),
                    addslashes($request->name),
                    (int) $request->agencyId,
                    (int) $request->teamId,
                    addslashes($request->email),
                    addslashes($request->position),
                    $request->changePassword,
                    addslashes($request->passwordConfirmation),
                    addslashes($request->status),
                    (array) $request->permissions
                );
                if ($response) {
                    http_response_code(200);
                    $api->generate_user_log(
                        $api->user_id,
                        'users.update',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'User updated']);
                } else {
                    http_response_code(400);
                }

            } else if ($url[1] == 'delete') {
                $response = $users->delete(addslashes($url[2]));
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'users.delete',
                        json_encode($response)
                    );
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'logs') {
                $response = $users->read_logs(
                    (isset($request->action) ? addslashes($request->action) : null),
                    (isset($request->initialDate  ) ? addslashes($request->initialDate) : null),
                    (isset($request->finalDate ) ? addslashes($request->finalDate ) : null),
                    (isset($request->userId) ? addslashes($request->userId) : null),
                );
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'users.logs'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(400);
                }
            } else {
                $response = $users->read_user_by_slug(addslashes($url[1]));
                if ($response) {
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'User not found or invalid URL']);
                }
            }
        } else if ($url[0] == 'agencies') {
            require_once 'services/agencies.php';
            $agencies = new Agencies;

            if (!isset($url[1])) {
                $response = $agencies->read();
                if ($response || $response == []) {
                    $api->generate_user_log(
                        $api->user_id,
                        'agencies.read'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                }
            } else if ($url[1] == 'create') {
                $response = $agencies->create(
                    addslashes($request->number),
                    addslashes($request->name)
                );
                if ($response) {
                    http_response_code(201);
                    $api->generate_user_log(
                        $api->user_id,
                        'agencies.create',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Agency created']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'update') {
                $response = $agencies->update(
                    addslashes($request->id),
                    addslashes($request->number),
                    addslashes($request->name),
                    addslashes($request->status)
                );
                if ($response) {
                    http_response_code(200);
                    $api->generate_user_log(
                        $api->user_id,
                        'agencies.update',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Agency updated']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'delete') {
                $response = $agencies->delete(addslashes($url[2]));
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'agencies.delete',
                        json_encode($response)
                    );
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else {
                $response = $agencies->read_by_slug(addslashes($url[1]));
                if ($response) {
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Agency not found or invalid URL']);
                }
            }
        } else if ($url[0] == 'products') {
            require_once 'services/products.php';
            $products = new Products;

            if (!isset($url[1])) {
                $response = $products->read(
                    $_GET['status'] ?? null
                );
                if ($response || $response == []) {
                    $api->generate_user_log(
                        $api->user_id,
                        'products.read'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                }
            } else if ($url[1] == 'create') {
                $response = $products->create(
                    addslashes($request->description),
                    addslashes($request->status)
                );
                if ($response) {
                    http_response_code(201);
                    $api->generate_user_log(
                        $api->user_id,
                        'products.create',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Product created']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'update') {
                $response = $products->update(
                    (int) $request->id,
                    addslashes($request->description),
                    addslashes($request->status)
                );
                if ($response) {
                    http_response_code(200);
                    $api->generate_user_log(
                        $api->user_id,
                        'products.update',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Product updated']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'delete') {
                $response = $products->delete(addslashes($url[2]));
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'products.delete',
                        json_encode($response)
                    );
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else {
                $response = $products->read_by_slug(addslashes($url[1]));
                if ($response) {
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Product not found or invalid URL']);
                }
            }
        } else if ($url[0] == 'teams') {
            require_once 'services/teams.php';
            $teams = new Teams;

            if (!isset($url[1])) {
                $response = $teams->read();
                if ($response || $response == []) {
                    $api->generate_user_log(
                        $api->user_id,
                        'teams.read'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                }
            } else if ($url[1] == 'create') {
                $response = $teams->create(
                    addslashes($request->name),
                    (array) $request->products
                );
                if ($response) {
                    http_response_code(201);
                    $api->generate_user_log(
                        $api->user_id,
                        'teams.create',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Product created']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'update') {
                $response = $teams->update(
                    (int) $request->id,
                    addslashes($request->name),
                    (array) $request->products
                );
                if ($response) {
                    http_response_code(200);
                    $api->generate_user_log(
                        $api->user_id,
                        'teams.update',
                        json_encode($response)
                    );
                    json_encode($response);
                    echo json_encode(['message' => 'Product updated']);
                } else {
                    json_encode($response);
                    http_response_code(400);
                }
            } else if ($url[1] == 'delete') {
                $response = $teams->delete(addslashes($url[2]));
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'teams.delete',
                        json_encode($response)
                    );
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else {
                $response = $teams->read_by_slug(addslashes($url[1]));
                if ($response) {
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Product not found or invalid URL']);
                }
            }
        } else if ($url[0] == 'ideas') {
            require_once 'services/ideas.php';
            $ideas = new ideas;

            if (!isset($url[1])) {
                $response = $ideas->read(
                    $user
                );
                if ($response || $response == []) {
                    $api->generate_user_log(
                        $api->user_id,
                        'ideas.read'
                    );
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                }
            } else if ($url[1] == 'create') {
                $response = $ideas->create(
                    $user,
                    (int) $request->agency,
                    (bool) $request->urgent,
                    addslashes($request->description)
                );
                if ($response) {
                    http_response_code(201);
                    $api->generate_user_log(
                        $api->user_id,
                        'ideas.create',
                        json_encode($response)
                    );
                    echo json_encode(['message' => 'Idea created']);
                } else {
                    http_response_code(400);
                }
            } else if ($url[1] == 'update') {
                $response = $ideas->update(
                    (int) $request->id,
                    (int) $request->agency,
                    addslashes($request->description),
                    addslashes($request->status),
                    (bool) $request->urgent
                );
                if ($response) {
                    http_response_code(200);
                    $api->generate_user_log(
                        $api->user_id,
                        'ideas.update',
                        json_encode($response)
                    );
                    json_encode($response);
                    echo json_encode(['message' => 'Idea updated']);
                } else {
                    json_encode($response);
                    http_response_code(400);
                }
            } else if ($url[1] == 'delete') {
                $response = $ideas->delete(addslashes($url[2]));
                if ($response) {
                    $api->generate_user_log(
                        $api->user_id,
                        'ideas.delete',
                        json_encode($response)
                    );
                    http_response_code(204);
                } else {
                    http_response_code(400);
                }
            } else {
                $response = $ideas->read_by_slug(addslashes($url[1]));
                if ($response) {
                    http_response_code(200);
                    echo json_encode($response);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Idea not found or invalid URL']);
                }
            }
        }
    } else {
        http_response_code(401);
        echo json_encode(['message' => 'User unauthorized']);
    }
} else {
    echo json_encode([
        'message' => 'Server running',
        'version' => VERSION
    ]);
}