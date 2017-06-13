<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Bigcommerce\Api\Client as Bigcommerce;
use Guzzle\Http\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ekomi\DbHandler;
use Ekomi\APIsHanlder;
use Ekomi\ConfigHelper;
use Ekomi\BCHanlder;

$app = new Application();
$app['debug'] = true;

$configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver' => 'pdo_mysql',
        'host' => $configHelper->dbHost(),
        'dbname' => $configHelper->dbName(),
        'user' => $configHelper->dbUsername(),
        'password' => $configHelper->dbPassword(),
        'charset' => 'utf8mb4'
    ),
));

$app->post('/saveConfig', function (Request $request) use ($app) {
    $storeHash = $request->get('storeHash');
    $id = $request->get('shopId');
    $secret = $request->get('shopSecret');

    $config = array(
        'storeHash' => $storeHash,
        'enabled' => $request->get('enabled'),
        'shopId' => $id,
        'shopSecret' => $secret,
        'productReviews' => $request->get('productReviews'),
        'mode' => $request->get('mode'),
        'statuses' => implode(',', $request->get('statuses'))
    );
    $apisHanlder = new APIsHanlder();
    $dbHandler = new DbHandler($app['db']);

    if ($id && $secret && $apisHanlder->verifyAccount($config)) {

        if (!$dbHandler->getPluginConfig($storeHash)) {
            $dbHandler->savePluginConfig($config);
        } else {
            $dbHandler->updatePluginConfig($config, $storeHash);
        }

        $alert = 'info';
        $message = 'Configuration saved successfully.';
    } else {
        $alert = 'danger';
        $message = 'Invalid shop id or secret.';
        $config['enabled'] = 0;
        $config['shopId'] = '';
        $config['shopSecret'] = '';
    }

    $bcHanlder = new BCHanlder($dbHandler->getStoreConfig($storeHash), $config);

    $statuses = $bcHanlder->getOrderStatusesList();

    $response = ['config' => $config, 'statuses' => $statuses, 'storeHash' => $storeHash, 'alert' => $alert, 'message' => $message];

    return $app['twig']->render('configuration.twig', $response);
});

$app->post('/orderUpdated', function (Request $request) use ($app) {

    $erroLogPath = explode('/api/', $_SERVER['SCRIPT_FILENAME'])[0];

    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    
    if (isset($input['data']['type']) && $input['data']['type'] == 'order') {

        $data = $input['data'];

        list($context, $storeHash) = explode('/', $input['producer'], 2);

        $orderId = $data['id'];

        $apisHanlder = new APIsHanlder();
        $dbHandler = new DbHandler($app['db']);

        $storeConfig = $dbHandler->getStoreConfig($storeHash);
        $pluginConfig = $dbHandler->getPluginConfig($storeHash);

        $return = 'something went wrong.';
        $statusCode = 200;

        if ($pluginConfig['enabled'] == '1') {
            $bcHanlder = new BCHanlder($storeConfig, $pluginConfig);

            $orderData = $bcHanlder->getOrderData($orderId);

            if (!empty($orderData)) {
                $fields = array(
                    'shop_id' => $pluginConfig['shopId'],
                    'interface_password' => $pluginConfig['shopSecret'],
                    'order_data' => $orderData,
                    'mode' => $pluginConfig['mode'],
                    'product_reviews' => $pluginConfig['productReviews'],
                    'plugin_name' => 'bigcommerce'
                );

                $fields = json_encode($fields);

                $response = $apisHanlder->sendDataToPD($fields);

                if ($response['code'] != 201) {
                    error_log(" Store:{$storeHash},orderId:$orderId => " . json_encode($response), 3, $erroLogPath . '/error.log');
                }
                $return = " Store:{$storeHash},orderId:$orderId => " . json_encode($response);
            }
        } else {
            $return = "eKomi Integration is not active.";
        }
    }
    
//    error_log($return, 3, $erroLogPath . '/error.log');
    
    return new Response($return, $statusCode);
});
$app->get('/load', function (Request $request) use ($app) {

    $data = verifySignedRequest($request->get('signed_payload'));
    if (empty($data)) {
        return 'Invalid signed_payload.';
    } else {
        
    }

    $storeHash = $data['store_hash'];
    $dbHandler = new DbHandler($app['db']);

    $storeConfig = $dbHandler->getStoreConfig($storeHash);
    $pluginConfig = $dbHandler->getPluginConfig($storeHash);

    $bcHanlder = new BCHanlder($storeConfig, $pluginConfig);

    $statuses = $bcHanlder->getOrderStatusesList();

    return $app['twig']->render('configuration.twig', ['config' => $pluginConfig, 'statuses' => $statuses, 'storeHash' => $storeHash]);
});

$app->get('/oauth', function (Request $request) use ($app) {
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

    $payload = array(
        'client_id' => $configHelper->clientId(),
        'client_secret' => $configHelper->clientSecret(),
        'redirect_uri' => $configHelper->callbackUrl(),
        'grant_type' => 'authorization_code',
        'code' => $request->get('code'),
        'scope' => $request->get('scope'),
        'context' => $request->get('context'),
    );

    $client = new Client($configHelper->bcAuthService());
    $req = $client->post('https://login.bigcommerce.com/oauth2/token', array(), $payload, array(
        'exceptions' => false,
    ));
    $resp = $req->send();

    if ($resp->getStatusCode() == 200) {
        $data = $resp->json();

        list($context, $storeHash) = explode('/', $data['context'], 2);

        $accessToken = $data['access_token'];
        $user = $data['user'];

        $storeConfig = array(
            'storeHash' => $storeHash,
            'accessToken' => $accessToken,
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'installed' => 1,
        );

        $dbHandler = new DbHandler($app['db']);

        //removes the existing config and reviews in table
        $dbHandler->removeStoreConfig($storeHash);
        $dbHandler->removePluginConfig($storeHash);

        $store = $dbHandler->getStoreConfig($storeHash);

        if (!$store) {
            $dbHandler->saveStoreConfig($storeConfig);
        } else {
            $dbHandler->updateStoreConfig($storeConfig, $storeHash);
        }


        $pluginConfig = $dbHandler->getPluginConfig($storeHash);

        $bcHanlder = new BCHanlder($dbHandler->getStoreConfig($storeHash), $pluginConfig);

        $bcHanlder->createWebHooks($configHelper->APP_URL());

        $statuses = $bcHanlder->getOrderStatusesList();

        return $app['twig']->render('configuration.twig', ['config' => $pluginConfig, 'statuses' => $statuses, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Please save configuration.']);
    } else {
        return 'Something went wrong... [' . $resp->getStatusCode() . '] ' . $resp->getBody();
    }
});


$app->get('/uninstall', function (Request $request) use ($app) {

    $data = verifySignedRequest($request->get('signed_payload'));
    if (empty($data)) {
        return 'Invalid signed_payload.';
    } else {
        
    }

    $storeHash = $data['store_hash'];

    $dbHandler = new DbHandler($app['db']);
    $dbHandler->removeStoreConfig($storeHash);
    $dbHandler->removePluginConfig($storeHash);

    return "uninstalled successfully";
});

/**
 * Configure the static BigCommerce API client with the authorized app's auth token, the client ID from the environment
 * and the store's hash as provided.
 * @param string $storeHash Store hash to point the BigCommece API to for outgoing requests.
 */
function configureBCApi($storeHash, $auth_token) {
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
    Bigcommerce::configure(array(
        'client_id' => $configHelper->clientId(),
        'auth_token' => $auth_token,
        'store_hash' => $storeHash
    ));
}

/**
 * This is used by the `GET /load` endpoint to load the app in the BigCommerce control panel
 * @param string $signedRequest Pull signed data to verify it.
 * @return array|null null if bad request, array of data otherwise
 */
function verifySignedRequest($signedRequest) {
    list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

    // decode the data
    $signature = base64_decode($encodedSignature);
    $jsonStr = base64_decode($encodedData);
    $data = json_decode($jsonStr, true);

    // confirm the signature
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

    $expectedSignature = hash_hmac('sha256', $jsonStr, $configHelper->clientSecret(), $raw = false);
    if (!hash_equals($expectedSignature, $signature)) {
        error_log('Bad signed request from BigCommerce!');
        return null;
    }
    return $data;
}

function baseUrl() {
    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
    $temp = explode('v1', $_SERVER['REDIRECT_URL']);
    if (isset($temp[0])) {
        return $url . $temp[0];
    } else {
        return $url . 'ekomi-prc-bigcommerce/api/';
    }
}

$app->run();

