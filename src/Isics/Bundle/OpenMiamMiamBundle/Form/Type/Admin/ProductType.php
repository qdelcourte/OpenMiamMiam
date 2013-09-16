<?php

/*
 * This file is part of the OpenMiamMiam project.
 *
 * (c) Isics <contact@isics.fr>
 *
 * This source file is subject to the AGPL v3 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Isics\Bundle\OpenMiamMiamBundle\Form\Type\Admin;

use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProductType extends AbstractType
{
    /**
     * @see AbstractType
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text')
                ->add('ref', 'text')
                ->add('category', 'entity', array(
                    'class' => 'IsicsOpenMiamMiamBundle:Category',
                    'property' => 'name',
                    'empty_value' => '',
                    'query_builder' => function(EntityRepository $er) {
                        return $er->createQueryBuilder('c')->orderBy('c.name', 'ASC');
                    },
                ))
                ->add('isBio', 'choice', array(
                    'choices' => array(true => 'yes', false => 'no'),
                    'expanded' => true
                ))
                ->add('isOfTheMoment', 'choice', array(
                    'choices' => array(true => 'yes', false => 'no'),
                    'expanded' => true
                ))
                ->add('imageFile', 'file', array(
                    'required' => false
                ))
                ->add('description', 'textarea', array(
                    'required' => false
                ))
                ->add('price', 'text', array(
                    'required' => false
                ))
                ->add('price_info', 'text', array(
                    'required' => false
                ))
                ->add('Save', 'submit');

        $product = $options['data'];
        if (null !== $product->getImage()) {
            $builder->add('deleteImage', 'checkbox', array(
                'required' => false
            ));
        }
    }

    /**
     * @see AbstractType
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Isics\Bundle\OpenMiamMiamBundle\Entity\Product',
        ));
    }

    /**
     * @see AbstractType
     */
    public function getName()
    {
        return 'open_miam_miam_admin_product';
    }
}
