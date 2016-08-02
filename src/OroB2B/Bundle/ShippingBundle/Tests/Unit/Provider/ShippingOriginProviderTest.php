<?php

namespace OroB2B\Bundle\ShippingBundle\Tests\Unit\Provider;

use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Region;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Component\Testing\Unit\EntityTrait;

use OroB2B\Bundle\ShippingBundle\Entity\ShippingOriginWarehouse;
use OroB2B\Bundle\ShippingBundle\Factory\ShippingOriginModelFactory;
use OroB2B\Bundle\ShippingBundle\Model\ShippingOrigin;
use OroB2B\Bundle\ShippingBundle\Provider\ShippingOriginProvider;
use OroB2B\Bundle\WarehouseBundle\Entity\Warehouse;

class ShippingOriginProviderTest extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /**
     * @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $doctrineHelper;

    /**
     * @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $configManager;

    /**
     * @var ShippingOriginModelFactory|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $shippingOriginModelFactory;

    /**
     * @var ShippingOriginProvider
     */
    protected $shippingOriginProvider;

    protected function setUp()
    {
        $this->doctrineHelper = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->shippingOriginModelFactory = new ShippingOriginModelFactory($this->doctrineHelper);

        $this->shippingOriginProvider = new ShippingOriginProvider(
            $this->doctrineHelper,
            $this->configManager,
            $this->shippingOriginModelFactory
        );
    }

    /**
     * @dataProvider shippingOriginProvider
     *
     * @param Warehouse $warehouse
     * @param ShippingOriginWarehouse|null $shippingOriginWarehouse
     * @param string $expected
     */
    public function testGetShippingOriginByWarehouse(Warehouse $warehouse, $shippingOriginWarehouse, $expected)
    {
        $repository = $this->getMock('Doctrine\Common\Persistence\ObjectRepository');
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['warehouse' => $warehouse])
            ->willReturn($shippingOriginWarehouse)
        ;

        $entityManager = $this->getMockBuilder('\Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with('OroB2B\Bundle\ShippingBundle\Entity\ShippingOriginWarehouse')
            ->willReturn($repository)
        ;

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityManagerForClass')
            ->with('OroB2B\Bundle\ShippingBundle\Entity\ShippingOriginWarehouse')
            ->willReturn($entityManager)
        ;

        $this->configManager->expects($this->any())
            ->method('get')
            ->with('orob2b_shipping.shipping_origin')
            ->willReturn([])
        ;

        $shippingOrigin = $this->shippingOriginProvider->getShippingOriginByWarehouse($warehouse);

        $this->assertInstanceOf($expected, $shippingOrigin);
    }

    /**
     * @dataProvider systemShippingOriginProvider
     *
     * @param array $configData
     * @param string $expectedCountry
     * @param string $expectedRegion
     */
    public function testGetSystemShippingOrigin($configData, $expectedCountry, $expectedRegion)
    {
        $country = new Country($configData['country']);
        $this->doctrineHelper->expects($this->at(0))
            ->method('getEntityReference')
            ->with('OroAddressBundle:Country', $configData['country'])
            ->willReturn($country)
        ;

        $region = new Region($configData['region']);
        $this->doctrineHelper->expects($this->at(1))
            ->method('getEntityReference')
            ->with('OroAddressBundle:Region', $configData['region'])
            ->willReturn($region)
        ;

        $shippingOrigin = $this->shippingOriginModelFactory->create($configData);

        $this->assertEquals($expectedCountry, $shippingOrigin->getCountry());
        $this->assertEquals($expectedRegion, $shippingOrigin->getRegion());
    }

    /**
     * @return array
     */
    public function shippingOriginProvider()
    {
        $warehouse = $this->getEntity(Warehouse::class, ['id' => 1, 'name' => 'Warehouse.1']);
        $data = new \ArrayObject();

        return [
            [
                'warehouse' => $warehouse,
                'shippingOriginWarehouse' => $this->getEntity(
                    ShippingOriginWarehouse::class,
                    [
                        'warehouse' => $warehouse,
                        'data' => $data->offsetSet('postalCode', '12345')
                    ],
                    []
                ),
                'expected' => ShippingOrigin::class
            ],
            [
                'warehouse' => $warehouse,
                'shippingOriginWarehouse' => null,
                'expected' => ShippingOrigin::class
            ]
        ];
    }

    /**
     * @return array
     */
    public function systemShippingOriginProvider()
    {
        return [
            [
                'configData' => [
                    'country' => 'US',
                    'region' => 'US-AL',
                ],
                'expectedCountry' => new Country('US'),
                'expectedRegion' => new Region('US-AL')
            ]
        ];
    }
}
