<?php
/**
 * @author Manuel Aguirre
 */

namespace Manuel\Bundle\UploadDataBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @author Manuel Aguirre
 */
class ExcelFileType extends AbstractType
{
    public function getParent()
    {
        return FileType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('mime_types', [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
        $resolver->setDefault(
            'mime_types_message',
            (new File())->mimeTypesMessage,
        );

        $resolver->setDefault('constraints', function (Options $options) {
            return [
                new NotBlank(),
                new File([
                    'mimeTypes' => $options['mime_types'],
                    'mimeTypesMessage' => $options['mime_types_message'],
                ]),
            ];
        });
    }
}
