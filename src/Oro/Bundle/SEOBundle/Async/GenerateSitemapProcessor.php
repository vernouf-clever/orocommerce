<?php

namespace Oro\Bundle\SEOBundle\Async;

use Oro\Bundle\RedirectBundle\Generator\CanonicalUrlGenerator;
use Oro\Bundle\SEOBundle\Model\Exception\InvalidArgumentException;
use Oro\Bundle\SEOBundle\Model\SitemapIndexMessageFactory;
use Oro\Bundle\SEOBundle\Model\SitemapMessageFactory;
use Oro\Bundle\SEOBundle\Provider\WebsiteForSitemapProviderInterface;
use Oro\Bundle\SEOBundle\Sitemap\Filesystem\GaufretteFilesystemAdapter;
use Oro\Bundle\SEOBundle\Sitemap\Website\WebsiteUrlProvidersServiceInterface;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\DependentJobService;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\Website\WebsiteInterface;
use Psr\Log\LoggerInterface;

/**
 * Async processor to generate sitemap files.
 */
class GenerateSitemapProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    /**
     * @var JobRunner
     */
    private $jobRunner;

    /**
     * @var DependentJobService
     */
    private $dependentJobService;

    /**
     * @var MessageProducerInterface
     */
    private $producer;

    /**
     * @var WebsiteForSitemapProviderInterface
     */
    private $websiteProvider;

    /**
     * @var SitemapIndexMessageFactory
     */
    private $indexMessageFactory;

    /**
     * @var SitemapMessageFactory
     */
    private $messageFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CanonicalUrlGenerator
     */
    private $canonicalUrlGenerator;

    /**
     * @var WebsiteUrlProvidersServiceInterface
     */
    private $websiteUrlProvidersService;

    /**
     * @var GaufretteFilesystemAdapter
     */
    private $fileSystemAdapter;

    /**
     * @var int
     */
    private $version;

    /**
     * @param JobRunner $jobRunner
     * @param DependentJobService $dependentJobService
     * @param MessageProducerInterface $producer
     * @param WebsiteUrlProvidersServiceInterface $websiteUrlProvidersService
     * @param SitemapIndexMessageFactory $indexMessageFactory
     * @param SitemapMessageFactory $messageFactory
     * @param LoggerInterface $logger
     * @param CanonicalUrlGenerator $canonicalUrlGenerator
     */
    public function __construct(
        JobRunner $jobRunner,
        DependentJobService $dependentJobService,
        MessageProducerInterface $producer,
        WebsiteUrlProvidersServiceInterface $websiteUrlProvidersService,
        SitemapIndexMessageFactory $indexMessageFactory,
        SitemapMessageFactory $messageFactory,
        LoggerInterface $logger,
        CanonicalUrlGenerator $canonicalUrlGenerator
    ) {
        $this->jobRunner = $jobRunner;
        $this->dependentJobService = $dependentJobService;
        $this->producer = $producer;
        $this->websiteUrlProvidersService = $websiteUrlProvidersService;
        $this->indexMessageFactory = $indexMessageFactory;
        $this->messageFactory = $messageFactory;
        $this->logger = $logger;
        $this->canonicalUrlGenerator = $canonicalUrlGenerator;
    }

    /**
     * @param WebsiteForSitemapProviderInterface $websiteProvider
     */
    public function setWebsiteProvider(WebsiteForSitemapProviderInterface $websiteProvider)
    {
        $this->websiteProvider = $websiteProvider;
    }

    /**
     * @param GaufretteFilesystemAdapter $fileSystemAdapter
     */
    public function setFileSystemAdapter(GaufretteFilesystemAdapter $fileSystemAdapter)
    {
        $this->fileSystemAdapter = $fileSystemAdapter;
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        $this->version = time();

        try {
            // make sure that temp directory is empty before the dump.
            $this->fileSystemAdapter->clearTempStorage();

            $websites = $this->websiteProvider->getAvailableWebsites();

            $result = $this->jobRunner->runUnique(
                $message->getMessageId(),
                Topics::GENERATE_SITEMAP,
                function (JobRunner $jobRunner, Job $job) use ($websites) {
                    $this->createFinishJobs($job, $websites);

                    foreach ($websites as $website) {
                        $providerNames = $this->getProvidersNamesByWebsite($website);
                        $this->canonicalUrlGenerator->clearCache($website);
                        foreach ($providerNames as $type) {
                            $this->scheduleGeneratingSitemapForWebsiteAndType($jobRunner, $website, $type);
                        }
                    }

                    return true;
                }
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->error(
                'Queue Message is invalid',
                ['exception' => $e]
            );

            return self::REJECT;
        } catch (\Exception $e) {
            $this->logger->error(
                'Unexpected exception occurred during queue message processing',
                [
                    'exception' => $e,
                    'topic' => Topics::GENERATE_SITEMAP,
                ]
            );

            return self::REJECT;
        }

        return $result ? self::ACK : self::REJECT;
    }

    /**
     * @param Job $job
     * @param array $websites
     */
    private function createFinishJobs(Job $job, array $websites): void
    {
        $context = $this->dependentJobService->createDependentJobContext($job->getRootJob());
        $websiteIds = [];
        foreach ($websites as $website) {
            $websiteIds[] = $website->getId();
            $context->addDependentJob(
                Topics::GENERATE_SITEMAP_INDEX_BY_WEBSITE,
                $this->indexMessageFactory->createMessage($website, $this->version)
            );
        }

        $context->addDependentJob(
            Topics::GENERATE_SITEMAP_MOVE_GENERATED_FILES,
            ['websiteIds' => $websiteIds],
            MessagePriority::VERY_LOW
        );
        $this->dependentJobService->saveDependentJob($context);
    }

    /**
     * @param WebsiteInterface $website
     *
     * @return array
     */
    protected function getProvidersNamesByWebsite(WebsiteInterface $website)
    {
        $providersByNames = $this->websiteUrlProvidersService->getWebsiteProvidersIndexedByNames($website);

        return array_keys($providersByNames);
    }

    /**
     * @param JobRunner $jobRunner
     * @param WebsiteInterface $website
     * @param string $type
     */
    protected function scheduleGeneratingSitemapForWebsiteAndType(
        JobRunner $jobRunner,
        WebsiteInterface $website,
        $type
    ) {
        $jobRunner->createDelayed(
            sprintf(
                '%s:%s:%s',
                Topics::GENERATE_SITEMAP_BY_WEBSITE_AND_TYPE,
                $website->getId(),
                $type
            ),
            function (JobRunner $jobRunner, Job $child) use ($website, $type) {
                $this->producer->send(
                    Topics::GENERATE_SITEMAP_BY_WEBSITE_AND_TYPE,
                    $this->messageFactory->createMessage($website, $type, $this->version, $child)
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::GENERATE_SITEMAP];
    }
}
