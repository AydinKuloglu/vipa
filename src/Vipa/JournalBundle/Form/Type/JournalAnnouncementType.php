<?php

namespace Vipa\JournalBundle\Form\Type;

use Vipa\JournalBundle\Entity\JournalAnnouncement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vipa\CoreBundle\Form\Type\JournalBasedTranslationsType;

class JournalAnnouncementType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('translations', JournalBasedTranslationsType::class,[
                'label' => ' ',
                'fields' => [
                    'title' => [
                        'required' => false
                    ],
                    'content' => [
                        'required' => false,
                        'attr' => [
                            'class' => 'form-control wysihtml5'
                        ],
                        'field_type' => 'purified_textarea'
                    ]
                ]
            ])
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JournalAnnouncement::class,
            'cascade_validation' => true
            ]
        );
    }
}