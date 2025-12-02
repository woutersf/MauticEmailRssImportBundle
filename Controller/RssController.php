<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticEmailRssImportBundle\Service\RssService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RssController extends CommonController
{
    public function fetchAction(Request $request): JsonResponse
    {
        /** @var IntegrationHelper $integrationHelper */
        $integrationHelper = $this->factory->getHelper('integration');
        $integration = $integrationHelper->getIntegrationObject('EmailRssImport');

        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return new JsonResponse(['error' => 'Integration not enabled'], 400);
        }

        $settings = $integration->getKeys();

        if (empty($settings['active'])) {
            return new JsonResponse(['error' => 'Integration not active'], 400);
        }

        // Parse available feeds
        $feeds = $this->parseFeeds($settings);

        // Get requested feed name from request
        $requestedFeed = $request->query->get('feed');

        // If requesting list of feeds
        if ($request->query->get('list_feeds')) {
            return new JsonResponse([
                'success' => true,
                'feeds' => array_keys($feeds),
            ]);
        }

        // Select the feed URL
        $rssUrl = null;
        $feedName = null;
        if ($requestedFeed && isset($feeds[$requestedFeed])) {
            $rssUrl = $feeds[$requestedFeed];
            $feedName = $requestedFeed;
        } elseif (!empty($feeds)) {
            // Use first feed if no specific feed requested
            $feedName = array_key_first($feeds);
            $rssUrl = $feeds[$feedName];
        }

        if (empty($rssUrl)) {
            return new JsonResponse(['error' => 'No RSS feed configured'], 400);
        }

        $rssFields = $settings['rss_fields'] ?? "title\nlink\ndescription\ncategory\npubDate\nmedia";
        $htmlTemplate = $settings['html_template'] ?? '';

        // Check if we should use cached items
        $useCache = $request->query->get('use_cache', false);

        if ($useCache) {
            // Fetch from cache (database)
            return $this->fetchFromCache($feedName, $htmlTemplate);
        }

        // Parse RSS fields
        $fields = array_filter(array_map('trim', explode("\n", $rssFields)));

        try {
            // Fetch RSS feed
            $xmlContent = @file_get_contents($rssUrl);

            if ($xmlContent === false) {
                return new JsonResponse(['error' => 'Failed to fetch RSS feed'], 500);
            }

            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return new JsonResponse(['error' => 'Failed to parse RSS feed', 'details' => $errors], 500);
            }

            $items = [];

            // Handle RSS 2.0 format
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $itemData = [];

                    foreach ($fields as $field) {
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

                        $itemData[$field] = $value;
                    }

                    $items[] = $itemData;
                }
            }

            return new JsonResponse([
                'success' => true,
                'items' => $items,
                'template' => $htmlTemplate,
                'feedName' => $feedName,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Exception: ' . $e->getMessage()], 500);
        }
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

        // Fallback to default if nothing configured
        if (empty($feeds)) {
            $feeds['BBC News'] = 'https://feeds.bbci.co.uk/news/rss.xml';
        }

        return $feeds;
    }

    /**
     * Fetch RSS items from cache (database)
     *
     * @param string $feedName
     * @param string $htmlTemplate
     * @return JsonResponse
     */
    private function fetchFromCache(string $feedName, string $htmlTemplate): JsonResponse
    {
        try {
            /** @var RssService $rssService */
            $rssService = $this->get('mautic.emailrssimport.service.rss');

            $items = $rssService->getCachedItems($feedName, 50);

            if (empty($items)) {
                return new JsonResponse([
                    'success' => true,
                    'items' => [],
                    'template' => $htmlTemplate,
                    'feedName' => $feedName,
                    'cached' => true,
                    'message' => 'No cached items found for this feed',
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'items' => $items,
                'template' => $htmlTemplate,
                'feedName' => $feedName,
                'cached' => true,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Exception fetching cached items: ' . $e->getMessage()], 500);
        }
    }
}
