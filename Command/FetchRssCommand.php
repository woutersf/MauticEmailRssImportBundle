<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Command;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticEmailRssImportBundle\Service\RssService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class FetchRssCommand
 *
 * Command to fetch RSS feeds and store them in the database
 *
 * @package MauticPlugin\MauticEmailRssImportBundle\Command
 */
class FetchRssCommand extends Command
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
     * FetchRssCommand constructor.
     *
     * @param RssService $rssService
     * @param IntegrationHelper $integrationHelper
     */
    public function __construct(RssService $rssService, IntegrationHelper $integrationHelper)
    {
        parent::__construct();
        $this->rssService = $rssService;
        $this->integrationHelper = $integrationHelper;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('mautic:rss:fetch')
            ->setDescription('Fetch RSS feeds and store items in database')
            ->addOption(
                'cleanup',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clean up old items (older than X days). Default: 30',
                30
            )
            ->setHelp(
                <<<'EOT'
The <info>%command.name%</info> command fetches all configured RSS feeds and stores them in the database:

<info>php %command.full_name%</info>

You can also clean up old items by specifying the number of days to keep:

<info>php %command.full_name% --cleanup=60</info>

This command is designed to be run daily via cron for automated RSS feed imports.
EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('RSS Feed Fetcher');

        // Get integration settings
        $integration = $this->integrationHelper->getIntegrationObject('EmailRssImport');

        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            $io->error('Email RSS Import integration is not enabled');
            return Command::FAILURE;
        }

        $settings = $integration->getKeys();

        if (empty($settings['active'])) {
            $io->error('Email RSS Import integration is not active');
            return Command::FAILURE;
        }

        // Parse feeds
        $feeds = $this->parseFeeds($settings);

        if (empty($feeds)) {
            $io->warning('No RSS feeds configured');
            return Command::SUCCESS;
        }

        $io->section('Configured Feeds');
        $io->listing(array_map(function ($name, $url) {
            return "{$name}: {$url}";
        }, array_keys($feeds), $feeds));

        // Parse RSS fields
        $rssFields = $settings['rss_fields'] ?? "title\nlink\ndescription\ncategory\npubDate\nmedia";
        $fields = array_filter(array_map('trim', explode("\n", $rssFields)));

        $io->section('Fetching Feeds');

        $totalAdded = 0;
        $totalSkipped = 0;
        $errors = [];

        foreach ($feeds as $feedName => $feedUrl) {
            $io->write("Fetching '{$feedName}'... ");

            $result = $this->rssService->fetchAndStoreFeed($feedName, $feedUrl, $fields);

            if ($result['success']) {
                $io->writeln("<info>✓</info> {$result['itemsAdded']} added, {$result['itemsSkipped']} skipped");
                $totalAdded += $result['itemsAdded'];
                $totalSkipped += $result['itemsSkipped'];
            } else {
                $io->writeln("<error>✗</error> {$result['message']}");
                $errors[] = "{$feedName}: {$result['message']}";
            }
        }

        // Summary
        $io->section('Summary');
        $io->success("Total items added: {$totalAdded}");
        $io->info("Total items skipped: {$totalSkipped}");

        if (!empty($errors)) {
            $io->section('Errors');
            $io->listing($errors);
        }

        // Cleanup old items if requested
        $cleanupDays = (int) $input->getOption('cleanup');
        if ($cleanupDays > 0) {
            $io->section('Cleanup');
            $io->write("Removing items older than {$cleanupDays} days... ");

            $deleted = $this->rssService->cleanupOldItems($cleanupDays);

            $io->writeln("<info>✓</info> {$deleted} items deleted");
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
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
}
