<?php

namespace Manuel\Bundle\UploadDataBundle\Entity;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UploadRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UploadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Upload::class);
    }

    public function getQueryForType($type, array $filters = [], $order = 'DESC')
    {
        $q = $this->createQueryBuilder('upload')
            ->select('upload, actions')
            ->leftJoin('upload.actions', 'actions')
            ->leftJoin('upload.attributes', 'attributes')
            ->where('upload.configClass = :type')
            ->setParameter('type', $type)
            ->orderBy('upload.id ', $order);

        $search = $filters['search'] ?? null;

        if (null !== $search && '' !== $search) {
            $q
                ->andWhere($q->expr()->orX(
                    'upload.id = :search',
                    'upload.filename LIKE :search_contains',
                    'upload.file LIKE :search_contains'
                ))
                ->setParameter('search', $search)
                ->setParameter('search_contains', '%' . $search . '%');
        }

        if ($attributes = (array)($filters['attributes'] ?? [])) {
//            $q->addSelect('CONCAT(CONCAT(attributes.name, \'---\'), attributes.value) AS HIDDEN attr_val');

            foreach ($attributes as $key => $value) {
                $alias = 'attr_' . $key;
                $keyParam = "attr_key_" . $key;
                $valueParam = "attr_value_" . $key;

                $subQ = $this
                    ->getEntityManager()
                    ->createQueryBuilder()
                    ->select($alias)
                    ->from(UploadAttribute::class, $alias)
                    ->andWhere("{$alias}.upload = upload")
                    ->andWhere("{$alias}.name = :{$keyParam}")
                    ->andWhere("{$alias}.value = :{$valueParam}");

                $q->andWhere($q->expr()->exists($subQ->getDQL()));
                $q->setParameter($keyParam, $key)->setParameter($valueParam, $value);
            }
        }

        return $q;
    }

    public function getLastForType($type, array $filters = []): ?Upload
    {
        return $this->getQueryForType($type, $filters)
            ->select('upload')
            ->orderBy('upload.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
