<?php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
#[ORM\Entity]
class CompositePrimaryKeyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $integerPart;

    /**
     *
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $stringPart;
}
