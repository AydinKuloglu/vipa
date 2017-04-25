<?php

namespace Vipa\AdminBundle\Form\Type;

use Vipa\CoreBundle\Params\PublisherStatuses;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\Publisher;
use Vipa\JournalBundle\Entity\PublisherRepository;
use Vipa\JournalBundle\Entity\SubjectRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JournalEditType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'publisher',
                'entity',
                [
                    'required' => true,
                    'label' => 'publisher',
                    'attr' => [
                        'class' => 'select2-element',
                    ],
                    'placeholder' => 'select.publisher',
                    'class' => 'VipaJournalBundle:Publisher',
                    'query_builder' => function(PublisherRepository $er) {
                        return $er->createQueryBuilder('publisher')
                            ->andWhere('publisher.status = :status')
                            ->andWhere('publisher.verified = :verified')
                            ->setParameter('status', PublisherStatuses::STATUS_COMPLETE)
                            ->setParameter('verified', true)
                            ;
                    }
                ]
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
            ->add('journalIndexs', 'collection', array(
                    'attr' => [
                        'class' => 'well',
                    ],
                    'options' => [
                        'label' => false,
                        'attr' => [
                            'class' => 'well',
                        ],
                    ],
                    'type' => new JournalIndexVerifyType(),
                    'allow_add' => false,
                    'allow_delete' => false,
                )
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
            ->add(
                'continuedAsJournal',
                'tetranz_select2entity',
                [
                    'empty_data' => null,
                    'remote_route' => 'vipa_admin_journal_autocomplete',
                    'class' => 'Vipa\JournalBundle\Entity\Journal',
                    'label' => 'continuedAsJournal',
                    'attr' => [
                        'class' => 'select2-element',
                    ],
                    'allow_clear' => true,
                    'remote_params' => [
                        'notCollectJournals' => [
                            $builder->getData()->getId()
                        ]
                    ]
                ]
            )
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
                'cascade_validation' => true,
                'validation_groups' => ['adminJournalEdit'],
            )
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'vipa_adminbundle_journal_edit_type';
    }
}
