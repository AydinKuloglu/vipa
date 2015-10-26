<?php

namespace Ojs\CmsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'translations',
                'a2lix_translations',
                array(
                    'fields' => array(
                        'title' => [],
                        'content' => array(
                            'required' => false,
                            'attr' => array('class' => ' form-control wysihtml5'),
                            'field_type' => 'textarea'
                        )
                    )
                )
            );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Ojs\CmsBundle\Entity\Post',
                'cascade_validation' => true,
                'object' => null,
                'objectId' => null,
                'post_type' => 'default'
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'okulbilisim_cmsbundle_post';
    }
}
