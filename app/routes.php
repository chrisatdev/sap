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
     * Hello world, is Okay!
     * Verb: GET
     */
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('API');
        return $response;
    });

    /**
     * Save data
     * Verb: POST
     * Params: @table_name //is the table name
     * Body: Json
     * Return: Json data
     */
    $app->post('/{table_name}', function (Request $request, Response $response, array $args) use ($container) {
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $table_name = $args['table_name'];
            $adapter = new Adapter;
            $adapter->table = $table_name;
            $body = $request->getBody()->getContents();
            $result = $adapter->insert( json_decode( $body, true ) );
            $response->getBody()->write( $result );
        }else{
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response->withHeader('Content-Type', 'application/json');
    });

    /**
     * Update data
     * Verb: PUT
     * Params: @table_name //is the table name
     * Body: Json
     * Return: Json data
     */
    $app->put('/{table_name}/{id}', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $id = $args['id'];
            $table_name = $args['table_name'];
            $adapter = new Adapter;
            $adapter->table = $table_name;
            $body = $request->getBody()->getContents();
            $result = $adapter->update( json_decode( $body, true ), $adapter->primary_key() . " = {$id}", $id );
            $response->getBody()->write( $result );
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

    /**
     * Delete a single record
     * Verb: DELETE
     * Params: @table_name //is the table name
     *         @id //is the ID of the record
     * Return: Json data
     */
    $app->delete('/{table_name}/{id}', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $id = $args['id'];
            $table_name = $args['table_name'];
            $adapter = new Adapter;
            $adapter->table = $table_name;
            $delete = $adapter->delete( $adapter->primary_key() . " = {$id}" );
            if( $delete ){
                $result = ['statusCode'=>$statusCode,'id'=>$id];
            }else{
                $statusCode = 400;
                $result = ['statusCode'=>$statusCode,'message'=>'Bad request','error'=>$delete];
            }
            $response->getBody()->write( json_encode( $result, JSON_NUMERIC_CHECK ) );
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

    /**
     * Get a list of records 
     * the table_name is de name of the table on DB
     */
    $app->get('/{table_name}[/page/{page}[/query/{query}]]', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $table_name  = $args['table_name'];
            $adapter = new Pagination;
            $adapter->fields = "*";
            $adapter->table = $table_name;
            
            $page    = isset($args['page']) ? $args['page'] : 1;

            $query   = isset($args['query']) ? $args['query'] : "";
            $where   = isset($args['query']) ? "CONCAT_WS(" . $adapter->columns() .") LIKE '%{$query}%'" : "";
            
            $fetchAll = $adapter->listing([
                "where"     => $where,
                "order"     => "`{$table_name}`.".$adapter->primary_key() . " DESC",
                "page"      => $page
            ]);
            /*
            $prefix = str_replace( "_id", "" , $adapter->primary_key() );
            $tmp = [];
            foreach ($fetchAll['list'] as $key => $value) {
                foreach ($value as $k => $v) {
                    if ($k == "{$prefix}_id") {
                        $tmp[$key]["{$prefix}_id"] = encrypt($v);
                    } else {
                        list( $fkprefix, $n) = explode( "_", $k );
                        if( $prefix !== $fkprefix ){
                            $tmp[$key][$fkprefix][$k] = $v; 
                        } else {
                            $tmp[$key][$k] = $v;
                        }
                    }
                }
            }
            $fetchAll = array_replace($fetchAll, ['list' => $tmp]);
            */
            $response->getBody()->write( json_encode( $fetchAll, JSON_NUMERIC_CHECK ) );
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

    /**
     * Get data of a single record
     * by ID
     */
    $app->get('/{table_name}/{id}', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $table_name = $args['table_name'];
            $id = !empty( $args['id'] ) ? $args['id'] : "";
            $adapter = new Adapter;
            $adapter->table = $table_name;
            $list = $adapter->find( $adapter->primary_key(), $id );
            $response->getBody()->write( json_encode( $list, JSON_NUMERIC_CHECK ) );
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

    /**
     * User Login
     * Verb: POST
     * Params: @table_name //is the table name
     * Body: Json
     * Return: Json data
     */
    $app->post('/{table_name}/login', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $table_name  = $args['table_name'];
            $adapter = new Adapter;
            $adapter->fields = "*";
            $adapter->table = $table_name;
            list( $prefix, $n ) = explode("_", $adapter->primary_key());
            $body = json_decode( $request->getBody()->getContents(), true);
            $username = $body["email"];
            $password = md5($body["password"]);
            $result = $adapter->find( "{$prefix}_email", $username, "{$prefix}_password = '{$password}' AND {$prefix}_status = 1" );
            if( haveRows( $result ) ){
                $adapter->table = "groups";
                $permissions = $adapter->find( "group_id", $result[0]["group_id"], "group_status = 1" );
                $list = [ 
                    "id" => encrypt( $result[0]["user_id"] ), 
                    "name" => $result[0]["{$prefix}_firstname"], 
                    "lastname" => $result[0]["{$prefix}_lastname"],
                    "email" => $result[0]["{$prefix}_email"], 
                    "role" => $result[0]["{$prefix}_type"], 
                    "group" => [ 
                                "id" =>encrypt( $result[0]["group_id"] ), 
                                "permissions" => json_decode( $permissions[0]['group_permissions'] )
                                ] 
                    ];
            }else{
                $statusCode = 404;
                $list = [];
            }
            $response->getBody()->write( json_encode( $list, JSON_NUMERIC_CHECK ) );
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message($statusCode);
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

};
