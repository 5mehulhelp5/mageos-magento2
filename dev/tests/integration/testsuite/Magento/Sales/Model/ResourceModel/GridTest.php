<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Model\ResourceModel;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Grid\LastUpdateTimeCache;
use Magento\Sales\Model\ResourceModel\Provider\UpdatedAtListProvider;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDataFixture Magento/Sales/_files/order_with_invoice_shipment_creditmemo.php
 */
class GridTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var LastUpdateTimeCache
     */
    private $lastUpdateTimeCache;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->lastUpdateTimeCache = $this->objectManager->get(LastUpdateTimeCache::class);
    }

    /**
     * @dataProvider gridDataProvider
     * @param array $constructorArgs
     * @param string $orderIdField
     */
    public function testRefreshBySchedule(array $constructorArgs, string $orderIdField)
    {
        $constructorArgs['orderIdField'] = $constructorArgs['mainTableName'] . '.' . $orderIdField;
        $constructorArgs['columns'] = [
            $orderIdField => $constructorArgs['orderIdField'],
            'created_at' => $constructorArgs['mainTableName'] . '.created_at',
            'updated_at' => $constructorArgs['mainTableName'] . '.updated_at',
        ];
        $constructorArgs['notSyncedDataProvider'] = $this->objectManager->get(UpdatedAtListProvider::class);
        $grid = $this->objectManager->create(Grid::class, $constructorArgs);

        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class)
            ->loadByIncrementId('100000111');
        $connection = $grid->getConnection();
        $select = $connection->select()
            ->from($constructorArgs['mainTableName'], ['created_at', 'updated_at'])
            ->where($orderIdField, $order->getEntityId());
        $data = $connection->fetchRow($select);
        $this->assertNotEmpty($data);

        //refresh data without cached updated_at
        $this->lastUpdateTimeCache->remove($constructorArgs['gridTableName']);
        $this->assertEmpty($this->lastUpdateTimeCache->get($constructorArgs['gridTableName']));
        sleep(1);
        $data['created_at'] = $data['updated_at'] = date('Y-m-d H:i:s');
        $connection->update(
            $constructorArgs['mainTableName'],
            $data,
            sprintf('%s = %d', $orderIdField, $order->getEntityId())
        );
        $grid->refreshBySchedule();

        $select = $connection->select()
            ->from($constructorArgs['gridTableName'], ['created_at', 'updated_at'])
            ->where($orderIdField, $order->getEntityId());
        $gridData = $connection->fetchRow($select);
        $this->assertEquals($data, $gridData);

        //refresh data with cached updated_at
        $this->assertNotEmpty($this->lastUpdateTimeCache->get($constructorArgs['gridTableName']));
        sleep(1);
        $data['created_at'] = $data['updated_at'] = date('Y-m-d H:i:s');
        $connection->update(
            $constructorArgs['mainTableName'],
            $data,
            sprintf('%s = %d', $orderIdField, $order->getEntityId())
        );
        $grid->refreshBySchedule();

        $select = $connection->select()
            ->from($constructorArgs['gridTableName'], ['created_at', 'updated_at'])
            ->where($orderIdField, $order->getEntityId());
        $gridData = $connection->fetchRow($select);
        $this->assertEquals($data, $gridData);
    }

    /**
     * @return array
     */
    public function gridDataProvider(): array
    {
        return [
            'Magento\Sales\Model\ResourceModel\Order\Grid' => [
                [
                    'mainTableName' => 'sales_order',
                    'gridTableName' => 'sales_order_grid',
                ],
                'entity_id',
            ],
            'ShipmentGridAggregator' => [
                [
                    'mainTableName' => 'sales_shipment',
                    'gridTableName' => 'sales_shipment_grid',
                ],
                'order_id',
            ],
            'CreditmemoGridAggregator' => [
                [
                    'mainTableName' => 'sales_creditmemo',
                    'gridTableName' => 'sales_creditmemo_grid',
                ],
                'order_id',
            ],
            'Magento\Sales\Model\ResourceModel\Order\Invoice\Grid' => [
                [
                    'mainTableName' => 'sales_invoice',
                    'gridTableName' => 'sales_invoice_grid',
                ],
                'order_id',
            ],
        ];
    }

    /**
     * @dataProvider shipmentGridDataProvider
     * @param array $constructorArgs
     * @param string $orderIdField
     */
    public function testSalesShipmentGridOrderIdField(array $constructorArgs, string $orderIdField)
    {
        $constructorArgs['orderIdField'] = $constructorArgs['mainTableName'] . '.' . $orderIdField;
        $constructorArgs['columns'] = [
            $orderIdField => $constructorArgs['orderIdField'],
            'created_at' => $constructorArgs['mainTableName'] . '.created_at',
            'updated_at' => $constructorArgs['mainTableName'] . '.updated_at',
        ];
        $constructorArgs['notSyncedDataProvider'] = $this->objectManager->get(UpdatedAtListProvider::class);
        $grid = $this->objectManager->create(Grid::class, $constructorArgs);
        $connection = $grid->getConnection();
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class)
            ->loadByIncrementId('100000111');
        $select = $connection->select()
            ->from($constructorArgs['gridTableName'], ['entity_id', 'order_id'])
            ->where($orderIdField, $order->getEntityId());
        $gridData = $connection->fetchRow($select);
        $testData = ['entity_id' => $order->getEntityId(), 'order_id' => $order->getEntityId()];
        $this->assertEquals($testData, $gridData);

    }

    /**
     * @return array
     */
    public function shipmentGridDataProvider(): array
    {
        return [
            'Magento\Sales\Model\ResourceModel\Grid' => [
                [
                    'mainTableName' => 'sales_shipment',
                    'gridTableName' => 'sales_shipment_grid',
                ],
                'entity_id',
            ],
        ];
    }
}
