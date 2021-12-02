<?php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class NonTypicalPrimaryKeyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     */
    private string $primaryKey;
}
