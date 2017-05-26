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
        'groupReviews' => $request->get('groupReviews'),
        'noReviewsTxt' => $request->get('noReviewsTxt'));
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
        $response = ['storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Configuration saved successfully.'];
    } else {
        $response = ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'danger', 'message' => 'Invalid shop id or secret.'];
    }
    return $app['twig']->render('configuration.twig', $response);
});

// Our web handlers
$app->get('/sendOrders', function (Request $request) use ($app) {

    $apisHanlder = new APIsHanlder();
    $dbHandler = new DbHandler($app['db']);
    $storesConfig = $dbHandler->getAllPrcConfig();

    /**
     * populate the prc_reviews table
     */
    foreach ($storesConfig as $key => $config) {
        if ($config['enabled'] == '1') {
//            $reviews = $apisHanlder->getProductReviews($config, $range = "1w");
//            $dbHandler->saveReviews($config, $reviews);
        }
    }
    return "Done";
});
$app->get('/load', function (Request $request) use ($app) {

    $data = verifySignedRequest($request->get('signed_payload'));
    if (empty($data)) {
        return 'Invalid signed_payload.';
    } else {
        
    }

    $storeHash = $data['store_hash'];
    // fetch config from DB and send as param
//	$kedy = getUserKey($data['store_hash'], $data['user']['email']);
    $dbHandler = new DbHandler($app['db']);
    $config = $dbHandler->getPrcConfig($storeHash);

    return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash]);
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

        $config = $dbHandler->getPrcConfig($storeHash);

        return $app['twig']->render('configuration.twig', ['config' => $config, 'storeHash' => $storeHash, 'alert' => 'info', 'message' => 'Please save configuration.']);
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
function configureBCApi($storeHash) {
    $configHelper = new ConfigHelper(new Dotenv\Dotenv(__DIR__ . '/../../'));
    Bigcommerce::configure(array(
        'client_id' => $configHelper->clientId(),
        'auth_token' => getAuthToken($storeHash),
        'store_hash' => $storeHash
    ));
}

/**
 * @param string $storeHash store's hash that we want the access token for
 * @return string the oauth Access (aka Auth) Token to use in API requests.
 */
function getAuthToken($storeHash) {
    $dbHandler = new DbHandler($app['db']);

    $config = $dbHandler->getStoreConfig($storeHash);

    return $config['accessToken'];
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

