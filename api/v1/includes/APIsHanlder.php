<?php

namespace Ekomi;

/**
 * Calls the eKomi APIs
 * 
 * This is the class which contains the queries to eKomi Systems.
 * 
 * @since 1.0.0
 */
class APIsHanlder {

    function __construct() {
        
    }

    /**
     * @param $configData array
     */
    public function verifyAccount($configData) {
        $ApiUrl = 'http://api.ekomi.de/v3/getSettings';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ApiUrl . "?auth=" . $configData['shopId'] . "|" . $configData['shopSecret'] . "&version=cust-1.0.0&type=request&charset=iso");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);
        if ($server_output == 'Access denied') {
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
