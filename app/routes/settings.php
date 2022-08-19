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
     * Get data of query params
     * Verb: GET
     * Params: @table_name //is the table name
     * Body: Json
     * Return: Json data
     */
    $app->get('/from/{table_name}/get/data', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        $where =  "";
        if( $valid ){
            $table_name = $args['table_name'];

            $adapter    = new Adapter;
            $adapter->fields = "*";
            $adapter->table = $table_name;
            $order = "";
            list( $prefix, $n ) = explode( '_', $adapter->primary_key() );
            $queryParams = $request->getQueryParams();
            if( is_array( $queryParams ) ){
                $removeAnd = false;
                foreach( $queryParams as $k => $val ){
                    if( $k == 'query' ){
                        $where  = isset($val) ? "CONCAT_WS(" . $adapter->columns() .") LIKE '%{$val}%'" : "";
                    }
                    if( $k == 'order' ){
                        switch( $val ){
                            case 'asc':
                                $order = "`{$table_name}`." .  $adapter->primary_key() . " ASC";
                            break;
                            case 'desc':
                                $order = "`{$table_name}`." .  $adapter->primary_key() . " DESC";
                            break;
                        }
                    }
                    if( $k !== 'query' && $k !== 'order' ){
                      $removeAnd = true;
                      $where .= isset($val) ? "`{$table_name}`.{$k} = '{$val}' AND " : "";
                    }
                }
                if( $removeAnd ){
                  $where = !empty( $where ) ? substr($where, 0, -4) : "";
                }
            }

            $fetchAll = $adapter->select([
                "where"     => $where,
                "order"     => $order
            ]);

            if( is_array( $fetchAll ) ){
                $response->getBody()->write( json_encode( $fetchAll, JSON_NUMERIC_CHECK ) );
            }else{
                $response->getBody()->write( $fetchAll );
            }
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
    $app->get('/credentials/{settings}/set_credential', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        switch( $args['settings'] ){
            case 'settings':
                $r = $auth->setting_credentials();
                if( !is_array( $r ) && $r > 0 ){
                    $r = $auth->get_credentials( $r );
                    $public_key = $r[0]['credential_public_key'];
                    $private_key = $r[0]['credential_private_key'];
                    $result['public_key'] = $public_key;
                    $result['private_key'] = $private_key;
                    $result['access_token'] = base64_encode("{$public_key}:{$private_key}");
                    $result['expired_time'] = $r[0]['credential_expired'];
                }else{
                    $statusCode = 400;
                    $result = $auth->bad_credentials_message($statusCode,'The settings for the credentials could not be created or are already created.');
                }
            break;
        }

        $response->getBody()->write( json_encode($result, JSON_NUMERIC_CHECK) );

        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });

    /**
     * Get data with fields foreign key
     * Verb: GET
     * Params: @module //is the table name
     *         @reference //is the field reference to foireign key 
     * Return: Json data
     */
    $app->get('/foreign/{module}/{reference}', function (Request $request, Response $response, array $args) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials;
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token( $authorization );
        if( $valid ){
            $adapter = new Adapter;
            $adapter->table = $args['module'];
            
            $fieldsDetails = null;
            $queryParams = $request->getQueryParams();
            if( is_array( $queryParams ) ){
                foreach( $queryParams as $k => $val ){
                    if( $k == 'fields' ){
                        $fieldsDetails = $val;
                    }                    
                }
            }
            
            $fetchAll = $adapter->foreign_key_values($args['reference'], $fieldsDetails);

            if( !is_array( $fetchAll ) ){
                $response->getBody()->write( $fetchAll );    
            }else{
                $response->getBody()->write( json_encode( $fetchAll, JSON_NUMERIC_CHECK ) );
            }
        }else{
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write( json_encode( $error ) );
        }
        return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus($statusCode);
    });
    
};
