<?php

namespace Vipa\JournalBundle\Form\Type;

use Vipa\CoreBundle\Form\Type\JournalBasedTranslationsType;
use Vipa\CoreBundle\Params\ArticleFileParams;
use Vipa\CoreBundle\Params\IssueFileParams;
use Vipa\JournalBundle\Entity\IssueFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vipa\CoreBundle\Form\Type\JournalLangCodeType;

class IssueFileType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', 'jb_file_ajax', array(
                'label' => 'issuefile.file',
                'endpoint' => 'issuefiles'
            ))
            ->add('type',
                'choice',
                [
                    'label' => 'issuefile.type',
                    'choices' => IssueFileParams::$FILE_TYPES,
                ])
            ->add('version', null, ['label' => 'issuefile.version'])
            ->add('langCode', JournalLangCodeType::class,
                [
                    'label' => 'issuefile.langcode'
                ]
            )
            ->add('translations', JournalBasedTranslationsType::class)
        ;
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => IssueFile::class
        ));
    }
}
