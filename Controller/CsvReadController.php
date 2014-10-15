<?php

namespace Manuelj555\Bundle\UploadDataBundle\Controller;

use Manuelj555\Bundle\UploadDataBundle\Entity\Upload;
use Manuelj555\Bundle\UploadDataBundle\Entity\UploadAttribute;
use Manuelj555\Bundle\UploadDataBundle\Form\Type\AttributeType;
use Manuelj555\Bundle\UploadDataBundle\Form\Type\CsvConfigurationType;
use Symfony\Component\HttpFoundation\Request;

class CsvReadController extends BaseReadController
{

    public function separatorAction(Request $request, Upload $upload)
    {
        if (!$separatorAttribute = $upload->getAttribute('separator')) {
            $separatorAttribute = new UploadAttribute('separator', '|');
            $upload->addAttribute($separatorAttribute);
        }

        $separatorAttribute->setFormLabel('Caracter separador');

        $form = $this->createFormBuilder()
            ->setAction($request->getRequestUri())
            ->add('attributes', 'collection', array(
                'type' => new AttributeType(),
                'data' => array($separatorAttribute),
            ))
            ->add('enviar', 'submit', array(
                'attr' => array('class' => 'btn-primary'),
                'label' => 'Siguiente Paso',
            ))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($upload);
            $em->flush();

            return $this->redirect($this->generateUrl('upload_data_upload_select_columns_csv', array(
                'id' => $upload->getId(),
            )));
        }

        return $this->render('@UploadData/Read/Csv/separator.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    public function selectColumnsAction(Request $request, Upload $upload)
    {
        //la idea acá es leer las columnas del archivo y mostrarlas
        //para que el usuario haga un mapeo de ellas con las esperadas
        //por el sistema.

        $options = array(
            'delimiter' => $upload->getAttribute('separator')->getValue(),
            'row_headers' => 0,
        );

        $headers = $this->get('upload_data.csv_reader')
            ->getRowHeaders($upload->getFullFilename(), $options);

        $a = $this->getConfig($upload);
        $columnsMapper = $a->getColumnsMapper();

        $columns = $columnsMapper->getColumns();
        $matches = $columnsMapper->match($headers);

        if ($request->isMethod('POST') and $request->request->has('columns')) {

            $options['header_mapping'] = $columnsMapper
                ->mapForm($request->request->get('columns'), $headers);

            if ($attr = $upload->getAttribute('config_read')) {
                $attr->setValue($options);
            } else {
                $upload->addAttribute(new UploadAttribute('config_read', $options));
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($upload);
            $em->flush();

            $this->processRead($upload, $options);

            return Response::create('Ok', 203, array(
                'X-Close-Modal' => true,
                'X-Reload' => true,
            ));
        }

        return $this->render('@UploadData/Read/select_columns.html.twig', array(
            'file_headers' => $headers,
            'columns' => $columns,
            'matches' => $matches,
        ));
    }
}