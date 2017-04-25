<?php

namespace Vipa\JournalBundle\Form\Type;

use Vipa\CoreBundle\Form\Type\JournalBasedTranslationsType;
use Vipa\JournalBundle\Entity\Article;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\SubjectRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleSubmissionType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('articleType', 'entity', array(
                    'label' => 'article.type',
                    'class' => 'Vipa\JournalBundle\Entity\ArticleTypes',
                    'required' => false,
                    'choices' => $options['journal']->getArticleTypes(),
                )
            )
            ->add(
                'language',
                'entity',
                [
                    'label' => 'article.language.primary',
                    'class' => 'Vipa\JournalBundle\Entity\Lang',
                    'required' => false,
                    'choices' => $options['journal']->getLanguages(),
                ]
            )
            ->add('translations', JournalBasedTranslationsType::class,[
                'fields' => [
                    'title' => [
                        'field_type' => 'text'
                    ],
                    'keywords' => [
                        'required' => true,
                        'label' => 'keywords',
                        'field_type' => 'tags'
                    ],
                    'abstract' => [
                        'required' => true,
                        'label' => 'article.abstract',
                        'attr' => array('class' => ' form-control wysihtml5'),
                        'field_type' => 'purified_textarea'
                    ]
                ]
            ])
            ->add(
                'subjects',
                'entity',
                array(
                    'class' => 'VipaJournalBundle:Subject',
                    'multiple' => true,
                    'required' => true,
                    'property' => 'indentedSubject',
                    'label' => 'subjects',
                    'attr' => [
                        'style' => 'height: 100px'
                    ],
                    'choices' => $options['journal']->getSubjects(),
                )
            )
            ->add('citations', 'collection', array(
                    'type' => new CitationType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                    'options' => array(
                        'citationTypes' => $options['citationTypes']
                    ),
                    'label' => 'article.citations'
                )
            )
            ->add('articleFiles', 'collection', array(
                    'type' => new ArticleFileType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                    'label' => 'article.files'
                )
            )
            ->add('articleAuthors', 'collection', array(
                    'type' => new ArticleAuthorType(),
                    'allow_add' => true,
                    'allow_delete' => true,
                    'label' => 'article.authors'
                )
            )
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'journal' => new Journal(),
                'validation_groups' => false,
                'cascade_validation' => true,
                'data_class' => Article::class,
                'allow_delete' => true,
                'citationTypes' => [],
                'attr' => [
                    'novalidate' => 'novalidate',
                    'class' => 'form-validate',
                ],
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'vipa_article_submission';
    }
}
