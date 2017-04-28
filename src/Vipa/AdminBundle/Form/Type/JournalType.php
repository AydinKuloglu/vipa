<?php

namespace Vipa\AdminBundle\Form\Type;

use Vipa\CoreBundle\Params\PublisherStatuses;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\PublisherRepository;
use Vipa\JournalBundle\Entity\SubjectRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JournalType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('translations', 'a2lix_translations', [
                'fields' => [
                    'title' => [
                        'label' => 'journal.title',
                    ],
                    'subtitle' => [
                        'required' => false,
                        'label' => 'journal.subtitle',
                    ],
                    'description' => [
                        'attr' => array('class' => ' form-control wysihtml5'),
                        'field_type' => 'purified_textarea',
                        'required' => false,
                    ],
                    'titleAbbr' => [
                        'required' => false
                    ],
                    'footerText' => [
                        'label' => 'footer_text',
                        'attr' => array('class' => ' form-control wysihtml5'),
                        'field_type' => 'purified_textarea',
                        'required' => false,
                    ]
                ]
            ])
            ->add('titleTransliterated', 'text', [
                'label' => 'titleTransliterated',
                'required' => false
                ]
            )
            ->add(
                'publisher',
                'entity',
                array(
                    'class' => 'VipaJournalBundle:Publisher',
                    'query_builder' => function(PublisherRepository $er) {
                        return $er->createQueryBuilder('publisher')
                            ->andWhere('publisher.status = :status')
                            ->andWhere('publisher.verified = :verified')
                            ->setParameter('status', PublisherStatuses::STATUS_COMPLETE)
                            ->setParameter('verified', true)
                            ;
                    },
                    'attr' => ['class' => 'select2-element validate[required]'],
                    'label' => 'journal.publisher',
                    'required' => true,
                    'placeholder' => 'select.publisher',
                )
            )
            ->add(
                'accessModal',
                'choice',
                [
                    'label' => 'journal.access.modal',
                    'choices' => [
                        0 => 'open.access',
                        1 => 'access.with.subscription',
                    ],
                ]
            )
            ->add(
                'mandatoryLang',
                'entity',
                [
                    'required' => true,
                    'label' => 'mandatory.lang',
                    'placeholder' => 'select.mandatory.lang',
                    'class' => 'Vipa\JournalBundle\Entity\Lang',
                    'attr' => [
                        'class' => 'select2-element ',
                    ]
                ]
            )
            ->add(
                'languages',
                'entity',
                array(
                    'label' => 'Supported Languages',
                    'class' => 'Vipa\JournalBundle\Entity\Lang',
                    'property' => 'name',
                    'multiple' => true,
                    'expanded' => false,
                    'attr' => [
                        'class' => 'select2-element validate[required]',
                    ]
                )
            )
            ->add(
                'periods',
                'entity',
                array(
                    'label' => 'Periods',
                    'class' => 'Vipa\JournalBundle\Entity\Period',
                    'multiple' => true,
                    'expanded' => false,
                    'attr' => [
                        'class' => 'select2-element validate[required]',
                    ]
                )
            )
            ->add(
                'subjects',
                'entity',
                array(
                    'class' => 'VipaJournalBundle:Subject',
                    'multiple' => true,
                    'required' => true,
                    'property' => 'indentedSubject',
                    'label' => 'journal.subjects',
                    'attr' => [
                        'style' => 'height: 200px',
                    ],
                    'query_builder' => function(SubjectRepository $er) {
                        return $er->getChildrenQueryBuilder(null, null, 'root', 'asc', false);
                    }
                )
            )
            ->add('path', 'hidden', [
                'label' => 'journal.path',
                'required' => false
                ]
            )
            ->add('domain', 'hidden', [
                'label' => 'journal.domain',
                'required' => false
                ]
            )
            ->add('issn', 'text', [
                'label' => 'ISSN',
                'required' => false,
                'attr' => [
                    'class' => 'maskissn',
                ]
                ]
            )
            ->add('eissn', 'text', array(
                'label' => 'eISSN',
                'required' => false,
                'attr' => array(
                    'class' => 'maskissn'
                )
                )
            )
            ->add(
                'founded',
                'collot_datetime',
                array(
                    'label' => 'journal.founded',
                    'date_format' => 'yyyy',
                    'widget' => 'single_text',
                    'pickerOptions' => [
                        'format' => 'yyyy',
                        'startView' => 'decade',
                        'minView' => 'decade',
                        'todayBtn' => 'true',
                        'todayHighlight' => 'true',
                        'autoclose' => 'true',
                    ],
                )
            )
            ->add(
                'endingDate',
                'collot_datetime',
                array(
                    'required' => false,
                    'label' => 'journal.endingDate',
                    'date_format' => 'yyyy',
                    'widget' => 'single_text',
                    'pickerOptions' => [
                        'format' => 'yyyy',
                        'startView' => 'decade',
                        'minView' => 'decade',
                        'todayBtn' => 'true',
                        'todayHighlight' => 'true',
                        'autoclose' => 'true',
                    ],
                )
            )
            ->add('domain')
            ->add(
                'googleAnalyticsId',
                'text',
                [
                    'label' => 'journal.google.analytics.id',
                    'required' => false,
                ]
            )
            ->add('url', 'text', [
                'label' => 'url',
                'required' => false,
                ]
            )
            ->add(
                'country',
                'entity',
                [
                    'label' => 'country',
                    'class' => 'BulutYazilim\LocationBundle\Entity\Country',
                    'attr' => [
                        'class' => 'select2-element ',
                    ],
                ]
            )
            ->add(
                'status',
                'choice',
                [
                    'label' => 'status',
                    'choices' => Journal::$statuses,
                ]
            )
            ->add('slug', 'text', [
                'label' => 'journal.slug',
                'required' => false,
                ]
            )
            ->add('tags', 'tags')
            ->add('header', 'jb_crop_image_ajax', array(
                'endpoint' => 'journal',
                'label' => 'Header Image',
                'img_width' => 960,
                'img_height' => 200,
                'crop_options' => array(
                    'aspect-ratio' => 960 / 200,
                    'maxSize' => "[960, 200]"
                )
            ))
            ->add('image', 'jb_crop_image_ajax', array(
                'endpoint' => 'journal',
                'label' => 'journal.cover_image',
                'img_width' => 200,
                'img_height' => 300,
                'crop_options' => array(
                    'aspect-ratio' => 200 / 300,
                    'maxSize' => "[200, 300]"
                )
            ))
            ->add('note', 'textarea', [
                    'label' => 'journal.note',
                    'required' => false,
                ]
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
                'data_class' => Journal::class,
                'cascade_validation' => true
            )
        );
    }
}
