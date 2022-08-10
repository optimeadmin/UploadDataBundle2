<?php
/**
 * @author Manuel Aguirre
 */

namespace Manuel\Bundle\UploadDataBundle\Data\Exception;

use Exception;

/**
 * @author Manuel Aguirre
 */
class InvalidColumnsMatchException extends Exception
{
    protected array $invalidColumns = [];
    protected array $repeatedColumns = [];

    public function getInvalidColumns(): array
    {
        return $this->invalidColumns;
    }

    public function getRepeatedColumns(): array
    {
        return $this->repeatedColumns;
    }
}
