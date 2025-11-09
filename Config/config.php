<?php

declare(strict_types=1);

return [
    'name'        => 'Email RSS Import',
    'description' => 'Import RSS feed items into email editor with customizable templates',
    'version'     => '1.0.0',
    'author'      => 'Frederik Wouters',

    'routes' => [
        'main' => [
            'mautic_emailrssimport_fetch' => [
                'path'       => '/emailrssimport/fetch',
                'controller' => 'MauticPlugin\MauticEmailRssImportBundle\Controller\RssController::fetchAction',
            ],
        ],
    ],

    'services' => [
        'integrations' => [
            'mautic.integration.emailrssimport' => [
                'class' => MauticPlugin\MauticEmailRssImportBundle\Integration\EmailRssImportIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
            ],
        ],
        'events' => [
            'mautic.emailrssimport.asset.subscriber' => [
                'class' => MauticPlugin\MauticEmailRssImportBundle\EventListener\AssetSubscriber::class,
            ],
        ],
    ],

    'parameters' => [],
];
