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
            ->select('upload, actions, HIDDEN attributes.id')
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
//            foreach ($attributes as $key => $value) {
//                $joinKey = 'attr' . $key;
//                $keyParam = "attr_key_" . $key;
//                $valueParam = "attr_value_" . $key;
//                $q->join(
//                    'upload.attributes',
//                    'attr' . $key,
//                    'with',
//                    "{$joinKey}.name = :{$keyParam} AND {$joinKey}.value = :{$valueParam}"
//                )->setParameter($keyParam, $key)->setParameter($valueParam, $value);
//            }
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

    /**
     * @param mixed $id
     * @param int $lockMode
     * @param null $lockVersion
     *
     * @return mixed|null|Upload
     */
    public function find($id, $lockMode = LockMode::NONE, $lockVersion = null)
    {
        return $this->createQueryBuilder('upload')
            ->select('upload, actions, attributes')
            ->leftJoin('upload.actions', 'actions')
            ->leftJoin('upload.attributes', 'attributes')
            ->where('upload.id = :id')
            ->setParameter('id', $id, \PDO::PARAM_INT)
            ->getQuery()
            ->getOneOrNullResult();
    }


}
