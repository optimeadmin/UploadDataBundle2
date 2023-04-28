<?php
/**
 * @author Manuel Aguirre
 */

declare(strict_types=1);

namespace Manuel\Bundle\UploadDataBundle\Serializer\Normalizer;

use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use Manuel\Bundle\UploadDataBundle\Entity\UploadAttribute;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Manuel Aguirre
 */
class UploadNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    const WITH_ITEMS_KEY = 'upload_with_items';

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Upload;
    }

    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if (!$object instanceof Upload) {
            throw new UnexpectedValueException("Invalid upload value");
        }

        $data = [
            'id' => $object->getId(),
            'columnsMatch' => $object->getColumnsMatch(),
            'filename' => $object->getFilename(),
            'valids' => $object->getValids(),
            'invalids' => $object->getInvalids(),
            'total' => $object->getTotal(),
            'uploadedAt' => $this->normalizer->normalize($object->getUploadedAt(), $format, $context),
            'lastCompletedAction' => $object->getLastCompletedAction()?->getName() ?? null,
            'attributes' => $this->normalizeAttributes($object),
        ];

        if ($context[self::WITH_ITEMS_KEY] ?? true) {
            $data['items'] = $this->normalizer->normalize($object->getItems(), $format, $context);
        }

        return $data;
    }

    private function normalizeAttributes(Upload $upload): array
    {
        $attributes = [];

        /** @var UploadAttribute $attr */
        foreach ($upload->getAttributes() as $attr) {
            $attributes[$attr->getName()] = $attr->getValue();
        }

        return $attributes;
    }
}