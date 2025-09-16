<?php

declare(strict_types=1);

namespace makxtr\DoctrineBatchInsert\DTO\Response;

use Doctrine\Common\Collections\ArrayCollection;

class EntityCollectionRelationsResponseObject
{
    private array $relatedEntities = [];

    private array $inverselyRelatedEntities = [];

    public function getRelatedEntities(): array
    {
        return $this->relatedEntities;
    }

    public function hasRelatedEntities(): bool
    {
        return !empty($this->relatedEntities);
    }

    public function addRelatedEntities(string $relatedClassName, array $relatedEntities): self
    {
        if (empty($this->relatedEntities[$relatedClassName])) {
            $this->relatedEntities[$relatedClassName] = new ArrayCollection();
        }

        $collection = $this->relatedEntities[$relatedClassName];

        foreach ($relatedEntities as $relatedEntity) {
            $collection->add($relatedEntity);
        }

        return $this;
    }

    public function getInverselyRelatedEntities(): array
    {
        return $this->inverselyRelatedEntities;
    }

    public function hasInverselyRelatedEntities(): bool
    {
        return !empty($this->inverselyRelatedEntities);
    }

    public function addInverselyRelatedEntities(string $relatedClassName, array $inverselyRelatedEntities): self
    {
        if (empty($this->inverselyRelatedEntities[$relatedClassName])) {
            $this->inverselyRelatedEntities[$relatedClassName] = new ArrayCollection();
        }

        $collection = $this->inverselyRelatedEntities[$relatedClassName];

        foreach ($inverselyRelatedEntities as $inverselyRelatedEntity) {
            $collection->add($inverselyRelatedEntity);
        }

        return $this;
    }
}
