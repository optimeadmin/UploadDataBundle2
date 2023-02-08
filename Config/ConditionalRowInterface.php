<?php
/**
 * @author Manuel Aguirre
 */

namespace Manuel\Bundle\UploadDataBundle\Config;

/**
 * @author Manuel Aguirre
 */
interface ConditionalRowInterface
{
    public function processRow(array $rawItem, int $rowNumber): bool;
}