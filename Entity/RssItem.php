<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * Class RssItem
 *
 * Stores cached RSS feed items for automation
 *
 * @package MauticPlugin\MauticEmailRssImportBundle\Entity
 */
class RssItem
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $feedName;

    /**
     * @var string
     */
    private $feedUrl;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string|null
     */
    private $link;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var string|null
     */
    private $category;

    /**
     * @var string|null
     */
    private $pubDate;

    /**
     * @var string|null
     */
    private $media;

    /**
     * @var string|null
     */
    private $guid;

    /**
     * @var \DateTime
     */
    private $fetchedDate;

    /**
     * @var array|null
     */
    private $additionalData;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('plugin_emailrssimport_items')
            ->setCustomRepositoryClass(RssItemRepository::class);

        $builder->addIdColumns();

        $builder->createField('feedName', 'string')
            ->columnName('feed_name')
            ->length(255)
            ->build();

        $builder->createField('feedUrl', 'string')
            ->columnName('feed_url')
            ->length(500)
            ->build();

        $builder->createField('title', 'string')
            ->columnName('title')
            ->length(500)
            ->build();

        $builder->createField('link', 'string')
            ->columnName('link')
            ->length(1000)
            ->nullable()
            ->build();

        $builder->createField('description', 'text')
            ->columnName('description')
            ->nullable()
            ->build();

        $builder->createField('category', 'string')
            ->columnName('category')
            ->length(500)
            ->nullable()
            ->build();

        $builder->createField('pubDate', 'string')
            ->columnName('pub_date')
            ->length(100)
            ->nullable()
            ->build();

        $builder->createField('media', 'string')
            ->columnName('media')
            ->length(1000)
            ->nullable()
            ->build();

        $builder->createField('guid', 'string')
            ->columnName('guid')
            ->length(500)
            ->nullable()
            ->build();

        $builder->createField('fetchedDate', 'datetime')
            ->columnName('fetched_date')
            ->build();

        $builder->createField('additionalData', 'json')
            ->columnName('additional_data')
            ->nullable()
            ->build();

        $builder->addIndex(['feed_name'], 'idx_feed_name');
        $builder->addIndex(['guid'], 'idx_guid');
        $builder->addIndex(['fetched_date'], 'idx_fetched_date');
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getFeedName(): ?string
    {
        return $this->feedName;
    }

    /**
     * @param string $feedName
     * @return $this
     */
    public function setFeedName(string $feedName): self
    {
        $this->feedName = $feedName;
        return $this;
    }

    /**
     * @return string
     */
    public function getFeedUrl(): ?string
    {
        return $this->feedUrl;
    }

    /**
     * @param string $feedUrl
     * @return $this
     */
    public function setFeedUrl(string $feedUrl): self
    {
        $this->feedUrl = $feedUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param string|null $link
     * @return $this
     */
    public function setLink(?string $link): self
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @param string|null $category
     * @return $this
     */
    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPubDate(): ?string
    {
        return $this->pubDate;
    }

    /**
     * @param string|null $pubDate
     * @return $this
     */
    public function setPubDate(?string $pubDate): self
    {
        $this->pubDate = $pubDate;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMedia(): ?string
    {
        return $this->media;
    }

    /**
     * @param string|null $media
     * @return $this
     */
    public function setMedia(?string $media): self
    {
        $this->media = $media;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuid(): ?string
    {
        return $this->guid;
    }

    /**
     * @param string|null $guid
     * @return $this
     */
    public function setGuid(?string $guid): self
    {
        $this->guid = $guid;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getFetchedDate(): ?\DateTime
    {
        return $this->fetchedDate;
    }

    /**
     * @param \DateTime $fetchedDate
     * @return $this
     */
    public function setFetchedDate(\DateTime $fetchedDate): self
    {
        $this->fetchedDate = $fetchedDate;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    /**
     * @param array|null $additionalData
     * @return $this
     */
    public function setAdditionalData(?array $additionalData): self
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    /**
     * Convert entity to array format for JSON response
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'link' => $this->link,
            'description' => $this->description,
            'category' => $this->category,
            'pubDate' => $this->pubDate,
            'media' => $this->media,
        ];

        // Merge additional data if present
        if ($this->additionalData) {
            $data = array_merge($data, $this->additionalData);
        }

        return $data;
    }
}
