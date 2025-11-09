<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\PluginBundle\Helper\IntegrationHelper;
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

        $rssUrl = $settings['rss_url'] ?? 'https://feeds.bbci.co.uk/news/rss.xml';
        $rssFields = $settings['rss_fields'] ?? "title\nlink\ndescription\ncategory\npubDate\nmedia";
        $htmlTemplate = $settings['html_template'] ?? '';

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
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Exception: ' . $e->getMessage()], 500);
        }
    }
}
