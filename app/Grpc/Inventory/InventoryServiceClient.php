<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Inventory;

/**
 */
class InventoryServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * (Remote Procedure Call)
     * @param \Inventory\StockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CheckStock(\Inventory\StockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.InventoryService/CheckStock',
        $argument,
        ['\Inventory\StockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\ReserveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ReserveStock(\Inventory\ReserveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.InventoryService/ReserveStock',
        $argument,
        ['\Inventory\OperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\ReleaseRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ReleaseStock(\Inventory\ReleaseRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.InventoryService/ReleaseStock',
        $argument,
        ['\Inventory\OperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\InitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function InitInventory(\Inventory\InitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.InventoryService/InitInventory',
        $argument,
        ['\Inventory\OperationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Inventory\DeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeleteInventory(\Inventory\DeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/inventory.InventoryService/DeleteInventory',
        $argument,
        ['\Inventory\OperationResponse', 'decode'],
        $metadata, $options);
    }

}
