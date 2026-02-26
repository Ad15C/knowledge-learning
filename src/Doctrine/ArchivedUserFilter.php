<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class ArchivedUserFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if ($targetEntity->getReflectionClass()->getName() !== \App\Entity\User::class) {
            return '';
        }

        return sprintf('%s.archived_at IS NULL', $targetTableAlias);
    }
}