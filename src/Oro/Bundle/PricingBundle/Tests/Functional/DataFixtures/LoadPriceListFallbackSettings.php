<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadCustomers;
use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadGroups;
use Oro\Bundle\PricingBundle\Entity\PriceListCustomerFallback;
use Oro\Bundle\PricingBundle\Entity\PriceListCustomerGroupFallback;
use Oro\Bundle\PricingBundle\Entity\PriceListWebsiteFallback;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\WebsiteBundle\Tests\Functional\DataFixtures\LoadWebsiteData;

class LoadPriceListFallbackSettings extends AbstractFixture implements DependentFixtureInterface
{
    const WEBSITE_CUSTOMER_GROUP_FALLBACK_1 = 'US_customer_group1_price_list_fallback';
    const WEBSITE_CUSTOMER_GROUP_FALLBACK_2 = 'US_customer_group2_price_list_fallback';
    const WEBSITE_CUSTOMER_GROUP_FALLBACK_3 = 'Canada_customer_group1_price_list_fallback';
    const WEBSITE_CUSTOMER_GROUP_FALLBACK_4 = 'Canada_customer_group2_price_list_fallback';

    /**
     * @var array
     */
    protected $fallbackSettings = [
        'customer' => [
            LoadWebsiteData::WEBSITE1 => [
                'customer.level_1_1' => PriceListCustomerFallback::ACCOUNT_GROUP,
                'customer.level_1.3' => PriceListCustomerFallback::ACCOUNT_GROUP,
                'customer.level_1.2' => PriceListCustomerFallback::CURRENT_ACCOUNT_ONLY,
            ],
            LoadWebsiteData::WEBSITE2 => [
                'customer.level_1_1' => PriceListCustomerFallback::CURRENT_ACCOUNT_ONLY,
                'customer.level_1.3' => PriceListCustomerFallback::ACCOUNT_GROUP,
                'customer.level_1.2' => PriceListCustomerFallback::CURRENT_ACCOUNT_ONLY,
            ],
        ],
        'customerGroup' => [
            LoadWebsiteData::WEBSITE1 => [
                [
                    'reference' => self::WEBSITE_CUSTOMER_GROUP_FALLBACK_1,
                    'group' => 'customer_group.group1',
                    'fallback' => PriceListCustomerGroupFallback::WEBSITE,
                ],
                [
                    'reference' => self::WEBSITE_CUSTOMER_GROUP_FALLBACK_2,
                    'group' => 'customer_group.group2',
                    'fallback' => PriceListCustomerGroupFallback::CURRENT_ACCOUNT_GROUP_ONLY,
                ],
            ],
            LoadWebsiteData::WEBSITE2 => [
                [
                    'reference' => self::WEBSITE_CUSTOMER_GROUP_FALLBACK_3,
                    'group' => 'customer_group.group1',
                    'fallback' => PriceListCustomerGroupFallback::WEBSITE,
                ],
                [
                    'reference' => self::WEBSITE_CUSTOMER_GROUP_FALLBACK_4,
                    'group' => 'customer_group.group2',
                    'fallback' => PriceListCustomerGroupFallback::CURRENT_ACCOUNT_GROUP_ONLY,
                ],
            ],
        ],
        'website' => [
            'US' => PriceListWebsiteFallback::CONFIG,
            'Canada' => PriceListWebsiteFallback::CURRENT_WEBSITE_ONLY,
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadWebsiteData::class,
            LoadCustomers::class,
            LoadGroups::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        foreach ($this->fallbackSettings['customer'] as $websiteReference => $fallbackSettings) {
            /** @var Website $website */
            $website = $this->getReference($websiteReference);
            foreach ($fallbackSettings as $customerReference => $fallbackValue) {
                /** @var Customer $customer */
                $customer = $this->getReference($customerReference);

                $priceListCustomerFallback = new PriceListCustomerFallback();
                $priceListCustomerFallback->setCustomer($customer);
                $priceListCustomerFallback->setWebsite($website);
                $priceListCustomerFallback->setFallback($fallbackValue);

                $manager->persist($priceListCustomerFallback);
            }
        }

        foreach ($this->fallbackSettings['customerGroup'] as $websiteReference => $fallbackSettings) {
            /** @var Website $website */
            $website = $this->getReference($websiteReference);
            foreach ($fallbackSettings as $fallbackData) {
                /** @var CustomerGroup $customerGroup */
                $customerGroup = $this->getReference($fallbackData['group']);

                $priceListCustomerGroupFallback = new PriceListCustomerGroupFallback();
                $priceListCustomerGroupFallback->setCustomerGroup($customerGroup);
                $priceListCustomerGroupFallback->setWebsite($website);
                $priceListCustomerGroupFallback->setFallback($fallbackData['fallback']);

                $manager->persist($priceListCustomerGroupFallback);
                $this->setReference($fallbackData['reference'], $priceListCustomerGroupFallback);
            }
        }

        foreach ($this->fallbackSettings['website'] as $websiteReference => $fallbackValue) {
            /** @var Website $website */
            $website = $this->getReference($websiteReference);

            $priceListWebsiteFallback = new PriceListWebsiteFallback();
            $priceListWebsiteFallback->setWebsite($website);
            $priceListWebsiteFallback->setFallback($fallbackValue);

            $manager->persist($priceListWebsiteFallback);
        }
        $manager->flush();
    }
}
