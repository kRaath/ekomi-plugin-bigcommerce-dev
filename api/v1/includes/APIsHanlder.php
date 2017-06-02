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

    /**
     * 
     * @param String $ordersData
     * @return type
     */
    public function sendDataToPD($fields) {
        $url = 'https://plugins-dashboard-staging-1.ekomiapps.de/api/v1/order';
        $boundary = md5(time());

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('ContentType:multipart/form-data;boundary=' . $boundary));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

}
