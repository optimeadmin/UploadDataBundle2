<?php
/**
 * @author Manuel Aguirre
 */

namespace Manuel\Bundle\UploadDataBundle\Data\Exception;

use Exception;
use Symfony\Component\Translation\TranslatableMessage;
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
}
