<?php
/**
 * @author Manuel Aguirre
 */

declare(strict_types=1);

namespace Manuel\Bundle\UploadDataBundle\Serializer\Normalizer;

use Manuel\Bundle\UploadDataBundle\Entity\UploadedItem;
use stdClass;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Manuel Aguirre
 */
class UploadedItemNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const GROUP_NAME = 'uploaded_item.group_name';
    public const GROUP_ERRORS = 'uploaded_item.group_errors';

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof UploadedItem;
    }

    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if (!$object instanceof UploadedItem) {
            throw new UnexpectedValueException("Invalid uploadedItem value");
        }

        $groupName = $context[self::GROUP_NAME] ?? null;
        $grouped = $context[self::GROUP_ERRORS] ?? false;

        $errors = $object->getErrors()->getAll($groupName, $grouped);
        $errorsCount = count($errors);

        return [
            'id' => $object->getId(),
            'fileRowNumber' => $object->getFileRowNumber(),
            'data' => $object->getData(),
            'extras' => $object->getExtras(),
            'isValid' => $errorsCount === 0,
            'errors' => $this->normalizer->normalize(
                $errorsCount > 0 ? $errors : new stdClass(), // para que siempre retorne un objeto json.
                $format,
                $context + [
                    AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
                ]
            ),
        ];
    }
}