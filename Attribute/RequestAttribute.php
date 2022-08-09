<?php
/**
 * @author Manuel Aguirre
 */

declare(strict_types=1);

namespace Manuel\Bundle\UploadDataBundle\Attribute;

use Attribute;

/**
 * @author Manuel Aguirre
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class RequestAttribute
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }
}