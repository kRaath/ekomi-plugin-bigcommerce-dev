<?php

namespace Ekomi;

use Bigcommerce\Api\Client as Bigcommerce;

/**
 * Calls the BigCommerce APIs
 * 
 * This is the class which contains the queries to eKomi Systems.
 * 
 * @since 1.0.0
 */
class BCHanlder {

    private $storeConfig;
    private $prcConfig;

    function __construct($storeConfig, $prcConfig) {
        $this->storeConfig = $storeConfig;
        $this->prcConfig = $prcConfig;

        Bigcommerce::useJson();
        configureBCApi($storeConfig['storeHash'], $storeConfig['accessToken']);
        Bigcommerce::verifyPeer(false);
    }

    public function getOrderStatusesList() {
        $orderStatuses = Bigcommerce::getOrderStatuses();
        $statuses = array();

        foreach ($orderStatuses as $key => $status) {
            $statuses [$status->id] = $status->name;
        }
        return $statuses;
    }

    public function getVariantIDs($bcProduct) {
        $productId = '';
        if ($bcProduct) {
            foreach ($bcProduct->variants as $key => $variant) {
                $productId .= ',' . "'$variant->id'";
            }
        }
        return $productId;
    }

    public function createWebHooks($appUrl) {
        $response = NULL;
        try {
//            $response = Bigcommerce::createWebhook([
//                        "scope" => "store/order/created",
//                        "destination" => $appUrl . "orderUpdated",
//                        "is_active" => true
//            ]);
            $response = Bigcommerce::createWebhook([
                        "scope" => "store/order/updated",
                        "destination" => $appUrl . "orderUpdated",
                        "is_active" => true
            ]);
//            $response = Bigcommerce::createWebhook([
//                        "scope" => "store/order/statusUpdated",
//                        "destination" => $appUrl . "orderUpdated",
//                        "is_active" => true
//            ]);
        } catch (Error $error) {
            $response = $error->getCode();
            $response .= ' | ' . $error->getMessage();
        }
        return $response;
    }

    public function listWebHooks() {
        return Bigcommerce::listWebhooks();
    }

    public function getOrderData($orderId) {
        $orderData = array();

        $order = Bigcommerce::getOrder($orderId);
        $orderData['order'] =$order;
        $orderData['products'] = Bigcommerce::getOrderProducts($orderId);
        
         $customerId = $order->fields->customer_id;
         die($customerId);
        $orderData['customer'] = Bigcommerce::getCustomer($customerId);
        $orderData['shipingAddresses'] = Bigcommerce::getOrderShippingAddresses($orderId);
        $orderData['shipments'] = Bigcommerce::getShipments($orderId);

       
        
        



        return $orderData;
    }

}
