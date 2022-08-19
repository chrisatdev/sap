<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Classes\db\Adapter as Adapter;
use Classes\listing\Pagination as Pagination;
use Certificate\Credentials;
use Controller\GetConfig as GetConfig;

return function (App $app) {
  $container = $app->getContainer();

  /**
   * User Login
   * Verb: POST
   * Params: @table_name //is the table name
   * Body: Json
   * Return: Json data
   */
  $app->post('/{table_name}/login', function (
    Request $request,
    Response $response,
    array $args
  ) use ($container) {
    $statusCode = 200;
    $auth = new Certificate\Credentials();
    $authorization = $request->getHeader('Authorization');
    $valid = $auth->valid_access_token($authorization);
    if ($valid) {
      $table_name = $args['table_name'];
      $adapter = new Adapter();
      $adapter->fields = "*";
      $adapter->table = $table_name;
      list($prefix, $n) = explode("_", $adapter->primary_key());
      $body = json_decode($request->getBody()->getContents(), true);
      $username = $body["email"];
      $password = md5($body["password"]);

      $result = $adapter->find(
        "{$prefix}_email",
        $username,
        "{$prefix}_password = '{$password}' AND {$prefix}_status = 1"
      );
      if (haveRows($result)) {
        if (array_key_exists('group_id', $result[0])) {
          $adapter->table = "user_groups";
          $permissions = $adapter->find(
            "group_id",
            $result[0]["group_id"],
            "group_status = 1"
          );
          $list = [
            "id" => encrypt($result[0]["{$prefix}_id"]),
            "firstname" => $result[0]["{$prefix}_firstname"],
            "lastname" => $result[0]["{$prefix}_lastname"],
            "email" => $result[0]["{$prefix}_email"],
            "role" => $result[0]["{$prefix}_type"],
            "group" => [
              "id" => $result[0]["group_id"],
              "permissions" => json_decode(
                $permissions[0]['group_permissions']
              ),
            ],
          ];
        } else {
          $list = [
            "id" => $result[0]["{$prefix}_id"],
            "name" => $result[0]["{$prefix}_name"],
            "email" => $result[0]["{$prefix}_email"],
            "role" => $result[0]["{$prefix}_type"],
          ];
        }
      } else {
        $statusCode = 404;
        $list = [];
      }
      $response->getBody()->write(json_encode($list, JSON_NUMERIC_CHECK));
    } else {
      $statusCode = 401;
      $error = $auth->bad_credentials_message($statusCode);
      $response->getBody()->write(json_encode($error));
    }
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus($statusCode);
  });
};
