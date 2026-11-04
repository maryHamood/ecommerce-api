<?php

namespace App\Services;

use Inventory\InventoryServiceClient;
use Inventory\InitRequest;
use Inventory\ReserveRequest;
use Inventory\ReleaseRequest;
use Inventory\StockRequest;
use Inventory\Item;
use Grpc\ChannelCredentials;

class InventoryService
{
    protected $client;

    public function __construct()
    {
        $this->client = new InventoryServiceClient('inventory-service:50051', [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    public function initInventory($productId, $quantity)
    {
        $request = new InitRequest();
        $request->setProductId((string) $productId);
        $request->setInitialQuantity($quantity);

        list($response, $status) = $this->client->InitInventory($request)->wait();
        return $response ? $response->getSuccess() : false;
    }

    public function reserveStock($orderId, array $items)
    {
        $request = new ReserveRequest();
        $request->setOrderId((string) $orderId);

        $grpcItems = [];
        foreach ($items as $item) {
            $grpcItem = new Item();
            $grpcItem->setProductId((string) $item['product_id']);
            $grpcItem->setQuantity($item['quantity']);
            $grpcItems[] = $grpcItem;
        }
        $request->setItems($grpcItems);

        list($response, $status) = $this->client->ReserveStock($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException('gRPC connection failed: ' . ($status->details ?? 'unknown error'));
        }

        return $response ? $response->getSuccess() : false;
    }

    public function releaseStock($orderId)
    {
        $request = new ReleaseRequest();
        $request->setOrderId((string) $orderId);

        list($response, $status) = $this->client->ReleaseStock($request)->wait();
        return $response ? $response->getSuccess() : false;
    }

    public function checkStock($productId)
    {
        $request = new StockRequest();
        $request->setProductId((string) $productId);

        list($response, $status) = $this->client->CheckStock($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            return 0;
        }

        return $response ? $response->getQuantity() : 0;
    }

    public function deleteInventory($productId)
    {
        $request = new \Inventory\DeleteRequest();
        $request->setProductId((string) $productId);

        list($response, $status) = $this->client->DeleteInventory($request)->wait();
        return $response ? $response->getSuccess() : false;
    }
}