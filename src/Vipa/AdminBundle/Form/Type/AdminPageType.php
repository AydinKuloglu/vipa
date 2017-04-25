<?php

namespace Vipa\AdminBundle\Form\Type;

use Vipa\AdminBundle\Entity\AdminPage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminPageType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('translations', 'a2lix_translations', array(
                'fields' => array(
                    'title' => [],
                    'body' => array(
                        'required' => false,
                        'attr' => array('class' => ' form-control wysihtml5'),
                        'field_type' => 'purified_textarea'
                        )
                    )
                )
            )
            ->add('visible', 'checkbox', [
                'required' => false
            ]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => AdminPage::class,
                'cascade_validation' => true,
                'attr' => [
                    'class' => 'form-validate',
                ],
            )
        );
    }
}
