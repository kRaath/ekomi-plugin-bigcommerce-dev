<?php

namespace Ekomi;

/**
 * Handles the database related functionality
 * 
 * This is the class which contains the queries to products reviews.
 * 
 * @since 1.0.0
 */
class ConfigHelper {

    protected $dotenv;

    function __construct($dotEnv) {
        // Load from .env file
        $dotEnv->load();
    }
    
    /**
     * @return string Get the app's client ID from the environment vars
     */
    public function clientId() {
        $clientId = getenv('BC_CLIENT_ID');
        return $clientId ?: '';
    }

    /**
     * @return string Get the app's client secret from the environment vars
     */
    public function clientSecret() {
        $clientSecret = getenv('BC_CLIENT_SECRET');
        return $clientSecret ?: '';
    }

    /**
     * @return string Get the callback URL from the environment vars
     */
    public function callbackUrl() {
        $callbackUrl = getenv('BC_CALLBACK_URL');
        return $callbackUrl ?: '';
    }

    /**
     * @return string Get auth service URL from the environment vars
     */
    public function bcAuthService() {
        $bcAuthService = getenv('BC_AUTH_SERVICE');
        return $bcAuthService ?: '';
    }
    public function dbHost() {
        $value = getenv('DB_HOST');
        return $value ?: '';
    }
    public function dbName() {
        $value = getenv('DB_NAME');
        return $value ?: '';
    }
    public function dbUsername() {
        $value = getenv('DB_USERNAME');
        return $value ?: '';
    }
    public function dbPassword() {
        $value = getenv('DB_PASSWORD');
        return $value ?: '';
    }


}
