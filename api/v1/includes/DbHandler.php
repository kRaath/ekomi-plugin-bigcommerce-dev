<?php

namespace Ekomi;

/**
 * Handles the database related functionality
 * 
 * This is the class which contains the queries to products reviews.
 * 
 * @since 1.0.0
 */
class DbHandler {

    protected $conn;

    function __construct($db) {
        $this->conn = $db;
    }

    /**
     * store_config CRUD
     */
    public function getStoreConfig($storeHash) {
        $data = $this->conn->fetchAssoc('SELECT * FROM store_config WHERE storeHash = ?', array($storeHash));

        return $data;
    }

    public function saveStoreConfig($storeConfig) {
        return $this->conn->insert('store_config', $storeConfig);
    }

    public function updateStoreConfig($storeConfig, $storeHash) {
        return $this->conn->update('store_config', $storeConfig, array('storeHash' => $storeHash));
    }

    public function removeStoreConfig($storeHash) {
        $val = $this->conn->delete('store_config', array('storeHash' => $storeHash));

        return $val;
    }

    /**
     * plugin_config CRUD
     */
    public function getPluginConfig($storeHash) {
        $data = $this->conn->fetchAssoc('SELECT * FROM plugin_config WHERE storeHash = ?', array($storeHash));

        return $data;
    }

    public function getAllPluginConfig() {
        $data = $this->conn->fetchAll('SELECT * FROM plugin_config');

        return $data;
    }

    public function savePluginConfig($config) {
        $data = $this->conn->insert('plugin_config', $config);

        return $data;
    }

    public function updatePluginConfig($config, $storeHash) {
        $value = $this->conn->update('plugin_config', $config, array('storeHash' => $storeHash));
        return $value;
    }

    public function removePluginConfig($storeHash) {
        $val = $this->conn->delete('plugin_config', array('storeHash' => $storeHash));

        return $val;
    }

}
