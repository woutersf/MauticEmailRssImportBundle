<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticEmailRssImportBundle\Entity\RssItem;
use MauticPlugin\MauticEmailRssImportBundle\Entity\RssItemRepository;
use Psr\Log\LoggerInterface;

/**
 * Class RssService
 *
 * Service for fetching and storing RSS feed items
 *
 * @package MauticPlugin\MauticEmailRssImportBundle\Service
 */
class RssService
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RssItemRepository
     */
    private $repository;

    /**
     * RssService constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->repository = $entityManager->getRepository(RssItem::class);
    }

    /**
     * Fetch and store RSS feed items
     *
     * @param string $feedName
     * @param string $feedUrl
     * @param array $fields Fields to extract from the RSS feed
     * @return array ['success' => bool, 'message' => string, 'itemsAdded' => int, 'itemsSkipped' => int]
     */
    public function fetchAndStoreFeed(string $feedName, string $feedUrl, array $fields): array
    {
        $this->logger->info("RSS Import: Fetching feed '{$feedName}' from {$feedUrl}");

        try {
            // Fetch RSS feed
            $xmlContent = @file_get_contents($feedUrl);

            if ($xmlContent === false) {
                $error = "Failed to fetch RSS feed from {$feedUrl}";
                $this->logger->error("RSS Import: {$error}");
                return ['success' => false, 'message' => $error, 'itemsAdded' => 0, 'itemsSkipped' => 0];
            }

            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $error = "Failed to parse RSS feed: " . json_encode($errors);
                $this->logger->error("RSS Import: {$error}");
                return ['success' => false, 'message' => $error, 'itemsAdded' => 0, 'itemsSkipped' => 0];
            }

            $itemsAdded = 0;
            $itemsSkipped = 0;
            $fetchedDate = new \DateTime();

            // Handle RSS 2.0 format
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    // Extract GUID for duplicate detection
                    $guid = isset($item->guid) ? (string) $item->guid : (isset($item->link) ? (string) $item->link : null);

                    // Skip if no GUID and no link (can't identify uniquely)
                    if (empty($guid)) {
                        $this->logger->warning("RSS Import: Skipping item without GUID or link");
                        $itemsSkipped++;
                        continue;
                    }

                    // Check if item already exists
                    $existingItem = $this->repository->getByGuid($guid, $feedName);
                    if ($existingItem) {
                        $itemsSkipped++;
                        continue;
                    }

                    // Create new RSS item entity
                    $rssItem = new RssItem();
                    $rssItem->setFeedName($feedName);
                    $rssItem->setFeedUrl($feedUrl);
                    $rssItem->setGuid($guid);
                    $rssItem->setFetchedDate($fetchedDate);

                    // Extract fields
                    $additionalData = [];
                    foreach ($fields as $field) {
                        $value = $this->extractField($item, $field);

                        // Set known fields directly
                        switch ($field) {
                            case 'title':
                                $rssItem->setTitle($value);
                                break;
                            case 'link':
                                $rssItem->setLink($value);
                                break;
                            case 'description':
                                $rssItem->setDescription($value);
                                break;
                            case 'category':
                                $rssItem->setCategory($value);
                                break;
                            case 'pubDate':
                                $rssItem->setPubDate($value);
                                break;
                            case 'media':
                                $rssItem->setMedia($value);
                                break;
                            default:
                                // Store unknown fields in additionalData
                                $additionalData[$field] = $value;
                                break;
                        }
                    }

                    if (!empty($additionalData)) {
                        $rssItem->setAdditionalData($additionalData);
                    }

                    // Persist the item
                    $this->entityManager->persist($rssItem);
                    $itemsAdded++;
                }

                // Flush all changes
                $this->entityManager->flush();
            }

            $message = "Successfully fetched feed '{$feedName}': {$itemsAdded} items added, {$itemsSkipped} items skipped";
            $this->logger->info("RSS Import: {$message}");

            return [
                'success' => true,
                'message' => $message,
                'itemsAdded' => $itemsAdded,
                'itemsSkipped' => $itemsSkipped,
            ];

        } catch (\Exception $e) {
            $error = "Exception while fetching feed '{$feedName}': " . $e->getMessage();
            $this->logger->error("RSS Import: {$error}");
            return ['success' => false, 'message' => $error, 'itemsAdded' => 0, 'itemsSkipped' => 0];
        }
    }

    /**
     * Extract a specific field from RSS item
     *
     * @param \SimpleXMLElement $item
     * @param string $field
     * @return string
     */
    private function extractField(\SimpleXMLElement $item, string $field): string
    {
        $value = '';

        if ($field === 'media') {
            // Handle media:content or enclosure
            $namespaces = $item->getNamespaces(true);

            if (isset($namespaces['media'])) {
                $media = $item->children($namespaces['media']);
                if (isset($media->content)) {
                    $value = (string) $media->content->attributes()->url;
                } elseif (isset($media->thumbnail)) {
                    $value = (string) $media->thumbnail->attributes()->url;
                }
            }

            // Fallback to enclosure
            if (empty($value) && isset($item->enclosure)) {
                $value = (string) $item->enclosure->attributes()->url;
            }
        } elseif ($field === 'category') {
            // Handle multiple categories
            if (isset($item->category)) {
                $categories = [];
                foreach ($item->category as $cat) {
                    $categories[] = (string) $cat;
                }
                $value = implode(', ', $categories);
            }
        } else {
            $value = isset($item->$field) ? (string) $item->$field : '';
        }

        return $value;
    }

    /**
     * Get cached items for a feed
     *
     * @param string $feedName
     * @param int $limit
     * @return array
     */
    public function getCachedItems(string $feedName, int $limit = 20): array
    {
        $items = $this->repository->getRecentItemsByFeed($feedName, $limit);

        return array_map(function (RssItem $item) {
            return $item->toArray();
        }, $items);
    }

    /**
     * Clean up old items (older than specified days)
     *
     * @param int $daysToKeep
     * @return int Number of deleted items
     */
    public function cleanupOldItems(int $daysToKeep = 30): int
    {
        $beforeDate = new \DateTime("-{$daysToKeep} days");
        $deleted = $this->repository->deleteItemsBeforeDate($beforeDate);

        $this->logger->info("RSS Import: Cleaned up {$deleted} old items (older than {$daysToKeep} days)");

        return $deleted;
    }
}
