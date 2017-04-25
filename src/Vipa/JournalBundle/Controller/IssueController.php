<?php

namespace Vipa\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\ORM\Query;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Params\ArticleStatuses;
use Vipa\JournalBundle\Entity\Article;
use Vipa\JournalBundle\Entity\ArticleRepository;
use Vipa\JournalBundle\Entity\Issue;
use Vipa\JournalBundle\Entity\Section;
use Vipa\JournalBundle\Event\Issue\IssueEvents;
use Vipa\JournalBundle\Event\JournalEvent;
use Vipa\JournalBundle\Event\JournalItemEvent;
use Vipa\JournalBundle\Event\ListEvent;
use Vipa\JournalBundle\Form\Type\IssueType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * Issue controller.
 *
 */
class IssueController extends Controller
{
    /**
     * Lists all Issue entities.
     *
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('VIEW', $journal, 'issues')) {
            throw new AccessDeniedException("You not authorized for this page!");
        }
        $source = new Entity('VipaJournalBundle:Issue');
        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction('vipa_journal_issue_show', ['id', 'journalId' => $journal->getId()]);

        $articleAction = new RowAction('<i class="fa fa-file-text"></i>', 'vipa_journal_issue_view');
        $articleAction->setRouteParameters(['journalId' => $journal->getId(), 'id']);
        $articleAction->setAttributes(
            [
                'class' => 'btn btn-success btn-xs  ',
                'data-toggle' => 'tooltip',
                'title' => $this->get('translator')->trans("Articles"),
            ]
        );

        $rowAction[] = $articleAction;

        $lastIssueAction = new RowAction('<i class="fa fa-cog"></i>', 'vipa_journal_issue_make_last');
        $lastIssueAction->setRouteParameters(['journalId' => $journal->getId(), 'id']);
        $lastIssueAction->setAttributes(
            [
                'class' => 'btn btn-success btn-xs  ',
                'data-toggle' => 'tooltip',
                'title' => $this->get('translator')->trans("make.last.issue"),
            ]
        );

        $rowAction[] = $lastIssueAction;
        if ($this->isGranted('EDIT', $journal, 'issues')) {
            $rowAction[] = $gridAction->editAction('vipa_journal_issue_edit', ['id', 'journalId' => $journal->getId()]);
        }

        if ($this->isGranted('DELETE', $journal, 'issues')) {
            $rowAction[] = $gridAction->deleteAction(
                'vipa_journal_issue_delete',
                ['id', 'journalId' => $journal->getId()]
            );
        }
        $rowAction[] = $gridAction->filesAction(
            'vipa_journal_issue_file_index',
            ['journalId' => $journal->getId(), 'id']
        );

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        $listEvent = new ListEvent();
        $listEvent->setGrid($grid);
        $eventDispatcher->dispatch(IssueEvents::LISTED, $listEvent);
        $grid = $listEvent->getGrid();

        return $grid->getGridResponse('VipaJournalBundle:Issue:index.html.twig');
    }

    /**
     * Creates a new Issue entity.
     *
     * @param   Request $request
     * @return  RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('CREATE', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for create a issue on this journal!");
        }

        $entity = new Issue();
        $form = $this->createCreateForm($entity, $journal->getId());
        $form->handleRequest($request);

        if ($form->isValid()) {
            $entity->setJournal($journal);
            $entity->setCurrentLocale($request->getDefaultLocale());

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(IssueEvents::PRE_CREATE, $event);

            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(IssueEvents::POST_CREATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.create');

            return $this->redirectToRoute(
                'vipa_journal_issue_show',
                ['id' => $entity->getId(), 'journalId' => $journal->getId()]
            );
        }

        return $this->render(
            'VipaJournalBundle:Issue:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a Issue entity.
     *
     * @param   Issue   $entity     The entity
     * @param   integer $journalId
     * @return \Symfony\Component\Form\Form The form
     */
    private function createCreateForm(Issue $entity, $journalId)
    {
        $form = $this->createForm(
            new IssueType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_journal_issue_create', ['journalId' => $journalId]),
                'method' => 'POST',
            )
        );

        return $form;
    }

    /**
     * Displays a form to create a new Issue entity.
     *
     * @return  Response
     */
    public function newAction()
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('CREATE', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for create a issue on this journal!");
        }

        $entity = new Issue();
        $form = $this->createCreateForm($entity, $journal->getId());

        return $this->render(
            'VipaJournalBundle:Issue:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a Issue entity.
     *
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function showAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('VIEW', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for view this journal's issue!");
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Issue $entity */
        $entity = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($entity);
        $entity->setDefaultLocale($request->getDefaultLocale());
        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_journal_issue'.$entity->getId());

        return $this->render(
            'VipaJournalBundle:Issue:show.html.twig',
            array(
                'entity' => $entity,
                'token' => $token,
            )
        );
    }

    /**
     * Displays a form to edit an existing Issue entity.
     *
     * @param   int $id
     * @return  Response
     */
    public function editAction($id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's issue!");
        }

        $em = $this->getDoctrine()->getManager();
        /** @var Issue $entity */
        $entity = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity, $journal->getId());


        return $this->render(
            'VipaJournalBundle:Issue:edit.html.twig',
            [
                'entity' => $entity,
                'edit_form' => $editForm->createView()
            ]
        );
    }

    /**
     * Creates a form to edit a Issue entity.
     * @param   Issue $entity The entity
     * @param   integer $journalId
     * @return  \Symfony\Component\Form\Form The form
     */
    private function createEditForm(Issue $entity, $journalId)
    {
        $form = $this->createForm(
            new IssueType(),
            $entity,
            array(
                'action' => $this->generateUrl(
                    'vipa_journal_issue_update',
                    ['id' => $entity->getId(), 'journalId' => $journalId]
                ),
                'method' => 'PUT',
            )
        );

        return $form;
    }

    /**
     * Edits an existing Issue entity.
     *
     * @param   Request $request
     * @param   $id
     * @return  RedirectResponse|Response
     */
    public function updateAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's issue!");
        }
        $em = $this->getDoctrine()->getManager();
        /** @var Issue $entity */
        $entity = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity, $journal->getId());
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(IssueEvents::PRE_UPDATE, $event);
            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(IssueEvents::POST_UPDATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.update');

            return $this->redirectToRoute(
                'vipa_journal_issue_edit',
                ['journalId' => $entity->getJournal()->getId(), 'id' => $id]
            );
        }

        return $this->render(
            'VipaJournalBundle:Issue:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @param   Request $request
     * @param   int     $id
     * @return  RedirectResponse
     */
    public function deleteAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('DELETE', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for delete this journal's issue!");
        }

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($entity);

        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('vipa_journal_issue'.$id);

        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }

        $event = new JournalItemEvent($entity);
        $eventDispatcher->dispatch(IssueEvents::PRE_DELETE, $event);

        // We are detaching articles from both the issue and its section in order
        // to make them available for putting inside another issue's section.
        foreach ($entity->getArticles() as $article) {
            $article->setIssue(null);
            $article->setSection(null);
            $em->persist($article);
        }

        $entity->getSections()->clear(); // Remove all section relations

        $em->flush(); // Detach articles and sections first
        $this->get('vipa_core.delete.service')->check($entity);
        $em->remove($entity);
        $em->flush();

        $event = new JournalEvent($journal);
        $eventDispatcher->dispatch(IssueEvents::POST_DELETE, $event);

        if ($event->getResponse()) {
            return $event->getResponse();
        }

        $this->successFlashBag('deletion.issue');

        return $this->redirectToRoute('vipa_journal_issue_index', ['journalId' => $journal->getId()]);
    }

    /**
     * show issue manager view page
     * @param   integer $id
     * @return  Response
     * @throws  NotFoundHttpException
     */
    public function viewAction($id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for view this journal's issue!");
        }
        $em = $this->getDoctrine()->getManager();
        /** @var Issue $issue */
        $issue = $em->getRepository('VipaJournalBundle:Issue')->find($id);
        $this->throw404IfNotFound($issue);
        /** @var ArticleRepository $repo */
        $repo = $em->getRepository('VipaJournalBundle:Article');
        $articles = $repo->getOrderedArticlesByIssue($issue, true);

        return $this->render(
            'VipaJournalBundle:Issue:view.html.twig',
            array(
                'articles' => $articles,
                'journal' => $journal,
                'issue' => $issue,
            )
        );
    }

    /**
     * show issue manager arrange issue page , arrange and update
     * @param   Request $request
     * @param   integer $id
     * @return  Response
     * @throws  NotFoundHttpException
     */
    public function arrangeAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's issue!");
        }

        $em = $this->getDoctrine()->getManager();

        /** @var Issue $issue */
        $issue = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($issue);

        /** @var ArticleRepository $articleRepo */
        $articleRepo = $em->getRepository('VipaJournalBundle:Article');
        if ($request->isMethod('POST') && $request->get('articleId')) {
            // update with with new values
            $articleIds = $request->get('articleId');
            $orders = $request->get('order');
            $firstPages = $request->get('firstPage');
            $lastPages = $request->get('lastPage');

            foreach ($articleIds as $i => $articleId) {
                /** @var Article $article */
                $article = $articleRepo->find($articleId);
                $this->throw404IfNotFound($article);
                $article->setOrderNum((int)$orders[$i]);
                $article->setFirstPage((int)$firstPages[$i]);
                $article->setLastPage((int)$lastPages[$i]);
                $em->persist($article);
            }
            $em->flush();

            $this->successFlashBag('successfully.arranged.articles');
        }

        $articles = $articleRepo->getOrderedArticlesByIssue($issue, true);
        $articlesUnissued = $articleRepo->getArticlesUnissued();
        $sections = $journal->getSections();
        $data = [
            'articles' => $articles,
            'journal' => $journal,
            'issue' => $issue,
            'sections' => $sections,
            'articlesUnissued' => $articlesUnissued,
        ];

        return $this->render('VipaJournalBundle:Issue:arrange.html.twig', $data);
    }

    /**
     * @param Request $request
     * @param int $id
     * @param int $sectionId
     * @return Response
     */
    public function arrangeSortAction(Request $request,$id,$sectionId)
    {
        $em = $this->getDoctrine()->getManager();
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $section = $em->getRepository(Section::class)->find($sectionId);
        $articles = $em->getRepository(Article::class)->findBy(['issue' => $id, 'section' => $sectionId, 'status' => ArticleStatuses::STATUS_PUBLISHED]);
        usort($articles, function($a, $b){
            return $a->getOrderNum() > $b->getOrderNum();
        });

        $sortData = [];
        foreach ($articles as $article){
            $sortData[$article->getId()] = $article->getOrderNum();
        }

        if($request->getMethod() == 'POST' && $request->request->has('sortData')){
            $sortData = json_decode($request->request->get('sortData'));
            foreach ($sortData as $articleId => $order){
                foreach ($articles as $article){
                    if($article->getId() == $articleId){
                        $article->setOrderNum($order);
                        $em->persist($article);
                    }
                }
            }
            $em->flush();
            $this->successFlashBag('successful.update');

            return $this->redirectToRoute('vipa_journal_issue_arrange_sort', [
                'journalId' => $journal->getId(),
                'id' => $id,
                'sectionId' => $sectionId
            ]);
        }

        return $this->render('VipaJournalBundle:Issue:sort.html.twig', [
                'articles' => $articles,
                'section' => $section,
                'issueId' => $id,
                'jsonSortData' => json_encode($sortData)
            ]
        );
    }

    /**
     * add article to this issue
     * @param   Request $request
     * @param   integer $id
     * @param   integer $articleId
     * @return  RedirectResponse
     * @throws  NotFoundHttpException
     */
    public function addArticleAction(Request $request, $id, $articleId)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's issue!");
        }

        $em = $this->getDoctrine()->getManager();

        /** @var Issue $issue */
        $issue = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($issue);

        $selectedSection = $request->get('section', null);

        /** @var Article $article */
        $article = $em->getRepository('VipaJournalBundle:Article')->find($articleId);

        $this->throw404IfNotFound($article);
        $section = null;

        if ($selectedSection) {
            /** @var Section $section */
            $section = $em->getRepository('VipaJournalBundle:Section')->find($selectedSection);
            $this->throw404IfNotFound($section);
        }

        $article->setIssue($issue);
        $article->setStatus(ArticleStatuses::STATUS_PUBLISHED);

        if ($section) {
            $sections = $issue->getSections();
            if (!$sections->contains($section)) {
                $issue->addSection($section);
                $em->persist($issue);
            }
            $article->setSection($section);
        }

        $em->persist($article);
        $em->flush();
        $this->successFlashBag('successfully.article.added.to.issue');

        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * Remove article fro this issue
     * @param   Request $request
     * @param   integer $id
     * @param   integer $articleId
     * @return  RedirectResponse
     * @throws  NotFoundHttpException
     */
    public function removeArticleAction(Request $request, $id, $articleId)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's issue!");
        }

        $referrer = $request->headers->get('referer');
        $em = $this->getDoctrine()->getManager();

        $issue = $em->getRepository('VipaJournalBundle:Issue')->find($id);

        $this->throw404IfNotFound($issue);
        /** @var Article $article */
        $article = $em->getRepository('VipaJournalBundle:Article')->find($articleId);

        $this->throw404IfNotFound($article);

        $article->setIssue(null);
        $em->persist($article);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirect($referrer);
    }

    /**
     * @param $id
     * @return RedirectResponse
     */
    public function makeLastIssueAction($id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();

        if (!$this->isGranted('EDIT', $journal, 'issues')) {
            throw new AccessDeniedException("You are not authorized for this page");
        }
        $em = $this->getDoctrine()->getManager();

        $findSelectedIssue = $em->getRepository('VipaJournalBundle:Issue')->find($id);
        $this->throw404IfNotFound($findSelectedIssue);

        $findLastIssues = $em->getRepository('VipaJournalBundle:Issue')->findBy([
            'lastIssue' => true
        ]);
        foreach($findLastIssues as $issue){
            $issue->setLastIssue(false);
        }
        $findSelectedIssue->setLastIssue(true);
        $em->flush();
        $this->successFlashBag('successfully.set.as.last.issue');

        return $this->redirectToRoute('vipa_journal_issue_index', [
            'journalId' => $journal->getId()
        ]);
    }
}
