<?php

namespace MauticPlugin\MauticEmailRssImportBundle\Entity;

use Doctrine\ORM\EntityRepository;

/**
 * Class RssItemRepository
 *
 * @package MauticPlugin\MauticEmailRssImportBundle\Entity
 */
class RssItemRepository extends EntityRepository
{
    /**
     * Get recent items for a specific feed
     *
     * @param string $feedName
     * @param int $limit
     * @return RssItem[]
     */
    public function getRecentItemsByFeed(string $feedName, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.feedName = :feedName')
            ->setParameter('feedName', $feedName)
            ->orderBy('r.fetchedDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get item by GUID (to check for duplicates)
     *
     * @param string $guid
     * @param string $feedName
     * @return RssItem|null
     */
    public function getByGuid(string $guid, string $feedName): ?RssItem
    {
        return $this->createQueryBuilder('r')
            ->where('r.guid = :guid')
            ->andWhere('r.feedName = :feedName')
            ->setParameter('guid', $guid)
            ->setParameter('feedName', $feedName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all items from a specific feed fetched after a certain date
     *
     * @param string $feedName
     * @param \DateTime $afterDate
     * @return RssItem[]
     */
    public function getItemsByFeedAfterDate(string $feedName, \DateTime $afterDate): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.feedName = :feedName')
            ->andWhere('r.fetchedDate >= :afterDate')
            ->setParameter('feedName', $feedName)
            ->setParameter('afterDate', $afterDate)
            ->orderBy('r.fetchedDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old items (cleanup)
     *
     * @param \DateTime $beforeDate
     * @return int Number of deleted items
     */
    public function deleteItemsBeforeDate(\DateTime $beforeDate): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.fetchedDate < :beforeDate')
            ->setParameter('beforeDate', $beforeDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Get latest items from all feeds fetched today
     *
     * @return RssItem[]
     */
    public function getTodaysItems(): array
    {
        $today = new \DateTime('today');

        return $this->createQueryBuilder('r')
            ->where('r.fetchedDate >= :today')
            ->setParameter('today', $today)
            ->orderBy('r.feedName', 'ASC')
            ->addOrderBy('r.fetchedDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get count of items by feed
     *
     * @param string $feedName
     * @return int
     */
    public function getCountByFeed(string $feedName): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.feedName = :feedName')
            ->setParameter('feedName', $feedName)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
