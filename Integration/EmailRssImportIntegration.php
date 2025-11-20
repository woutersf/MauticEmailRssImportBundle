<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Integration;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;

class EmailRssImportIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'EmailRssImport';
    }

    /**
     * Return's authentication method such as oauth2, oauth1a, key, etc.
     */
    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * Get the path to the integration icon
     */
    public function getIcon(): string
    {
        return 'plugins/MauticEmailRssImportBundle/Assets/img/rss-icon.png';
    }

    /**
     * Return array of key => label elements that will be converted to inputs to
     * obtain from the user.
     *
     * @return array<string, string>
     */
    public function getRequiredKeyFields(): array
    {
        return [];
    }

    /**
     * @param FormBuilder|Form $builder
     * @param array            $data
     * @param string           $formArea
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('keys' === $formArea) {

            // RSS Feeds textarea - supports multiple feeds
            $defaultFeeds = "BBC News|https://feeds.bbci.co.uk/news/rss.xml\nTechCrunch|https://techcrunch.com/feed/";
            $builder->add(
                'rss_feeds',
                TextareaType::class,
                [
                    'label' => 'mautic.plugin.emailrssimport.rss_feeds',
                    'data'  => $data['rss_feeds'] ?? $defaultFeeds,
                    'attr'  => [
                        'class'   => 'form-control',
                        'rows'    => 10,
                        'tooltip' => 'mautic.plugin.emailrssimport.rss_feeds.tooltip',
                        'placeholder' => 'Feed Name|https://example.com/rss.xml',
                    ],
                    'required' => false,
                    'help'     => 'mautic.plugin.emailrssimport.rss_feeds.help',
                ]
            );

            // RSS Fields textarea
            $defaultFields = "title\nlink\ndescription\ncategory\npubDate\nmedia";
            $builder->add(
                'rss_fields',
                TextareaType::class,
                [
                    'label' => 'mautic.plugin.emailrssimport.rss_fields',
                    'data'  => $data['rss_fields'] ?? $defaultFields,
                    'attr'  => [
                        'class'   => 'form-control',
                        'rows'    => 6,
                        'tooltip' => 'mautic.plugin.emailrssimport.rss_fields.tooltip',
                    ],
                    'required' => false,
                ]
            );

            // HTML Template textarea
            $defaultTemplate = '<mj-section background-color="#ffffff" padding-top="25px" padding-bottom="0">
      <mj-column width="100%">
        <mj-image src="{media}" alt="{title}" padding-top="0" padding-bottom="20px"></mj-image>
        <mj-text color="#000000" font-family="Ubuntu, Helvetica, Arial, sans-serif" font-size="20px" line-height="1.5" font-weight="500" padding-bottom="0px">
          <p>{title}</p>
        </mj-text>
        <mj-text color="#000000" font-family="Ubuntu, Helvetica, Arial, sans-serif" font-size="16px" line-height="1.5" font-weight="300" align="justify">
          <p>{description}</p>
        </mj-text>
        <mj-button background-color="#486AE2" color="#FFFFFF" href="{link}" font-family="Ubuntu, Helvetica, Arial, sans-serif" padding-top="20px" padding-bottom="40px">READ MORE</mj-button>
        <mj-text color="#666666" font-family="Ubuntu, Helvetica, Arial, sans-serif" font-size="12px">
          <p>{category} - {pubDate}</p>
        </mj-text>
      </mj-column>
    </mj-section>';

            $builder->add(
                'html_template',
                TextareaType::class,
                [
                    'label' => 'mautic.plugin.emailrssimport.html_template',
                    'data'  => $data['html_template'] ?? $defaultTemplate,
                    'attr'  => [
                        'class'   => 'form-control',
                        'rows'    => 20,
                        'tooltip' => 'mautic.plugin.emailrssimport.html_template.tooltip',
                    ],
                    'required' => false,
                    'help'     => 'mautic.plugin.emailrssimport.html_template.help',
                ]
            );
        }
    }

    public function isConfigured(): bool
    {
        $keys = $this->getKeys();

        return !empty($keys['active']);
    }
}
