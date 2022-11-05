<?php
/**
 * @author Manuel Aguirre
 */

declare(strict_types=1);

namespace Manuel\Bundle\UploadDataBundle\Serializer\Normalizer;

use Manuel\Bundle\UploadDataBundle\Entity\Upload;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Manuel Aguirre
 */
class UploadNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization($data, string $format = null, array $context = [])
    {
        return $data instanceof Upload;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof Upload) {
            throw new UnexpectedValueException("Invalid upload value");
        }

        return [
            'id' => $object->getId(),
            'columnsMatch' => $object->getColumnsMatch(),
            'filename' => $object->getFilename(),
            'valids' => $object->getValids(),
            'invalids' => $object->getInvalids(),
            'total' => $object->getTotal(),
            'uploadedAt' => $this->normalizer->normalize($object->getUploadedAt(), $format, $context),
            'lastCompletedAction' => $object->getLastCompletedAction()?->getName() ?? null,
            'items' => $this->normalizer->normalize($object->getItems(), $format, $context),
        ];
    }
}