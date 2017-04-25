<?php

namespace Vipa\AdminBundle\Controller;

use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity;
use Elastica\Query;
use Vipa\AdminBundle\Form\Type\JournalEditType;
use Vipa\AdminBundle\Form\Type\JournalType;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Params\JournalStatuses;
use Vipa\JournalBundle\Entity\Journal;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vipa\AdminBundle\Events\AdminEvent;
use Vipa\AdminBundle\Events\AdminEvents;

/**
 * Journal controller.
 */
class AdminJournalController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $router = $this->get('router');
        $source = new Entity('VipaJournalBundle:Journal');
        $source->manipulateRow(
            function (Row $row) use ($request, $router) {
                /* @var Journal $entity */
                $entity = $row->getEntity();
                if (!is_null($entity)) {
                    $journalLinkTemplate = $entity->getTitleTranslations();
                    if($entity->isIndexable() && $entity->getPublisher() !== null){
                        $generateJournalLink = $router->generate('vipa_journal_index', [
                            'slug' => $entity->getSlug(),
                        ]);
                        $journalLinkTemplate = '<a target="_blank" href="'.$generateJournalLink.'">'.$entity->getTitleTranslations().'</a>';
                    }
                    $row->setField('translations.title:translation_agg', $journalLinkTemplate);
                }

                return $row;
            }
        );
        $grid = $this->get('grid')->setSource($source);
        $gridAction = $this->get('grid_action');

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction('vipa_admin_journal_show', 'id');
        $rowAction[] = $gridAction->editAction('vipa_admin_journal_edit', 'id');
        $rowAction[] = $gridAction->contactsAction('vipa_journal_journal_contact_index');
        $rowAction[] = (new RowAction('Manage', 'vipa_journal_dashboard_index'))
            ->setRouteParameters('id')
            ->setRouteParametersMapping(array('id' => 'journalId'))
            ->setAttributes(
                array(
                    'class' => 'btn btn-success btn-xs',
                    'data-toggle' => 'tooltip',
                    'title' => "Manage"
                )
            );

        $actionColumn->setRowActions($rowAction);

        $grid->addColumn($actionColumn);
        $data = [];
        $data['grid'] = $grid;

        return $grid->getGridResponse('VipaAdminBundle:AdminJournal:index.html.twig', $data);
    }

    /**
     * Displays a form to edit an existing Journal entity.
     *
     * @param $id
     * @return Response
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Journal $entity */
        $entity = $em->getRepository('VipaJournalBundle:Journal')->find($id);
        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminJournal:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a Publisher entity.
     *
     * @param Journal $entity The entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(Journal $entity)
    {
        $form = $this->createForm(
            new JournalEditType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_journal_update', array('id' => $entity->getId())),
                'method' => 'PUT',
            )
        );
        $form->add('submit', 'submit', ['label' => 'Update']);

        return $form;
    }

    /**
     * Edits an existing Journal entity.
     *
     * @param  Request $request
     * @param $id
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Journal $entity */
        $entity = $em->getRepository('VipaJournalBundle:Journal')->find($id);
        $this->throw404IfNotFound($entity);
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);
        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->successFlashBag('successful.update');

            $event = new AdminEvent([
                'eventType' => 'update',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::ADMIN_JOURNAL_CHANGE, $event);
            return $this->redirectToRoute('vipa_admin_journal_edit', ['id' => $id]);
        }

        return $this->render(
            'VipaAdminBundle:AdminJournal:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @param  Request $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $entity = new Journal();
        $entity->setCurrentLocale($request->getDefaultLocale());
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
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
            $dispatcher->dispatch(AdminEvents::ADMIN_JOURNAL_CHANGE, $event);
            return $this->redirect($this->generateUrl('vipa_admin_journal_show', array('id' => $entity->getId())));
        }

        return $this->render(
            'VipaAdminBundle:AdminJournal:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a Journal entity.
     * @param  Journal $entity The entity
     * @return Form    The form
     */
    private function createCreateForm(Journal $entity)
    {
        $form = $this->createForm(
            new JournalType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_journal_create'),
                'method' => 'POST',
            )
        );

        return $form;
    }

    /**
     * Displays a form to create a new Journal entity.
     *
     * @param Request $request
     * @return Response
     */
    public function newAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $defaultCountry = $em->getRepository('BulutYazilimLocationBundle:Country')->find($this->getParameter('country_id'));
        $entity = new Journal();
        $entity->setCountry($defaultCountry);
        $entity->setCurrentLocale($request->getDefaultLocale());
        $form = $this->createCreateForm($entity);

        return $this->render(
            'VipaAdminBundle:AdminJournal:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a Journal entity.
     *
     * @param Request $request
     * @param Journal $entity
     * @return Response
     */
    public function showAction(Request $request, Journal $entity)
    {
        $this->throw404IfNotFound($entity);
        $entity->setDefaultLocale($request->getDefaultLocale());
        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_admin_journal'.$entity->getId());

        return $this->render(
            'VipaAdminBundle:AdminJournal:show.html.twig',
            ['entity' => $entity, 'token' => $token]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function autoCompleteAction(Request $request)
    {
        $q = filter_var($request->get('q'), FILTER_SANITIZE_STRING);
        $search = $this->get('fos_elastica.index.search.journal');

        $notCollectJournals = [];
        if($request->query->has('notCollectJournals')){
            $notCollectJournalsParam = $request->query->get('notCollectJournals');
            if(!empty($notCollectJournalsParam) && is_array($notCollectJournalsParam)){
                $notCollectJournals = $notCollectJournalsParam;
            }
        }

        $searchQuery = new Query('_all');

        $boolQuery = new Query\BoolQuery();

        $fieldQuery = new Query\MultiMatch();

        $fieldQuery->setFields(['title']);
        $fieldQuery->setQuery(strtoupper($q));
        $fieldQuery->setFuzziness(0.7);
        $boolQuery->addMust($fieldQuery);

        $searchQuery->setQuery($boolQuery);
        $searchQuery->setSize(10);

        $results = $search->search($searchQuery);
        $data = [];
        foreach ($results as $result) {
            if(!in_array($result->getId(), $notCollectJournals)){
                $data[] = [
                    'id' => $result->getId(),
                    'text' => $result->getData()['title'],
                ];
            }
        }

        return new JsonResponse($data);
    }
}
