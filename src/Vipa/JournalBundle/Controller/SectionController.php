<?php

namespace Vipa\JournalBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\ORM\Query;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Params\ArticleStatuses;
use Vipa\JournalBundle\Entity\Issue;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\Section;
use Vipa\JournalBundle\Event\JournalEvent;
use Vipa\JournalBundle\Event\JournalItemEvent;
use Vipa\JournalBundle\Event\ListEvent;
use Vipa\JournalBundle\Event\Section\SectionEvents;
use Vipa\JournalBundle\Form\Type\SectionType;
use Vipa\JournalBundle\Validator\Constraints\ArticleStatus;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * Section controller.
 *
 */
class SectionController extends Controller
{
    /**
     * Lists all Section entities.
     *
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('VIEW', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for view this journal's sections!");
        }
        $source = new Entity('VipaJournalBundle:Section');
        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');

        $rowAction[] = $gridAction->showAction('vipa_journal_section_show', ['id', 'journalId' => $journal->getId()]);
        if ($this->isGranted('EDIT', $this->get('vipa.journal_service')->getSelectedJournal(), 'sections')) {
            $rowAction[] = $gridAction->editAction(
                'vipa_journal_section_edit',
                ['id', 'journalId' => $journal->getId()]
            );
        }
        if ($this->isGranted('DELETE', $this->get('vipa.journal_service')->getSelectedJournal(), 'sections')) {
            $rowAction[] = $gridAction->deleteAction(
                'vipa_journal_section_delete',
                ['id', 'journalId' => $journal->getId()]
            );
        }

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        $listEvent = new ListEvent();
        $listEvent->setGrid($grid);
        $eventDispatcher->dispatch(SectionEvents::LISTED, $listEvent);
        $grid = $listEvent->getGrid();

        return $grid->getGridResponse('VipaJournalBundle:Section:index.html.twig');
    }

    /**
     * Creates a new Section entity.
     *
     * @param  Request                   $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('CREATE', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for create section on this journal!");
        }

        $entity = new Section();
        $form = $this->createCreateForm($entity, $journal);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $entity->setJournal($journal);
            $entity->setCurrentLocale($request->getDefaultLocale());
            $em = $this->getDoctrine()->getManager();

            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(SectionEvents::PRE_CREATE, $event);

            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(SectionEvents::POST_CREATE, $event);

            $this->successFlashBag('successful.create');

            return $this->redirectToRoute(
                'vipa_journal_section_show',
                [
                    'id' => $entity->getId(),
                    'journalId' => $journal->getId(),
                ]
            );
        }

        return $this->render(
            'VipaJournalBundle:Section:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a Section entity.
     *
     * @param Section $entity
     * @param Journal $journal
     * @return Form
     */
    private function createCreateForm(Section $entity, Journal $journal)
    {
        $form = $this->createForm(
            new SectionType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_journal_section_create', ['journalId' => $journal->getId()]),
                'method' => 'POST',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new Section entity.
     *
     * @return Response
     */
    public function newAction()
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (!$this->isGranted('CREATE', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for create section on this journal!");
        }
        $entity = new Section();
        $form = $this->createCreateForm($entity, $journal);

        return $this->render(
            'VipaJournalBundle:Section:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a Section entity.
     *
     * @param Request $request
     * @param Section $entity
     * @return Response
     */
    public function showAction(Request $request, Section $entity)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (!$this->isGranted('VIEW', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for view this journal's section!");
        }

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_journal_section'.$entity->getId());

        return $this->render(
            'VipaJournalBundle:Section:show.html.twig',
            array(
                'entity' => $entity,
                'token' => $token,
            )
        );
    }

    /**
     * Displays a form to edit an existing Section entity.
     *
     * @param $id
     * @return Response
     */
    public function editAction($id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        if (!$this->isGranted('EDIT', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's section!");
        }
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('VipaJournalBundle:Section')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $editForm = $this->createEditForm($entity);

        return $this->render(
            'VipaJournalBundle:Section:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a Section entity.
     *
     * @param Section $entity The entity
     *
     * @return Form The form
     */
    private function createEditForm(Section $entity)
    {
        $form = $this->createForm(
            new SectionType(),
            $entity,
            array(
                'action' => $this->generateUrl(
                    'vipa_journal_section_update',
                    array('id' => $entity->getId(), 'journalId' => $entity->getJournal()->getId())
                ),
                'method' => 'PUT',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * Edits an existing Section entity.
     *
     * @param  Request                   $request
     * @param $id
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('EDIT', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for edit this journal's section!");
        }
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('VipaJournalBundle:Section')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $event = new JournalItemEvent($entity);
            $eventDispatcher->dispatch(SectionEvents::PRE_UPDATE, $event);
            $em->persist($event->getItem());
            $em->flush();

            $event = new JournalItemEvent($event->getItem());
            $eventDispatcher->dispatch(SectionEvents::POST_UPDATE, $event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            $this->successFlashBag('successful.update');

            return $this->redirectToRoute(
                'vipa_journal_section_edit',
                [
                    'id' => $id,
                    'journalId' => $journal->getId(),
                ]
            );
        }

        return $this->render(
            'VipaJournalBundle:Section:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Deletes a Section entity.
     *
     * @param  Request          $request
     * @param $id
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, $id)
    {
        $journal = $this->get('vipa.journal_service')->getSelectedJournal();
        $eventDispatcher = $this->get('event_dispatcher');

        if (!$this->isGranted('DELETE', $journal, 'sections')) {
            throw new AccessDeniedException("You are not authorized for delete this journal's section!");
        }

        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaJournalBundle:Section')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('notFound');
        }

        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('vipa_journal_section'.$id);

        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }

        $event = new JournalItemEvent($entity);
        $eventDispatcher->dispatch(SectionEvents::PRE_DELETE, $event);

        foreach ($entity->getArticles() as $article) {
            $article->setIssue(null);
            $article->setSection(null);
            $article->setStatus(ArticleStatuses::STATUS_PUBLISH_READY);
            $em->persist($article);
        }

        $em->flush(); // Detach articles first
        $this->get('vipa_core.delete.service')->check($entity);

        $em->remove($entity);
        $em->flush();

        $event = new JournalEvent($journal);
        $eventDispatcher->dispatch(SectionEvents::POST_DELETE, $event);

        if ($event->getResponse()) {
            return $event->getResponse();
        }

        $this->successFlashBag('deletion.section');

        return $this->redirectToRoute('vipa_journal_section_index', ['journalId' => $journal->getId()]);
    }
}
