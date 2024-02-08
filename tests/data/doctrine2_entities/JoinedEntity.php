<?php

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class JoinedEntity extends JoinedEntityBase
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $own = null;
}
