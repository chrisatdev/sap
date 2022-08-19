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
     * Get a list products top
     * the table_name is de name of the table on DB
     */
    $app->get('/sp/products/list/location/{location}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials();
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token($authorization);
        if ($valid) {
            $adapter = new Adapter();
            $adapter->table = 'products';
            switch ($args['location']) {
                case 'offer':
                    $where = 'product_bid = 1 AND product_status = 1';
                    $order = 'product_id DESC';
                    break;
                case 'featured':
                    $where = 'product_featured = 1 AND product_status = 1';
                    $order = 'product_id DESC';
                    break;
                case 'new':
                    $month = date('m');
                    $where = "product_status = 1 AND MONTH( product_createat ) = {$month}";
                    $order = 'product_id DESC';
                    break;
                case 'category':
                    $where = 'products.category_id = 6 AND product_status = 1';
                    $order = 'product_id DESC';
                    break;
                default:
                    $where = 'product_status = 1';
                    $order = 'product_id DESC';
                    break;
            }

            $listProducts = [];
            $fetchAll = $adapter->select([
                'where' => $where,
                'order' => $order,
                'limit' => 20,
            ]);

            $c = 0;
            if (haveRows($fetchAll)) {
                $listProducts = $fetchAll;
                foreach ($fetchAll as $p) {
                    $adapter->table = 'product_prices';
                    $fetchAllPrices = $adapter->find(
                        'product_prices.product_id',
                        $p['product_id'],
                        'product_price_status=1'
                    );
                    $listProducts[$c]['prices'] = $fetchAllPrices;

                    $adapter->table = 'product_installments';
                    $fetchAllInstallments = $adapter->find(
                        'product_installments.product_id',
                        $p['product_id'],
                        'product_installment_status=1'
                    );
                    $listProducts[$c]['installments'] = $fetchAllInstallments;

                    $adapter->table = 'product_images';
                    $fetchAllImages = $adapter->find(
                        'product_images.product_id',
                        $p['product_id'],
                        'product_image_status=1'
                    );
                    $listProducts[$c]['images'] = $fetchAllImages;

                    $c++;
                }
            }

            $response
                ->getBody()
                ->write(json_encode($listProducts, JSON_NUMERIC_CHECK));
        } else {
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write(json_encode($error));
        }
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    });

    /**
     * Get a product detail
     * the table_name is de name of the table on DB
     */
    $app->get('/sp/products/detail/by_id/{product_id}', function (
        Request $request,
        Response $response,
        array $args
    ) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials();
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token($authorization);
        if ($valid) {
            $adapter = new Adapter();
            $adapter->table = 'products';
            $product_id = $args['product_id'];
            $where = "product_id = $product_id";
            $listProducts = [];
            $fetchAll = $adapter->select([
                'where' => $where,
                'limit' => 1,
            ]);

            $c = 0;
            if (haveRows($fetchAll)) {
                $listProducts = $fetchAll;
                foreach ($fetchAll as $p) {
                    $adapter->table = 'product_prices';
                    $fetchAllPrices = $adapter->find(
                        'product_prices.product_id',
                        $p['product_id'],
                        'product_price_status=1'
                    );
                    $listProducts[$c]['prices'] = $fetchAllPrices;

                    $adapter->table = 'product_installments';
                    $fetchAllInstallments = $adapter->find(
                        'product_installments.product_id',
                        $p['product_id'],
                        'product_installment_status=1'
                    );
                    $listProducts[$c]['installments'] = $fetchAllInstallments;

                    $adapter->table = 'product_images';
                    $fetchAllImages = $adapter->find(
                        'product_images.product_id',
                        $p['product_id'],
                        'product_image_status=1'
                    );
                    $listProducts[$c]['images'] = $fetchAllImages;

                    $c++;
                }
            }

            $response
                ->getBody()
                ->write(json_encode($listProducts, JSON_NUMERIC_CHECK));
        } else {
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write(json_encode($error));
        }
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    });

    /**
     * Get filter
     * the table_name is de name of the table on DB
     */
    $app->get('/shopProducts/sidebar/filter/list', function (
        Request $request,
        Response $response,
        array $args
    ) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials();
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token($authorization);
        if ($valid) {
            $queryParams = $request->getQueryParams();
            $category_id = array_key_exists('category_id', $queryParams)
                ? $queryParams['category_id']
                : 0;
            $filterList = [];
            $adapter = new Adapter();
            $subAdapter = new Adapter();
            $subAdapter->table = 'products';
            // Categories
            $adapter->table = 'categories';
            $where = $category_id > 0 ? " AND category_id={$category_id}" : '';
            $parentCategories = $adapter->select([
                'where' =>
                    'category_status=1 AND category_parent_id=0' . $where,
            ]);

            if (haveRows($parentCategories)) {
                $index = 0;
                foreach ($parentCategories as $rs) {
                    $totalProducts = 0;
                    $filterList['categories'][$index]['id'] =
                        $rs['category_id'];
                    $filterList['categories'][$index]['name'] =
                        $rs['category_name'];
                    $filterList['categories'][$index]['slug'] =
                        $rs['category_id'] . '-' . slugit($rs['category_name']);

                    $productsCat = $subAdapter->find(
                        'category_id',
                        $rs['category_id']
                    );
                    // Sub Categories
                    $subCategories = $adapter->select([
                        'where' => "category_status=1 AND category_parent_id={$rs['category_id']}",
                    ]);
                    if (haveRows($subCategories)) {
                        $subIndex = 0;
                        foreach ($subCategories as $sc) {
                            $filterList['categories'][$index]['childrens'][
                                $subIndex
                            ]['id'] = $sc['category_id'];
                            $filterList['categories'][$index]['childrens'][
                                $subIndex
                            ]['name'] = $sc['category_name'];
                            $filterList['categories'][$index]['childrens'][
                                $subIndex
                            ]['slug'] =
                                $sc['category_id'] .
                                '-' .
                                slugit($sc['category_name']);
                            $subCatProducts = $subAdapter->find(
                                'category_id',
                                $sc['category_id']
                            );
                            $filterList['categories'][$index]['childrens'][
                                $subIndex
                            ]['totalProducts'] = count($subCatProducts);
                            $totalProducts += count($subCatProducts);
                            $subIndex++;
                        }
                    }
                    // end Sub Categories
                    $filterList['categories'][$index]['totalProducts'] =
                        $totalProducts + count($productsCat);
                    $index++;
                }
            }
            // end Categories
            $adapter->table = 'products';
            $AllProducts = $adapter->select([
                'where' => 'product_status=1',
            ]);
            $filterList['allCategoryProducts'] = haveRows($AllProducts)
                ? count($AllProducts)
                : 0;

            // Brands
            $table = $category_id > 0 ? 'products' : 'brands';
            $group = $category_id > 0 ? 'products.brand_id' : '';
            $where =
                $category_id > 0
                    ? "products.category_id={$category_id}"
                    : 'brand_status=1';
            $adapter->table = $table;
            $brands = $adapter->select([
                'group' => $group,
                'where' => $where,
            ]);
            if (haveRows($brands)) {
                $index = 0;
                foreach ($brands as $rs) {
                    $filterList['brands'][$index]['id'] = $rs['brand_id'];
                    $filterList['brands'][$index]['name'] = $rs['brand_name'];
                    $filterList['brands'][$index]['slug'] =
                        $rs['brand_id'] . '-' . slugit($rs['brand_name']);
                    $index++;
                }
            }
            // end Brands

            // Product price
            $where =
                $category_id > 0
                    ? "product_status=1 AND products.category_id={$category_id}"
                    : 'product_status=1';
            $products = $subAdapter->select([
                'where' => $where,
                'order' => 'product_price ASC',
            ]);
            $filterList['price'][0]['min'] = $products[0]['product_price'];
            $filterList['price'][0]['min_format'] = miles(
                $products[0]['product_price']
            );
            $products = $subAdapter->select([
                'where' => $where,
                'order' => 'product_price DESC',
            ]);
            $filterList['price'][0]['max'] = $products[0]['product_price'];
            $filterList['price'][0]['max_format'] = miles(
                $products[0]['product_price']
            );
            // end product price

            $response->getBody()->write(json_encode($filterList));
        } else {
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write(json_encode($error));
        }
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    });

    /**
     * Get a list of records
     * the table_name is de name of the table on DB
     */
    $app->get('/custom/filter/products/list[/page/{page}]', function (
        Request $request,
        Response $response,
        array $args
    ) use ($container) {
        $statusCode = 200;
        $auth = new Certificate\Credentials();
        $authorization = $request->getHeader('Authorization');
        $valid = $auth->valid_access_token($authorization);
        if ($valid) {
            $adapter = new Pagination();
            $adptr = new Adapter();
            $queryParams = $request->getQueryParams();
            $adapter->limiting = array_key_exists('limit', $queryParams)
                ? $queryParams['limit']
                : 24;
            $table_name = 'products';
            $adapter->fields = '*';
            $adapter->table = $table_name;
            $where = '';
            $page = isset($args['page']) ? $args['page'] : 1;
            if (is_array($queryParams)) {
                foreach ($queryParams as $k => $val) {
                    if ($k == 'query') {
                        $where = isset($val)
                            ? 'CONCAT_WS(' .
                                $adapter->columns() .
                                ") LIKE '%{$val}%'       "
                            : '';
                    } else {
                        $where .= isset($val)
                            ? "`{$table_name}`.{$k}={$val} AND "
                            : '';
                    }
                }
                if (!empty($where)) {
                    $where = substr($where, 0, -4);
                }
            }

            $listProducts = [];
            $fetchAll = $adapter->listing([
                'where' => $where,
                'order' =>
                    "`{$table_name}`." . $adapter->primary_key() . ' DESC',
                'page' => $page,
            ]);

            $c = 0;
            if (haveRows($fetchAll)) {
                $listProducts = $fetchAll;
                foreach ($fetchAll['list'] as $p) {
                    $adptr->table = 'product_prices';
                    $fetchAllPrices = $adptr->find(
                        'product_prices.product_id',
                        $p['product_id'],
                        'product_price_status=1'
                    );
                    $listProducts['list'][$c]['prices'] = $fetchAllPrices;

                    $adptr->table = 'product_installments';
                    $fetchAllInstallments = $adptr->find(
                        'product_installments.product_id',
                        $p['product_id'],
                        'product_installment_status=1'
                    );
                    $listProducts['list'][$c][
                        'installments'
                    ] = $fetchAllInstallments;

                    $adptr->table = 'product_images';
                    $fetchAllImages = $adptr->find(
                        'product_images.product_id',
                        $p['product_id'],
                        'product_image_status=1'
                    );
                    $listProducts['list'][$c]['images'] = $fetchAllImages;

                    $c++;
                }
            }

            $response
                ->getBody()
                ->write(json_encode($listProducts, JSON_NUMERIC_CHECK));
        } else {
            $statusCode = 401;
            $error = $auth->bad_credentials_message();
            $response->getBody()->write(json_encode($error));
        }
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    });
};
