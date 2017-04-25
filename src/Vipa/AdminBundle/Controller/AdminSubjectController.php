<?php

namespace Vipa\AdminBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\Common\Collections\ArrayCollection;
use Vipa\AdminBundle\Form\Type\SubjectType;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Helper\TreeHelper;
use Vipa\JournalBundle\Entity\Subject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vipa\AdminBundle\Events\AdminEvent;
use Vipa\AdminBundle\Events\AdminEvents;

/**
 * Subject controller.
 *
 */
class AdminSubjectController extends Controller
{

    /**
     * Lists all Subject entities.
     *
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $source = new Entity("VipaJournalBundle:Subject");
        $grid = $this->get('grid')->setSource($source);

        $gridAction = $this->get('grid_action');
        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction('vipa_admin_subject_show', 'id');
        $rowAction[] = $gridAction->editAction('vipa_admin_subject_edit', 'id');
        $rowAction[] = $gridAction->deleteAction('vipa_admin_subject_delete', 'id');
        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        /** @var ArrayCollection|Subject[] $all */
        $all = $this
            ->getDoctrine()
            ->getRepository('VipaJournalBundle:Subject')
            ->findAll();

        $data = [
            'grid' => $grid,
            'tree' => TreeHelper::createSubjectTreeView(TreeHelper::SUBJECT_ADMIN, $this->get('router'), $all)
        ];

        return $grid->getGridResponse('VipaAdminBundle:AdminSubject:index.html.twig', $data);
    }

    /**
     * Creates a new Subject entity.
     *
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $entity = new Subject();
        $entity->setCurrentLocale($request->getDefaultLocale());
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity->setSlug($entity->getTranslationByLocale($request->getDefaultLocale())->getSubject());
            $em->persist($entity);
            $em->flush();
            $this->successFlashBag('successful.create');

            $event = new AdminEvent([
                'eventType' => 'create',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::ADMIN_SUBJECT_CHANGE, $event);
            return $this->redirectToRoute('vipa_admin_subject_show', ['id' => $entity->getId()]);
        }

        return $this->render(
            'VipaAdminBundle:AdminSubject:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a Subject entity.
     *
     * @param Subject $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createCreateForm(Subject $entity)
    {
        $form = $this->createForm(
            new SubjectType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_subject_create'),
                'method' => 'POST',
            )
        );
        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new Subject entity.
     *
     */
    public function newAction()
    {
        $entity = new Subject();
        $form = $this->createCreateForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminSubject:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a Subject entity.
     *
     * @param Request $request
     * @param Subject $entity
     * @return Response
     */
    public function showAction(Request $request, Subject $entity)
    {
        $this->throw404IfNotFound($entity);
        $entity->setDefaultLocale($request->getDefaultLocale());
        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_admin_subject'.$entity->getId());

        return $this->render(
            'VipaAdminBundle:AdminSubject:show.html.twig',
            ['entity' => $entity, 'token' => $token]
        );
    }

    /**
     * Displays a form to edit an existing Subject entity.
     *
     * @param  Subject $entity
     * @return Response
     */
    public function editAction(Subject $entity)
    {
        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminSubject:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a Subject entity.
     *
     * @param Subject $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(Subject $entity)
    {
        $form = $this->createForm(
            new SubjectType($entity->getId()),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_subject_update', array('id' => $entity->getId())),
                'method' => 'PUT',
            )
        );

        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * Edits an existing Subject entity.
     *
     * @param  Request $request
     * @param  Subject $entity
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, Subject $entity)
    {
        $this->throw404IfNotFound($entity);
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $em = $this->getDoctrine()->getManager();
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $entity->setSlug($entity->getTranslationByLocale($request->getDefaultLocale())->getSubject());
            $em->flush();
            $this->successFlashBag('successful.update');

            $event = new AdminEvent([
                'eventType' => 'update',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::ADMIN_SUBJECT_CHANGE, $event);
            return $this->redirectToRoute('vipa_admin_subject_edit', ['id' => $entity->getId()]);
        }

        return $this->render(
            'VipaAdminBundle:AdminSubject:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @param  Request $request
     * @param  Subject $entity
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, Subject $entity)
    {
        $this->throw404IfNotFound($entity);
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $em = $this->getDoctrine()->getManager();

        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('vipa_admin_subject'.$entity->getId());
        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }
        $this->get('vipa_core.delete.service')->check($entity);
        $event = new AdminEvent([
            'eventType' => 'delete',
            'entity'    => $entity,
        ]);
        $dispatcher->dispatch(AdminEvents::ADMIN_SUBJECT_CHANGE, $event);
        $em->remove($entity);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('vipa_admin_subject_index');
    }
}
