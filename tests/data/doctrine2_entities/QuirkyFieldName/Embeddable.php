<?php

namespace QuirkyFieldName;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable
 */
#[ORM\Embeddable]
class Embeddable
{
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $val = null;
}
