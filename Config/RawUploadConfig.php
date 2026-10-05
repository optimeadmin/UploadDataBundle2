<?php

namespace Manuel\Bundle\UploadDataBundle\Config;

use Manuel\Bundle\UploadDataBundle\Data\RawUploadedItem;
use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use Manuel\Bundle\UploadDataBundle\Validator\GroupedConstraintViolations;

abstract class RawUploadConfig extends UploadConfig
{
    public function transfer(Upload $upload): void
    {
    }

    abstract public function transferRaw(Upload $upload, iterable $items): void;

    public function validateRawRow(
        RawUploadedItem $item,
        Upload $upload,
        GroupedConstraintViolations $errors,
    ): void {
    }
}
