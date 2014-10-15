<?php
/**
 * 28/09/14
 * upload
 */

namespace Manuelj555\Bundle\UploadDataBundle\Mapper\ColumnList;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;


/**
 * @autor Manuel Aguirre <programador.manuel@gmail.com>
 */
class ActionColumn extends AbstractColumn
{

    public function getType()
    {
        return 'action';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver); // TODO: Change the autogenerated stub

        $resolver->setDefaults(array(
            'template' => '@UploadData/Default/column_action.html.twig',
            'use_show' => true,
            'modal' => false,
        ));

        $resolver->setRequired(array(
            'route',
            'status',
        ));

        $resolver->setAllowedTypes(array(
            'status' => 'Closure',
        ));

        $resolver->setOptional(array('condition', 'modal_route'));
    }
}