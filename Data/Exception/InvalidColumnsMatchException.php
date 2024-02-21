<?php
/**
 * @author Manuel Aguirre
 */

namespace Manuel\Bundle\UploadDataBundle\Data\Exception;

use Exception;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Manuel Aguirre
 */
class InvalidColumnsMatchException extends Exception
{
    protected array $invalidColumns = [];
    protected array $repeatedColumns = [];
    protected ?TranslatableMessage $translatableMessage = null;

    public function getInvalidColumns(): array
    {
        return $this->invalidColumns;
    }

    public function getRepeatedColumns(): array
    {
        return $this->repeatedColumns;
    }

    public function toArray(TranslatorInterface $translator = null): array
    {
        if (!$this->translatableMessage || !$translator) {
            return ['message' => $this->message];
        }

        return ['message' => $this->translatableMessage->trans($translator)];
    }

    public function toValidationErrors(
        string $propertyPath,
        TranslatorInterface $translator = null
    ): ConstraintViolationListInterface {
        if (!$this->translatableMessage || !$translator) {
            $message = $this->message;
        } else {
            $message = $this->translatableMessage->trans($translator);
        }

        return new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $propertyPath, null),
        ]);
    }
}
