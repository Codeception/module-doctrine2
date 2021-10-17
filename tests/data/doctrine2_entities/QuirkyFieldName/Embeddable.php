<?php

namespace QuirkyFieldName;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable
 */
class Embeddable
{
    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $val = null;
}
