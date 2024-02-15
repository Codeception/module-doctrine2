<?php

namespace MultilevelRelations;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class C
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $name = null;

    /**
     * @ORM\ManyToOne(targetEntity="B")
     */
    #[ORM\ManyToOne(targetEntity: B::class)]
    private ?B $b = null;

    public function getB(): ?B
    {
        return $this->b;
    }
}
