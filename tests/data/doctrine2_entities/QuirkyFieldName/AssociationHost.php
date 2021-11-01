<?php

namespace QuirkyFieldName;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class AssociationHost
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity="Association")
     */
    private ?Association $assoc = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $_assoc_val = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
