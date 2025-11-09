<?php

namespace MauticPlugin\MauticEmailRssImportBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AssetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    public function injectAssets(CustomAssetsEvent $event): void
    {
        $event->addScript('plugins/MauticEmailRssImportBundle/Assets/js/rss-import.js', 'bodyClose');
        $event->addStylesheet('plugins/MauticEmailRssImportBundle/Assets/css/rss-import.css');
    }
}
