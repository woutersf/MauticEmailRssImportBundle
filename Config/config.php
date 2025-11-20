<?php

declare(strict_types=1);

return [
    'name'        => 'Email RSS Import',
    'description' => 'Import RSS feed items into email editor with customizable templates. Supports multiple feeds and daily automation.',
    'version'     => '2.0.0',
    'author'      => 'Frederik Wouters',
    'icon'        => 'plugins/MauticEmailRssImportBundle/Assets/img/rss-icon.png',

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
            'mautic.emailrssimport.cron.subscriber' => [
                'class' => MauticPlugin\MauticEmailRssImportBundle\EventListener\CronSubscriber::class,
                'arguments' => [
                    'mautic.emailrssimport.service.rss',
                    'mautic.helper.integration',
                ],
            ],
        ],
        'others' => [
            'mautic.emailrssimport.service.rss' => [
                'class' => MauticPlugin\MauticEmailRssImportBundle\Service\RssService::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'monolog.logger.mautic',
                ],
            ],
        ],
        'commands' => [
            'mautic.emailrssimport.command.fetch' => [
                'class' => MauticPlugin\MauticEmailRssImportBundle\Command\FetchRssCommand::class,
                'arguments' => [
                    'mautic.emailrssimport.service.rss',
                    'mautic.helper.integration',
                ],
                'tag' => 'console.command',
            ],
        ],
    ],

    'parameters' => [],
];
