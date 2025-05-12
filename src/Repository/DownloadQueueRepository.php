<?php

namespace App\Repository;

use App\Entity\DownloadQueue;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DownloadQueue>
 */
class DownloadQueueRepository extends ServiceEntityRepository
{
    private const MAX_PENDING = 5;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadQueue::class);
    }

    /**
    * @return DownloadQueue[] Returns an array of DownloadQueue objects
    */
    public function getQueuedFiles(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [Status::PENDING, Status::IN_PROGRESS, Status::ERROR])
            ->setMaxResults(self::MAX_PENDING)
            ->getQuery()
            ->getResult();
    }

    /**
    * @return DownloadQueue[] Returns an array of DownloadQueue objects
    */
    public function getAdditionalQueuedFiles(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('statuses', [Status::PENDING])
            ->setMaxResults(self::MAX_PENDING)
            ->getQuery()
            ->getResult();
    }
}
