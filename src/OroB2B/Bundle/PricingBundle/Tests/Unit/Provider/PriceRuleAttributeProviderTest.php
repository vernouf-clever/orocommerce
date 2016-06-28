<?php

namespace OroB2B\Bundle\PricingBundle\Tests\Unit\Provider;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface;

use OroB2B\Bundle\PricingBundle\Provider\PriceRuleAttributeProvider;

class PriceRuleAttributeProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Registry|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var VirtualFieldProviderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $virtualFieldProvider;

    /**
     * @var PriceRuleAttributeProvider
     */
    protected $priceRuleAttributeProvider;

    protected function setUp()
    {
        $this->registry = $this->getMockBuilder('Doctrine\Bundle\DoctrineBundle\Registry')
            ->disableOriginalConstructor()
            ->getMock();


        $this->virtualFieldProvider = $this->getMock('Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface');
        $this->priceRuleAttributeProvider = new PriceRuleAttributeProvider(
            $this->registry,
            $this->virtualFieldProvider
        );
    }

    public function testGetAvailableRuleAttributes()
    {
        $this->mockManager();
        $className = 'ClassName';
        $this->priceRuleAttributeProvider->addAvailableClass($className);
        $actualFields = $this->priceRuleAttributeProvider->getAvailableRuleAttributes();
        $expectFields = [$className => ['field1', 'field2', 'field4']];
        $this->assertEquals($expectFields, $actualFields);
    }

    public function testGetAvailableConditionAttributes()
    {
        $this->mockManager();
        $className = 'ClassName';
        $this->virtualFieldProvider->method('getVirtualFields')->willReturn(['virtualMethodName']);

        $this->priceRuleAttributeProvider->addAvailableClass($className);
        $actualFields = $this->priceRuleAttributeProvider->getAvailableConditionAttributes();
        $expectFields = [$className => ['field1', 'field2', 'field4', 'virtualMethodName']];
        $this->assertEquals($expectFields, $actualFields);
    }

    public function testAddAvailableClass()
    {
        $class = 'ClassName';
        $this->priceRuleAttributeProvider->addAvailableClass($class);
        $this->assertEquals(['ClassName'], $this->priceRuleAttributeProvider->getAvailableClasses());
    }

    protected function mockManager()
    {
        $metadata = $this->getMock(ClassMetadata::class);
        $metadata->expects($this->once())->method('getFieldNames')->willReturn(['field1', 'field2', 'field3', 'field4']);
        $metadata->expects($this->exactly(4))->method('getTypeOfField')->willReturnMap(
            [
                ['field1', 'integer'],
                ['field2', 'money'],
                ['field3', 'string'],
                ['field4', 'float'],
            ]
        );

        $manager = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();
        $manager->method('getClassMetadata')->willReturn($metadata);
        $this->registry->method('getManagerForClass')->willReturn($manager);
    }
}
