<?php

namespace Manuel\Bundle\UploadDataBundle\Config;

use Manuel\Bundle\UploadDataBundle\Data\RawUploadedItem;
use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;

abstract class RawUploadConfig extends UploadConfig
{
    public function transfer(Upload $upload): void
    {
    }

    abstract public function transferRaw(Upload $upload, iterable $items): void;

    public function validateRawRow(
        RawUploadedItem $item,
        ContextualValidatorInterface $context,
        Upload $upload,
    ): void {
    }
}
