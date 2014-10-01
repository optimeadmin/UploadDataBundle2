<?php

namespace Manuelj555\Bundle\UploadDataBundle\Controller;

use Manuelj555\Bundle\UploadDataBundle\Entity\Upload;
use Manuelj555\Bundle\UploadDataBundle\Entity\UploadAttribute;
use Manuelj555\Bundle\UploadDataBundle\Form\Type\AttributeType;
use Manuelj555\Bundle\UploadDataBundle\Form\Type\CsvConfigurationType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcelReadController extends BaseReadController
{

    public function selectRowHeadersAction(Request $request, Upload $upload)
    {
        if (!$attr = $upload->getAttribute('row_headers')) {
            $attr = new UploadAttribute('row_headers', 1);
            $upload->addAttribute($attr);
        }

        $attr->setFormLabel('Número de Fila de las Cabeceras');

        $form = $this->createFormBuilder()
            ->setAction($request->getRequestUri())
            ->setMethod('post')
            ->add('attributes', 'collection', array(
                'type' => new AttributeType(),
                'data' => array($attr),
            ))
            ->add('preview', 'button', array(
                'attr' => array('class' => 'btn-info'),
                'label' => 'Previsualizar Fila',
            ))
            ->add('send', 'submit', array(
                'attr' => array('class' => 'btn-primary'),
                'label' => 'Siguiente Paso',
            ))
            ->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($upload);
            $em->flush();

            return $this->redirect($this->generateUrl('upload_data_upload_select_columns_excel', array(
                'id' => $upload->getId(),
            )));
        }

        return $this->render('@UploadData/Read/Excel/select_row_headers.html.twig', array(
            'form' => $form->createView(),
            'upload' => $upload,
        ));
    }

    public function previewHeadersAction(Request $request, Upload $upload)
    {
        $row = $request->get('row', 1);

        //previsualizamos las cabeceras
        $headers = $this->get('upload_data.excel_reader')
            ->getRowHeaders($upload->getFullFilename(), array(
                'row_headers' => $row,
            ));

        return $this->render('@UploadData/Read/Excel/preview_headers.html.twig', array(
            'headers' => $headers,
        ));
    }


    public function selectColumnsAction(Request $request, Upload $upload)
    {
        //la idea acá es leer las columnas del archivo y mostrarlas
        //para que el usuario haga un mapeo de ellas con las esperadas
        //por el sistema.

        $options = array(
            'row_headers' => $upload->getAttribute('row_headers')->getValue(),
        );

        $headers = $this->get('upload_data.reader_loader')
            ->get($upload->getFullFilename())
            ->getRowHeaders($upload->getFullFilename(), $options);

        $a = $this->getConfig($upload);
        $columnsMapper = $a->getColumnsMapper();

        $columns = $columnsMapper->getColumns();
        $matches = $columnsMapper->match($headers);

        if ($request->isMethod('POST') and $request->request->has('columns')) {

            $options['header_mapping'] = $columnsMapper
                ->mapForm($request->request->get('columns'), $headers);

//            var_dump($options['header_mapping']);

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
