<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Silex\Application;
use Bigcommerce\Api\Client as Bigcommerce;
use Firebase\JWT\JWT;
use Guzzle\Http\Client;
use Handlebars\Handlebars;
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

    if ($id && $secret && $apisHanlder->verifyAccount($config)) {

        $dbHandler = new DbHandler($app['db']);

        if (!$dbHandler->getPrcConfig($storeHash)) {
            $dbHandler->savePrcConfig($config);
        } else {
            $dbHandler->updatePrcConfig($config, $storeHash);
        }

        /**
         * populate the prc_reviews table
         */
        if ($config['enabled'] == '1') {
//            $reviews = $apisHanlder->getProductReviews($config, $range = "all");
//            $dbHandler->saveReviews($config, $reviews);
        }
        $alert = 'info';
        $message = 'Configuration saved successfully.';
    } else {
        $alert = 'danger';
        $message = 'Invalid shop id or secret.';
    }

    $bcHanlder = new BCHanlder($dbHandler->getStoreConfig($storeHash), $config);

    $statuses = $bcHanlder->getOrderStatusesList();

    $response = ['config' => $config, 'statuses' => $statuses, 'storeHash' => $storeHash, 'alert' => $alert, 'message' => $message];

    return $app['twig']->render('configuration.twig', $response);
});

// Our web handlers
$app->post('/orderUpdated', function (Request $request) use ($app) {

    $apisHanlder = new APIsHanlder();
    $dbHandler = new DbHandler($app['db']);

    $storeHash = 'ali1vdxuuc';
    $storeConfig = $dbHandler->getStoreConfig($storeHash);
    $prcConfig = $dbHandler->getStoreConfig($storeHash);

    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));

    
    $bcHanlder = new BCHanlder($storeConfig, $prcConfig);
    
    var_dump($bcHanlder->createWebHooks($configHelper->APP_URL()));

    var_dump($bcHanlder->listWebHooks());

    $app['db']->insert('test', ['value' => 'orderUpdated']);

    die;

    return "Done";
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
    $prcConfig = $dbHandler->getPrcConfig($storeHash);

    $bcHanlder = new BCHanlder($storeConfig, $prcConfig);

    $statuses = $bcHanlder->getOrderStatusesList();
    var_dump($bcHanlder->listWebHooks());die;
    return $app['twig']->render('configuration.twig', ['config' => $prcConfig, 'statuses' => $statuses, 'storeHash' => $storeHash]);
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
        $dbHandler->removePrcConfig($storeHash);

        $store = $dbHandler->getStoreConfig($storeHash);

        if (!$store) {
            $dbHandler->saveStoreConfig($storeConfig);
        } else {
            $dbHandler->updateStoreConfig($storeConfig, $storeHash);
        }


        $prcConfig = $dbHandler->getPrcConfig($storeHash);

        $bcHanlder = new BCHanlder($dbHandler->getStoreConfig($storeHash), $prcConfig);

        $bcHanlder->createWebHooks($configHelper->APP_URL());

        $statuses = $bcHanlder->getOrderStatusesList();

        return $app['twig']->render('configuration.twig', ['config' => $prcConfig, 'statuses' => $statuses, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Please save configuration.']);
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
    $dbHandler->removePrcConfig($storeHash);
//    $dbHandler->removePrcReviews($storeHash);

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

