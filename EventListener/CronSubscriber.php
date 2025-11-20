<?php

namespace MauticPlugin\MauticEmailRssImportBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\MaintenanceEvent;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticEmailRssImportBundle\Service\RssService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class CronSubscriber
 *
 * Subscribes to cron events to automatically fetch RSS feeds daily
 *
 * @package MauticPlugin\MauticEmailRssImportBundle\EventListener
 */
class CronSubscriber implements EventSubscriberInterface
{
    /**
     * @var RssService
     */
    private $rssService;

    /**
     * @var IntegrationHelper
     */
    private $integrationHelper;

    /**
     * CronSubscriber constructor.
     *
     * @param RssService $rssService
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(RssService $rssService, IntegrationHelper $integrationHelper)
    {
        $this->rssService = $rssService;
        $this->integrationHelper = $integrationHelper;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::MAINTENANCE_CLEANUP_DATA => ['onCronMaintenance', 0],
        ];
    }

    /**
     * Execute RSS feed fetching during cron maintenance
     *
     * @param MaintenanceEvent $event
     */
    public function onCronMaintenance(MaintenanceEvent $event): void
    {
        // Get integration settings
        $integration = $this->integrationHelper->getIntegrationObject('EmailRssImport');

        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return;
        }

        $settings = $integration->getKeys();

        if (empty($settings['active'])) {
            return;
        }

        // Check if we should run (only once per day)
        if (!$this->shouldRun($event)) {
            return;
        }

        $event->setStat('RSS Feeds', 'Fetching RSS feeds...', 'mautic.emailrssimport.rss');

        // Parse feeds
        $feeds = $this->parseFeeds($settings);

        if (empty($feeds)) {
            $event->setStat('RSS Feeds', 'No feeds configured', 'mautic.emailrssimport.rss');
            return;
        }

        // Parse RSS fields
        $rssFields = $settings['rss_fields'] ?? "title\nlink\ndescription\ncategory\npubDate\nmedia";
        $fields = array_filter(array_map('trim', explode("\n", $rssFields)));

        $totalAdded = 0;
        $totalSkipped = 0;
        $errorCount = 0;

        foreach ($feeds as $feedName => $feedUrl) {
            $result = $this->rssService->fetchAndStoreFeed($feedName, $feedUrl, $fields);

            if ($result['success']) {
                $totalAdded += $result['itemsAdded'];
                $totalSkipped += $result['itemsSkipped'];
            } else {
                $errorCount++;
            }
        }

        // Cleanup old items (keep last 30 days)
        $deleted = $this->rssService->cleanupOldItems(30);

        $message = "Fetched " . count($feeds) . " feeds: {$totalAdded} items added, {$totalSkipped} skipped";
        if ($deleted > 0) {
            $message .= ", {$deleted} old items cleaned up";
        }
        if ($errorCount > 0) {
            $message .= " ({$errorCount} errors)";
        }

        $event->setStat('RSS Feeds', $message, 'mautic.emailrssimport.rss');
    }

    /**
     * Determine if RSS fetching should run
     * Only run once per day
     *
     * @param MaintenanceEvent $event
     * @return bool
     */
    private function shouldRun(MaintenanceEvent $event): bool
    {
        // Check if this is a daily maintenance run
        // We use the isDryRun to skip during testing
        if ($event->isDryRun()) {
            return false;
        }

        // Always run during maintenance (which typically runs once per day)
        return true;
    }

    /**
     * Parse feeds configuration into an array of name => URL
     *
     * @param array $settings
     * @return array
     */
    private function parseFeeds(array $settings): array
    {
        $feeds = [];

        // Support new multiple feeds format
        if (!empty($settings['rss_feeds'])) {
            $lines = array_filter(array_map('trim', explode("\n", $settings['rss_feeds'])));
            foreach ($lines as $line) {
                // Format: Feed Name|https://example.com/rss.xml
                if (strpos($line, '|') !== false) {
                    [$name, $url] = array_map('trim', explode('|', $line, 2));
                    if (!empty($name) && !empty($url)) {
                        $feeds[$name] = $url;
                    }
                }
            }
        }

        // Backward compatibility: support old single RSS URL field
        if (empty($feeds) && !empty($settings['rss_url'])) {
            $feeds['Default Feed'] = $settings['rss_url'];
        }

        return $feeds;
    }
}
