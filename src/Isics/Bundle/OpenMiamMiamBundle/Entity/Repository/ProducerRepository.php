<?php

/*
 * This file is part of the OpenMiamMiam project.
 *
 * (c) Isics <contact@isics.fr>
 *
 * This source file is subject to the AGPL v3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Isics\Bundle\OpenMiamMiamBundle\Entity\Repository;


use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Isics\Bundle\OpenMiamMiamBundle\Entity\Association;
use Isics\Bundle\OpenMiamMiamBundle\Entity\Branch;

class ProducerRepository extends EntityRepository
{
    /**
     * Finds all producers for a branch
     *
     * @param Branch  $branch Branch
     * @param integer $limit  Limit
     *
     * @return array
     */
    public function findAllRandomForBranch(Branch $branch, $limit = 5)
    {
        // Retrieve all producers ids
        $ids = $this->findAllIds($branch);
        if (empty($ids)) {
            return array();
        }

        // Randomize ids
        shuffle($ids);

        // Truncate
        array_splice($ids, $limit);

        // Retrieve producers
        $producers = $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Randomize producers
        shuffle($producers);

        return $producers;
    }

    /**
     *
     * @param Branch $branch
     * @return array
     */
    public function findAllProducer(Branch $branch)
    {
        // Retrieve all producers ids
        $ids = $this->findAllIds();
        if (empty($ids)) {
            return array();
        }

        // Randomize ids
        shuffle($ids);

        // Retrieve producers
        $producers = $this->createQueryBuilder('p')
        ->where('p.id IN (:ids)')
        ->setParameter('ids', $ids)
        ->getQuery()
        ->getResult();

        // Randomize producers
        shuffle($producers);

        return $producers;
    }


    /**
     * Finds all ids
     *
     * @param Branch $branch Branch
     *
     * @return array
     */
    public function findAllIds(Branch $branch = null)
    {
        $qb = $this->createQueryBuilder('p')->select('p.id');
        if (null !== $branch) {
            $qb->innerJoin('p.branches', 'b')
                ->andWhere('b.id = :branchId')
                ->setParameter('branchId', $branch->getId());
        }

        $flattenIds = array();
        foreach ($qb->getQuery()->getResult() as $id) {
            $flattenIds[] = $id['id'];
        }

        return $flattenIds;
    }

    /**
     * Finds ids of producers in branches
     *
     * @param mixed $branch Branch or array of branches
     *
     * @return array
     */
    public function findIdsForBranch($branch)
    {
        $flattenIds = array();
        foreach ($this->getIdsForBranchQueryBuilder($branch)->getQuery()->getResult() as $id) {
            $flattenIds[] = $id['id'];
        }

        return $flattenIds;
    }

    /**
     * Filter for branch
     *
     * @param mixed        $branch Branch or array of branches
     * @param QueryBuilder $qb     Query builder
     *
     * @return QueryBuilder
     */
    public function filterBranch($branch, QueryBuilder $qb = null)
    {
        $qb = null === $qb ? $this->createQueryBuilder('p') : $qb;

        if ($branch instanceof \ArrayAccess) {
            $branchesIds = array();
            foreach ($branch as $_branch) {
                $branchesIds[] = $_branch->getId();
            }

            $qb->innerJoin('p.branches', 'b')
                ->andWhere('b.id IN (:branchesIds)')
                ->setParameter('branchesIds', $branchesIds);

        } else {
            die('teest');
            $qb->innerJoin('p.branches', 'b', Expr\Join::WITH, $qb->expr()->eq('b', ':branch'))
                ->setParameter('branch', $branch);
        }

        return $qb;
    }

    /**
     * Returns query builder to find all producers ids of branches
     *
     * @param mixed $branch Branch or array of branches
     *
     * @return QueryBuilder
     */
    public function getIdsForBranchQueryBuilder($branch)
    {
        return $this->filterBranch($branch)
            ->select('DISTINCT p.id');
    }

    /**
     * Returns query builder to find all producer of association
     *
     * @param Association  $association
     * @param QueryBuilder $qb
     *
     * @return QueryBuilder
     */
    public function getForAssociationQueryBuilder(Association $association, QueryBuilder $qb = null)
    {
        $qb = null === $qb ? $this->createQueryBuilder('p') : $qb;

        return $qb->innerjoin('p.associations', 'a')
                ->andWhere('a.id = :associationId')
                ->setParameter('associationId', $association->getId())
                ->addOrderBy('p.name', 'ASC');
    }
}
