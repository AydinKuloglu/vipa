<?php

namespace Vipa\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Events\TypeEvent;
use Vipa\CoreBundle\Params\ArticleFileParams;
use Vipa\CoreBundle\Params\ArticleStatuses;
use Vipa\CoreBundle\Service\GridAction;
use Vipa\CoreBundle\Service\OrcidService;
use Vipa\JournalBundle\Entity\Article;
use Vipa\JournalBundle\Entity\ArticleAuthor;
use Vipa\JournalBundle\Entity\ArticleFile;
use Vipa\JournalBundle\Entity\ArticleRepository;
use Vipa\JournalBundle\Entity\ArticleSubmissionFile;
use Vipa\JournalBundle\Entity\ArticleSubmissionStart;
use Vipa\JournalBundle\Entity\Author;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\JournalSubmissionFile;
use Vipa\JournalBundle\Entity\JournalUser;
use Vipa\JournalBundle\Entity\SubmissionChecklist;
use Vipa\JournalBundle\Event\Article\ArticleEvents;
use Vipa\JournalBundle\Event\JournalItemEvent;
use Vipa\JournalBundle\Form\Type\ArticlePreviewType;
use Vipa\JournalBundle\Form\Type\ArticleStartType;
use Vipa\JournalBundle\Form\Type\ArticleSubmissionType;
use Vipa\UserBundle\Entity\Role;
use Vipa\UserBundle\Entity\User;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Article Submission controller.
 *
 */
class ArticleSubmissionController extends Controller
{

    /**
     * Lists all new Article submissions entities.
     *
     * @param  Request                   $request
     * @param  bool                      $all
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request, $all = false)
    {
        $translator = $this->get('translator');
        /** @var Journal $currentJournal */
        $currentJournal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (
            ($all && !$this->isGranted('VIEW', $currentJournal, 'articles')) ||
            (!$all && !$this->isGranted('CREATE', $currentJournal, 'articles'))
        ) {
            return $this->redirect($this->generateUrl('vipa_user_index'));
        }

        $user = $this->getUser();

        $source1 = new Entity('VipaJournalBundle:Article', 'submission');
        $source2 = new Entity('VipaJournalBundle:Article', 'submission');
        $source1TableAlias = $source1->getTableAlias();
        $source2TableAlias = $source2->getTableAlias();

        $source1->manipulateQuery(
            function (QueryBuilder $qb) use ($source1TableAlias, $user, $currentJournal, $all) {
                $qb->andWhere($source1TableAlias.'.status IN (:notDraftStatuses)')
                    ->setParameter(
                        'notDraftStatuses',
                        [
                            ArticleStatuses::STATUS_REJECTED,
                            ArticleStatuses::STATUS_PUBLISH_READY,
                            ArticleStatuses::STATUS_INREVIEW,
                            ArticleStatuses::STATUS_PUBLISHED,
                        ]
                    );
                if (!$all) {
                    $qb->andWhere($source1TableAlias.'.submitterUser = :user')
                        ->setParameter('user', $user);
                }

                return $qb;
            }
        );

        $source2->manipulateQuery(
            function (QueryBuilder $qb) use ($source2TableAlias, $user, $currentJournal, $all) {
                $qb->andWhere($source2TableAlias.'.status = :status')
                    ->setParameter('status', ArticleStatuses::STATUS_NOT_SUBMITTED);
                if (!$all) {
                    $qb->andWhere($source2TableAlias.'.submitterUser = :user')
                        ->setParameter('user', $user);
                }
            }
        );

        $gridManager = $this->get('grid.manager');
        $submissionsGrid = $gridManager->createGrid('submission');
        $drafts = $gridManager->createGrid('drafts');
        $source1->manipulateRow(
            function (Row $row) use ($translator, $currentJournal) {
                /** @var Article $entity */
                $entity = $row->getEntity();
                $entity->setDefaultLocale($currentJournal->getMandatoryLang()->getCode());

                $row->setField('status', $translator->trans($entity->getStatusText()));

                $row->setField('title', $entity->getTitle());

                return $row;
            }
        );

        $source2->manipulateRow(
            function (Row $row) use ($translator, $request) {
                $entity = $row->getEntity();
                /** @var Article $entity */
                $entity->setDefaultLocale($request->getDefaultLocale());

                $row->setField('status', $translator->trans($entity->getStatusText()));
                $row->setField('title', $entity->getTitle());

                return $row;
            }
        );

        $submissionsGrid->setSource($source1);
        $drafts->setSource($source2);
        /** @var GridAction $gridAction */
        $gridAction = $this->get('grid_action');
        $rowAction = [];
        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->submissionResumeAction(
            'vipa_journal_submission_edit',
            ['journalId' => $currentJournal->getId(), 'id']
        );
        $rowAction[] = $gridAction->submissionCancelAction(
            'vipa_journal_submission_cancel',
            ['journalId' => $currentJournal->getId(), 'id']
        );
        $actionColumn->setRowActions($rowAction);
        $drafts->addColumn($actionColumn);
        $data = [
            'page' => 'submission',
            'submissions' => $submissionsGrid,
            'drafts' => $drafts,
            'all' => $all,
        ];

        return $gridManager->getGridManagerResponse('VipaJournalBundle:ArticleSubmission:index.html.twig', $data);
    }

    /**
     * @param  Request                   $request
     * @return RedirectResponse|Response
     */
    public function newAction(Request $request)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $currentTime = date('H:i:s \O\n d/m/Y');
        if ($this->submissionsNotAllowed($request) || ($journal->getEndingDate() !== null && $currentTime<$journal->getEndingDate()))  {
            return $this->respondAsNotAllowed();
        }
        $em = $this->getDoctrine()->getManager();
        $session = $this->get('session');

        if (!$session->has('submissionFiles')) {
            return $this->redirectToRoute(
                'vipa_journal_submission_start',
                array('journalId' => $journal->getId())
            );
        }

        $defaultCountryId = $this->container->getParameter('country_id');
        $defaultCountry = $em->getRepository('BulutYazilimLocationBundle:Country')->find($defaultCountryId);
        /** @var User $user */
        $user = $this->getUser();
        if (!$journal) {
            return $this->redirectToRoute('vipa_journal_user_register_list');
        }

        $article = new Article();
        $articleAuthor = new ArticleAuthor();

        $author = new Author();
        $author
            ->setUser($user)
            ->setFirstName($user->getFirstName())
            ->setLastName($user->getLastName())
            ->setEmail($user->getEmail())
            ->setAddress($user->getAddress());
        if ($defaultCountry) {
            $author->setCountry($defaultCountry);
        }

        $articleAuthor->setAuthor($author);

        $submissionSetting = $em->getRepository('VipaJournalBundle:SubmissionSetting')->findOneBy([]);
        $abstractTemplates = [];
        if($submissionSetting){
            foreach($submissionSetting->getTranslations() as $translation){
                $abstractTemplates[$translation->getLocale()] = $translation->getSubmissionAbstractTemplate();
            }
        }


        $article
            ->setSubmitterUser($user)
            ->setStatus(ArticleStatuses::STATUS_NOT_SUBMITTED)
            ->setJournal($journal)
            ->addArticleFile(new ArticleFile())
            ->addArticleAuthor($articleAuthor);

        $defaultLocale = $journal->getMandatoryLang()->getCode();
        $article->setCurrentLocale($defaultLocale);

        $form = $this->createCreateForm($article, $journal);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            $k = 1;
            foreach ($article->getArticleAuthors() as $f_articleAuthor) {
                $f_articleAuthor->setAuthorOrder($k);
                $f_articleAuthor->setArticle($article);
                $k++;
            }

            $citationCounter = 1;
            foreach ($article->getCitations() as $f_citations) {
                $f_citations->setOrderNum($citationCounter);
                $citationCounter++;
            }

            foreach ($article->getArticleFiles() as $f_articleFile) {
                $f_articleFile->setArticle($article);
                $f_articleFile->setVersion(0);
            }

            $journalSubmissionFiles = $em
                ->getRepository('VipaJournalBundle:JournalSubmissionFile')
                ->findBy(
                    [
                        'visible' => true,
                        'locale' => $request->getLocale(),
                    ]
                );

            foreach ($session->get('submissionFiles') as $fileKey => $submissionFile) {
                if (!is_null($submissionFile)) {
                    /** @var JournalSubmissionFile $journalEqualFile */
                    $journalEqualFile = $journalSubmissionFiles[$fileKey];
                    $articleSubmissionFile = new ArticleSubmissionFile();
                    $articleSubmissionFile
                        ->setTitle($journalEqualFile->getTitle())
                        ->setDetail($journalEqualFile->getDetail())
                        ->setLocale($journalEqualFile->getLocale())
                        ->setRequired($journalEqualFile->getRequired())
                        ->setFile($submissionFile)
                        ->setArticle($article);
                    $em->persist($articleSubmissionFile);
                }
            }

            if(!$article->getLanguage()){
                $article->setLanguage($journal->getMandatoryLang());
            }

            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute(
                'vipa_journal_submission_preview',
                array(
                    'journalId' => $journal->getId(),
                    'articleId' => $article->getId(),
                )
            );
        }

        return $this->render(
            'VipaJournalBundle:ArticleSubmission:new.html.twig',
            array(
                'article'           => $article,
                'journal'           => $journal,
                'form'              => $form->createView(),
                'abstractTemplates'  => $abstractTemplates,
            )
        );
    }

    /**
     * @param Request $request
     * @return bool
     */
    private function submissionsNotAllowed(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $submissionSetting = $em
            ->getRepository('VipaJournalBundle:SubmissionSetting')
            ->findOneBy([]);

        if (!$request->attributes->get('_system_setting')->isArticleSubmissionActive()
            || ($submissionSetting
            && !$submissionSetting->getSubmissionEnabled())) {

            return true;
        }

        return false;
    }

    /**
     * @return Response
     */
    private function respondAsNotAllowed()
    {
        $em = $this->getDoctrine()->getManager();
        $submissionSetting = $em->getRepository('VipaJournalBundle:SubmissionSetting')->findOneBy([]);
        $message = 'message.submission_not_available';
        if($submissionSetting
            && !empty($submissionSetting->getSubmissionCloseText())
            && !$submissionSetting->getSubmissionEnabled()
        ){
            $message = $submissionSetting->getSubmissionCloseText();
        }

        return $this->render(
            'VipaSiteBundle:Site:not_available.html.twig',
            [
                'title' => 'title.submission_new',
                'message' => $message,
            ]
        );
    }

    /**
     * @param  Article       $article
     * @param  Journal       $journal
     * @return FormInterface
     */
    private function createCreateForm(Article $article, Journal $journal)
    {
        $event = new TypeEvent(new ArticleSubmissionType());
        $this->get('event_dispatcher')->dispatch(ArticleEvents::INIT_SUBMIT_FORM, $event);

        $form = $this->createForm(
            $event->getType(),
            $article,
            array(
                'action' => $this->generateUrl(
                    'vipa_journal_submission_new',
                    array('journalId' => $journal->getId())
                ),
                'method' => 'POST',
                'journal' => $journal,
                'citationTypes' => array_keys($this->container->getParameter('citation_types')),
            )
        )
            ->add('save', 'submit', array('label' => 'save', 'attr' => array('class' => 'btn-block')));

        return $form;
    }

    /**
     * @param  Request                   $request
     * @param $id
     * @return RedirectResponse|Response
     */
    public function editAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if ($this->submissionsNotAllowed($request)) {
            return $this->respondAsNotAllowed();
        }
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $this->getUser();
        if (!$journal) {
            $this->throw404IfNotFound($journal);
        }

        /** @var ArticleRepository $articleRepository */
        $articleRepository = $em->getRepository('VipaJournalBundle:Article');
        /** @var Article $article */
        $article = $articleRepository->findOneBy(
            array(
                'id' => $id,
                'submitterUser' => $user,
                'status' => ArticleStatuses::STATUS_NOT_SUBMITTED,
            )
        );
        $this->throw404IfNotFound($article);

        $originalAuthors = new ArrayCollection();
        $originalFiles = new ArrayCollection();

        foreach ($article->getArticleAuthors() as $author) {
            $originalAuthors->add($author);
        }

        foreach ($article->getArticleFiles() as $file) {
            $originalFiles->add($file);
        }

        $form = $this->createEditForm($article, $journal);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $k = 1;
            foreach ($article->getArticleAuthors() as $f_articleAuthor) {
                $f_articleAuthor->setAuthorOrder($k);
                $f_articleAuthor->setArticle($article);
                $k++;
            }
            $i = 1;
            foreach ($article->getCitations() as $f_citations) {
                $f_citations->setOrderNum($i);
                $i++;
            }

            foreach ($originalAuthors as $author) {
                if ($article->getArticleAuthors()->contains($author) === false) {
                    $em->remove($author);
                }
            }

            foreach ($originalFiles as $file) {
                if ($article->getArticleFiles()->contains($file) === false) {
                    $em->remove($file);
                }
            }

            foreach ($article->getArticleFiles() as $f_articleFile) {
                $f_articleFile->setVersion(0);
                $f_articleFile->setArticle($article);
            }

            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute(
                'vipa_journal_submission_preview',
                array('journalId' => $journal->getId(), 'articleId' => $article->getId())
            );
        }

        return $this->render(
            'VipaJournalBundle:ArticleSubmission:edit.html.twig',
            array(
                'article' => $article,
                'journal' => $journal,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @param Article $article
     * @param Journal $journal
     * @return $this|FormInterface
     */
    private function createEditForm(Article $article, Journal $journal)
    {
        $event = new TypeEvent(new ArticleSubmissionType());
        $this->get('event_dispatcher')->dispatch(ArticleEvents::INIT_SUBMIT_FORM, $event);

        $form = $this->createForm(
            $event->getType(),
            $article,
            array(
                'action' => $this->generateUrl(
                    'vipa_journal_submission_edit',
                    array('journalId' => $journal->getId(), 'id' => $article->getId())
                ),
                'method' => 'POST',
                'journal' => $journal,
                'citationTypes' => array_keys($this->container->getParameter('citation_types')),
            )
        )
            ->add('save', 'submit', array('label' => 'save', 'attr' => array('class' => 'btn-block')));

        return $form;
    }

    /**
     * @param  Request                   $request
     * @param $articleId
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function previewAction(Request $request, $articleId)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if ($this->submissionsNotAllowed($request)) {
            return $this->respondAsNotAllowed();
        }
        $em = $this->getDoctrine()->getManager();
        $dispatcher = $this->get('event_dispatcher');
        $session = $this->get('session');

        /** @var User $user */
        $user = $this->getUser();
        if (!$journal) {
            $this->throw404IfNotFound($journal);
        }

        /** @var ArticleRepository $articleRepository */
        $articleRepository = $em->getRepository('VipaJournalBundle:Article');
        /** @var Article $article */
        $article = $articleRepository->findOneBy(
            array(
                'id' => $articleId,
                'submitterUser' => $user,
                'status' => ArticleStatuses::STATUS_NOT_SUBMITTED,
            )
        );
        $this->throw404IfNotFound($article);

        $form = $this->createForm(
            new ArticlePreviewType(),
            $article,
            array(
                'action' => $this->generateUrl(
                    'vipa_journal_submission_preview',
                    array('journalId' => $journal->getId(), 'articleId' => $article->getId())
                ),
                'method' => 'POST',
            )
        )
        ->add('submit', 'submit', array('label' => 'article.submit', 'attr' => ['class' => 'btn-block']));
        $form->handleRequest($request);

        $validator = $this->get('validator');
        $draftErrors = $validator->validate($article, null, ['groups' => 'submission']);

        $submissionSetting = $em->getRepository('VipaJournalBundle:SubmissionSetting')->findOneBy([]);

        if ($form->isValid() && count($draftErrors) == 0) {
            if ($session->has('submissionFiles')) {
                $session->remove('submissionFiles');
            }

            $article->setStatus(ArticleStatuses::STATUS_PUBLISH_READY);
            $article->setSubmissionDate(new \DateTime());
            $em->persist($article);

            // Assign user to author journal role
            /** @var Role $role */
            $role = $em
                ->getRepository('VipaUserBundle:Role')
                ->findOneBy(['role' => 'ROLE_AUTHOR']);

            /** @var JournalUser $journalUser */
            $journalUser = $em->getRepository('VipaJournalBundle:JournalUser')->findOneBy(
                array(
                    'user' => $user,
                )
            );
            if (!$journalUser) {
                $journalUser = new JournalUser();
                $journalUser->setJournal($journal)
                    ->setUser($user);
            }
            $journalUser->addRole($role);
            $em->persist($journalUser);

            $em->flush();

            $response = $this->redirectToRoute(
                'vipa_journal_submission_me',
                ['journalId' => $article->getJournal()->getId()]
            );

            $event = new JournalItemEvent($article);
            $dispatcher->dispatch(ArticleEvents::POST_SUBMIT, $event);

            if (null !== $event->getResponse()) {
                return $event->getResponse();
            }

            return $response;
        }

        return $this->render(
            'VipaJournalBundle:ArticleSubmission:preview.html.twig',
            array(
                'article' => $article,
                'journal' => $journal,
                'translations' => $article->getTranslations(),
                'fileTypes' => ArticleFileParams::$FILE_TYPES,
                'form' => $form->createView(),
                'submissionSetting' => $submissionSetting,
                'draftErrors' => $draftErrors,
            )
        );
    }

    /**
     * @param  Request                   $request
     * @return RedirectResponse|Response
     */
    public function startAction(Request $request)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $em = $this->getDoctrine();
        $currentTime = date('H:i:s \O\n d/m/Y');
        if ($this->submissionsNotAllowed($request) || ($journal->getEndingDate() !== null && $currentTime<$journal->getEndingDate()))  {
            return $this->respondAsNotAllowed();
        }
        $session = $this->get('session');

        if (!$journal) {
            $this->throw404IfNotFound($journal);
        }

        /** @var SubmissionChecklist[] $checkLists */
        $checkLists = [];
        $checkListsChoices = [];
        foreach ($journal->getSubmissionChecklist() as $checkList) {
            if (
                $checkList->getVisible()
                && ($checkList->getLocale() === $request->getLocale() || empty($checkList->getLocale()))
            ) {
                $checkLists[] = $checkList;
                $checkListsChoices[$checkList->getId()] = $checkList->getId();
            }
        }

        $entity = new ArticleSubmissionStart();
        $journalSubmissionFiles = $em->getRepository('VipaJournalBundle:JournalSubmissionFile')
            ->findBy(
                [
                    'visible' => true,
                    'locale' => $request->getLocale(),
                    'journal' => $journal,
                ]
            );
        foreach ($journalSubmissionFiles as $file) {
            $fileEntity = new ArticleSubmissionFile();
            $entity->addArticleSubmissionFile($fileEntity);
        }
        $form = $this->createStartForm($checkListsChoices, $entity);
        $form->handleRequest($request);

        $submissionFiles = [];
        if ($form->isValid() && $form->isSubmitted()) {
            foreach ($entity->getArticleSubmissionFiles() as $fileKey => $submissionFile) {
                if (empty($submissionFile->getFile()) && $journalSubmissionFiles[$fileKey]->getRequired()) {
                    $this->errorFlashBag('all.required.files.must.be.uploaded');
                    return $this->render(
                        'VipaJournalBundle:ArticleSubmission:start.html.twig',
                        array(
                            'journal' => $journal,
                            'checkLists' => $checkLists,
                            'journalSubmissionFiles' => $journalSubmissionFiles,
                            'form' => $form->createView(),
                        )
                    );
                }
                $submissionFiles[$fileKey] = $submissionFile->getFile();
            }
            $session->set('submissionFiles', $submissionFiles);

            return $this->redirectToRoute('vipa_journal_submission_new', array('journalId' => $journal->getId()));
        }

        return $this->render(
            'VipaJournalBundle:ArticleSubmission:start.html.twig',
            array(
                'journal' => $journal,
                'checkLists' => $checkLists,
                'journalSubmissionFiles' => $journalSubmissionFiles,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @param  array                  $checkListsChoices
     * @param  ArticleSubmissionStart $entity
     * @return FormInterface
     */
    private function createStartForm(array $checkListsChoices, ArticleSubmissionStart $entity)
    {
        $form = $this->createForm(
            new ArticleStartType(),
            $entity,
            array(
                'checkListsChoices' => $checkListsChoices,
                'method' => 'POST',
            )
        )
            ->add('save', 'submit', array('label' => 'save.next', 'attr' => array('class' => 'btn-block')));

        return $form;
    }

    /**
     * Returns requested orcid user profile details
     * @param  Request      $request
     * @return JsonResponse
     * @throws \Exception
     *
     */
    public function getOrcidAuthorAction(Request $request)
    {
        $getAuthor = null;
        if ($request->get('orcidAuthorId')) {
            $orcidAuthorId = $request->get('orcidAuthorId');
            $orcidService = new OrcidService();
            $getAuthor = $orcidService->getBio($orcidAuthorId);
        }
        $response = new JsonResponse();
        $response->setData($getAuthor);

        return $response;
    }

    /**
     * @param $id
     * @return RedirectResponse
     *
     */
    public function cancelAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Article $article */
        $article = $em->getRepository('VipaJournalBundle:Article')->findOneBy(
            array(
                'submitterUser' => $this->getUser(),
                'id' => $id,
                'status' => ArticleStatuses::STATUS_NOT_SUBMITTED,
            )
        );
        $this->throw404IfNotFound($article);
        //remove article 's article files relational items
        foreach ($article->getArticleFiles() as $file) {
            $article->removeArticleFile($file);
            $file->setArticle(null);
            $em->persist($file);
            $em->remove($file);
        }
        //remove article 's article authors relational items
        foreach ($article->getArticleAuthors() as $articleAuthor) {
            $article->removeArticleAuthor($articleAuthor);
            $articleAuthor->setArticle(null);
            $em->persist($articleAuthor);
            $em->remove($articleAuthor);
        }
        //remove article 's article submission files relational items
        foreach ($article->getArticleSubmissionFiles() as $submissionFile) {
            $article->removeArticleSubmissionFile($submissionFile);
        }
        //remove article 's article citations relational items
        foreach ($article->getCitations() as $citation) {
            $article->removeCitation($citation);
            $citation->removeArticle($article);
            $em->persist($citation);
            $em->remove($citation);
        }
        //remove article 's article attributes relational items
        foreach ($article->getAttributes() as $attribute) {
            $article->removeAttribute($attribute);
        }
        $em->remove($article);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('vipa_user_index');
    }
}
