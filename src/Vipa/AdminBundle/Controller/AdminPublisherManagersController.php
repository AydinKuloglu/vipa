<?php

namespace Vipa\AdminBundle\Controller;

use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Vipa\AdminBundle\Entity\PublisherManagers;
use Vipa\AdminBundle\Form\Type\PublisherManagersType;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vipa\AdminBundle\Events\AdminEvent;
use Vipa\AdminBundle\Events\AdminEvents;

/**
 * PublisherManagers controller.
 *
 */
class AdminPublisherManagersController extends Controller
{
    /**
     * Lists all PublisherManagers entities.
     *
     * @return Response
     */
    public function indexAction()
    {
        $source = new Entity('VipaAdminBundle:PublisherManagers');
        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');

        $rowAction[] = $gridAction->showAction('vipa_admin_publisher_managers_show', 'id');
        $rowAction[] = $gridAction->editAction('vipa_admin_publisher_managers_edit', 'id');
        $rowAction[] = $gridAction->deleteAction('vipa_admin_publisher_managers_delete', 'id');

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);
        $data = [];
        $data['grid'] = $grid;

        return $grid->getGridResponse('VipaAdminBundle:AdminPublisherManagers:index.html.twig', $data);
    }

    /**
     * Creates a new PublisherManagers entity.
     *
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $entity = new PublisherManagers();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();
            $this->successFlashBag('successful.create');

            $event = new AdminEvent([
                'eventType' => 'create',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::PUBLISHER_MANAGER_CHANGE, $event);
            return $this->redirectToRoute(
                'vipa_admin_publisher_managers_show',
                [
                    'id' => $entity->getId()
                ]
            );
        }

        return $this->render(
            'VipaAdminBundle:AdminPublisherManagers:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a PublisherManagers entity.
     *
     * @param PublisherManagers $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createCreateForm(PublisherManagers $entity)
    {
        $form = $this->createForm(
            new PublisherManagersType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_publisher_managers_create'),
                'method' => 'POST',
            )
        );
        $form->add('submit', 'submit', array('label' => 'Create'));

        return $form;
    }

    /**
     * Displays a form to create a new PublisherManagers entity.
     *
     */
    public function newAction()
    {
        $entity = new PublisherManagers();
        $form = $this->createCreateForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminPublisherManagers:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a PublisherManagers entity.
     *
     * @param PublisherManagers $entity
     * @return Response
     */
    public function showAction(PublisherManagers $entity)
    {
        $this->throw404IfNotFound($entity);
        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_admin_publisher_managers'.$entity->getId());

        return $this->render(
            'VipaAdminBundle:AdminPublisherManagers:show.html.twig',
            ['entity' => $entity, 'token' => $token]
        );
    }

    /**
     * Displays a form to edit an existing PublisherManagers entity.
     *
     * @param PublisherManagers $entity
     * @return Response
     */
    public function editAction(PublisherManagers $entity)
    {
        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminPublisherManagers:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a PublisherManagers entity.
     *
     * @param PublisherManagers $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(PublisherManagers $entity)
    {
        $form = $this->createForm(
            new PublisherManagersType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_publisher_managers_update', array('id' => $entity->getId())),
                'method' => 'PUT',
            )
        );
        $form->add('submit', 'submit', array('label' => 'Update'));

        return $form;
    }

    /**
     * Edits an existing PublisherManagers entity.
     *
     * @param Request $request
     * @param PublisherManagers $entity
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, PublisherManagers $entity)
    {
        $em = $this->getDoctrine()->getManager();
        $this->throw404IfNotFound($entity);
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $em->flush();
            $this->successFlashBag('successful.update');

            $event = new AdminEvent([
                'eventType' => 'update',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::PUBLISHER_MANAGER_CHANGE, $event);
            return $this->redirectToRoute('vipa_admin_publisher_managers_edit', ['id' => $entity->getId()]);
        }

        return $this->render(
            'VipaAdminBundle:AdminPublisherManagers:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @param  Request $request
     * @param  PublisherManagers $entity
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws TokenNotFoundException
     */
    public function deleteAction(Request $request, PublisherManagers $entity)
    {
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $this->throw404IfNotFound($entity);
        $em = $this->getDoctrine()->getManager();

        $csrf = $this->get('security.csrf.token_manager');
        $token = $csrf->getToken('vipa_admin_publisher_managers'.$entity->getId());
        if ($token != $request->get('_token')) {
            throw new TokenNotFoundException("Token Not Found!");
        }
        $event = new AdminEvent([
            'eventType' => 'delete',
            'entity'    => $entity,
        ]);
        $dispatcher->dispatch(AdminEvents::PUBLISHER_MANAGER_CHANGE, $event);
        $em->remove($entity);
        $em->flush();
        $this->successFlashBag('successful.remove');

        return $this->redirectToRoute('vipa_admin_publisher_managers_index');
    }
}
