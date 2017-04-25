<?php

namespace Vipa\AdminBundle\Controller;

use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Entity;
use Doctrine\ORM\ORMException;
use Vipa\AdminBundle\Events\AdminEvents;
use Vipa\AdminBundle\Events\MergeEvent;
use Vipa\AdminBundle\Events\MergeEvents;
use Vipa\AdminBundle\Form\Type\ChangePasswordType;
use Vipa\AdminBundle\Form\Type\UpdateUserType;
use Vipa\AdminBundle\Form\Type\UserType;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\JournalBundle\Entity\Article;
use Vipa\JournalBundle\Entity\Author;
use Vipa\JournalBundle\Entity\BoardMember;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\JournalSetupProgress;
use Vipa\JournalBundle\Entity\JournalUser;
use Vipa\JournalBundle\Entity\Subject;
use Vipa\UserBundle\Entity\MultipleMail;
use Vipa\UserBundle\Entity\User;
use Vipa\UserBundle\Entity\UserRepository;
use Presta\SitemapBundle\Exception\Exception;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\GetResponseUserEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Vipa\AdminBundle\Events\AdminEvent;
use Vipa\AdminBundle\Form\Type\UserMergeType;

/**
 * User administration controller
 */
class AdminUserController extends Controller
{

    /**
     * Lists all User entities.
     * @return Response
     */
    public function indexAction()
    {
        $source = new Entity("VipaUserBundle:User");
        $grid = $this->get('grid');
        $gridAction = $this->get('grid_action');
        $grid->setSource($source);

        $passwordAction = new RowAction('<i class="fa fa-key"></i>', 'vipa_admin_user_password');
        $passwordAction->setRouteParameters('id');
        $passwordAction->setAttributes(
            [
                'class' => 'btn btn-info btn-xs',
                'data-toggle' => 'tooltip',
                'title' => $this->get('translator')->trans('title.password_change'),
            ]
        );

        $mergeAction = new RowAction('<i class="fa fa-compress"></i>', 'vipa_admin_user_primary_merge');
        $mergeAction->setRouteParameters('id');
        $mergeAction->setAttributes(
            [
                'class' => 'btn btn-primary btn-xs',
                'data-toggle' => 'tooltip',
                'title' => $this->get('translator')->trans('title.user_merge'),
            ]
        );

        $actionColumn = new ActionsColumn("actions", 'actions');
        $rowAction[] = $gridAction->showAction('vipa_admin_user_show', 'id');
        $rowAction[] = $gridAction->editAction('vipa_admin_user_edit', 'id');
        $rowAction[] = $passwordAction;
        $rowAction[] = $mergeAction;
        $rowAction[] = $gridAction->userBanAction();

        $actionColumn->setRowActions($rowAction);
        $grid->addColumn($actionColumn);

        return $grid->getGridResponse('VipaAdminBundle:AdminUser:index.html.twig', ['grid' => $grid]);
    }

    /**
     * Creates a new User entity.
     *
     * @param  Request                   $request
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $entity = new User();
        $form = $this->createCreateForm($entity)
            ->add('create', 'submit', array('label' => 'c'));
        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $factory = $this->get('security.encoder_factory');
            $encoder = $factory->getEncoder($entity);
            $password = $encoder->encodePassword($entity->getPassword(), $entity->getSalt());
            $entity->setPassword($password);
            $em->persist($entity);
            $em->flush();

            $this->successFlashBag('successful.create');

            $event = new AdminEvent([
                'eventType' => 'create',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::ADMIN_USER_CHANGE_CREATE, $event);
            return $this->redirectToRoute(
                'vipa_admin_user_show',
                [
                    'id' => $entity->getId(),
                ]
            );
        }

        return $this->render(
            'VipaAdminBundle:AdminUser:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Creates a form to create a User entity.
     * @param  User $entity The entity
     * @return Form The form
     */
    private function createCreateForm(User $entity)
    {
        $form = $this->createForm(
            new UserType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_user_create'),
                'method' => 'POST'
            )
        );

        return $form;
    }

    /**
     * Displays a form to create a new User entity.
     *
     * @return Response
     */
    public function newAction()
    {
        $entity = new User();
        $form = $this->createCreateForm($entity)
            ->add('create', 'submit', array('label' => 'c'));

        return $this->render(
            'VipaAdminBundle:AdminUser:new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Finds and displays a User entity.
     *
     * @param $id
     * @return Response
     */
    public function showAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaUserBundle:User')->find($id);

        $this->throw404IfNotFound($entity);

        $token = $this
            ->get('security.csrf.token_manager')
            ->refreshToken('vipa_admin_user'.$entity->getId());

        return $this->render(
            'VipaAdminBundle:AdminUser:show.html.twig',
            ['entity' => $entity, 'token' => $token]
        );
    }

    /**
     * @param  bool     $username
     * @return Response
     */
    public function profileAction($username = false)
    {
        /** @var UserRepository $userRepo */
        $userRepo = $this->getDoctrine()->getRepository('VipaUserBundle:User');
        $sessionUser = $this->getUser();
        /** @var User $user */
        $user = $username ?
            $userRepo->findOneBy(array('username' => $username)) :
            $sessionUser;
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaUserBundle:User')->find($user->getId());
        $this->throw404IfNotFound($entity);

        return $this->render(
            'VipaUserBundle:User:profile.html.twig',
            array(
                'entity' => $entity,
                'delete_form' => array(),
                'me' => ($sessionUser == $user)
            )
        );
    }

    /**
     * Displays a form to edit an existing User entity.
     *
     * @param $id
     * @return Response
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaUserBundle:User')->find($id);
        $this->throw404IfNotFound($entity);
        $editForm = $this->createEditForm($entity)
            ->add('save','submit');

        return $this->render(
            'VipaAdminBundle:AdminUser:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Creates a form to edit a User entity.
     * @param  User                         $entity The entity
     * @return \Symfony\Component\Form\Form The form
     */
    private function createEditForm(User $entity)
    {
        $form = $this->createForm(
            new UpdateUserType(),
            $entity,
            array(
                'action' => $this->generateUrl('vipa_admin_user_update', array('id' => $entity->getId())),
                'method' => 'PUT',
                'validation_groups' => array('Default'),
            )
        );

        return $form;
    }

    /**
     * Edits an existing User entity.
     *
     * @param  Request                   $request
     * @param $id
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var User $entity */
        $entity = $em->getRepository('VipaUserBundle:User')->find($id);
        $this->throw404IfNotFound($entity);
        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
        $oldPassword = $entity->getPassword();
        $editForm = $this->createEditForm($entity)
            ->add('save','submit');
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $password = $entity->getPassword();
            if (empty($password)) {
                $entity->setPassword($oldPassword);
            } else {
                $factory = $this->get('security.encoder_factory');
                $encoder = $factory->getEncoder($entity);
                $password = $encoder->encodePassword($entity->getPassword(), $entity->getSalt());
                $entity->setPassword($password);
            }

            $em->flush();

            $this->successFlashBag('successful.update');

            $event = new AdminEvent([
                'eventType' => 'update',
                'entity'    => $entity,
            ]);
            $dispatcher->dispatch(AdminEvents::ADMIN_USER_CHANGE, $event);

            return $this->redirectToRoute('vipa_admin_user_edit', ['id' => $id]);
        }

        return $this->render(
            'VipaAdminBundle:AdminUser:edit.html.twig',
            array(
                'entity' => $entity,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * @param $id
     * @return RedirectResponse
     */
    public function blockAction($id)
    {
        /** @var User $user */
        $em = $this->getDoctrine()->getManager();
        $user = $em->find('VipaUserBundle:User', $id);
        $dispatcher = $this->get('event_dispatcher');
        if (!$user) {
            throw new NotFoundResourceException("User not found.");
        }

        $user->setEnabled(false);
        $em->persist($user);
        $em->flush();

        $event = new AdminEvent([
            'eventType' => 'block',
            'entity'    => $user,
        ]);
        $dispatcher->dispatch(AdminEvents::ADMIN_USER_CHANGE, $event);

        return $this->redirectToRoute('vipa_admin_user_index');
    }

    /**
     * @param $id
     * @return RedirectResponse
     * @throws ORMException
     */
    public function unblockAction($id)
    {
        /** @var User $user */
        $em = $this->getDoctrine()->getManager();
        $user = $em->find('VipaUserBundle:User', $id);
        $dispatcher = $this->get('event_dispatcher');
        if (!$user) {
            throw new NotFoundResourceException("User not found.");
        }

        $user->setEnabled(true);
        $em->persist($user);
        $em->flush();

        $event = new AdminEvent([
            'eventType' => 'unblock',
            'entity'    => $user,
        ]);
        $dispatcher->dispatch(AdminEvents::ADMIN_USER_CHANGE, $event);

        return $this->redirectToRoute('vipa_admin_user_index');
    }

    public function changePasswordAction(Request $request, $id)
    {
        /** @var User $user */
        $em = $this->getDoctrine()->getManager();
        $user = $em->find('VipaUserBundle:User', $id);
        $this->throw404IfNotFound($user);
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.resetting.form.factory');
        /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
        $userManager = $this->get('fos_user.user_manager');

        $form = $formFactory->createForm();
        $form->setData($user);
        $form->add('submit', 'submit');

        $form->handleRequest($request);

        if ($form->isValid()) {

            $userManager->updateUser($user);
            $this->successFlashBag('successful.update');
        }

        return $this->render('VipaAdminBundle:AdminUser:password.html.twig', [
                'form' => $form->createView()
            ]
        );
    }


    /**
     * @param User|null $primaryUser
     * @return Form
     */
    private function createMergeForm(User $primaryUser = null)
    {
        $form = $this->createForm(
            new UserMergeType(),
            null,
            array(
                'action' => $primaryUser == null ? $this->generateUrl('vipa_admin_user_merge') : $this->generateUrl('vipa_admin_user_primary_merge', ['id' => $primaryUser->getId()]),
                'method' => 'POST',
                'primaryUser' => $primaryUser
            )
        );

        return $form;
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse|Response
     */
    public function mergeAction(Request $request, $id = null)
    {
        /** @var User $user */
        $em = $this->getDoctrine()->getManager();

        /** @var $dispatcher EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');

        $user = null;
        if($id !== null) {
            $user = $em->find('VipaUserBundle:User', $id);
        }
        $form = $this->createMergeForm($user)
            ->add('create', 'submit', array('label' => 'c'));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var User $primaryUser */
            $primaryUser = $user == null ? $data['primaryUser'] : $user;

            /** @var User[] $slaveUsers */
            $slaveUsers = $data['slaveUsers'];

            foreach ($slaveUsers as $slaveUser) {
                if($primaryUser->getId() == $slaveUser->getId() || $slaveUser->getMerged() !== null){
                    continue;
                }
                
                foreach ($this->migrateEntities() as $name => $class)
                {
                    $this->migrateUser($class, $name, $primaryUser, $slaveUser);
                }

                $this->migrateMails($primaryUser, $slaveUser);

                $primaryUser->addMergeUser($slaveUser);
                $slaveUser->setMergedUser($primaryUser);
                $em->persist($primaryUser);
                $em->persist($slaveUser);
            }

            $event = new MergeEvent($primaryUser, $slaveUsers);
            $dispatcher->dispatch(MergeEvents::OJS_ADMIN_USER_MERGE, $event);


            $em->flush();

            $this->successFlashBag('successful.create');

            return $this->redirectToRoute(
                'vipa_admin_user_index'
            );
        }

        return $this->render(
            'VipaAdminBundle:AdminUser:merge.html.twig',
            array(
                'form' => $form->createView(),
            )
        );

    }

    /**
     * @param $class
     * @param $entityName
     * @param User $primary
     * @param User $slave
     */
    private function migrateUser($class, $entityName, User $primary, User $slave)
    {
        $em = $this->getDoctrine()->getManager();

        switch ($entityName)
        {
            case 'journal.user':

                /**
                 * @var JournalUser $result
                 */
                $result = $em->getRepository($class)->findOneBy(['user' => $slave]);

                if(!$result){
                    continue;
                }

                foreach ($result->getRoles() as $role)
                {
                    if(!in_array($role, $primary->getJournalRoles($result->getJournal())))
                    {
                        $journalUser = new JournalUser();
                        $journalUser->setUser($primary);
                        $journalUser->addRole($role);

                        $em->persist($journalUser);
                    }

                }

            break;

            case 'subject':

                foreach ($slave->getSubjects() as $subject)
                {
                    if(!in_array($subject, (array)$primary->getSubjects()))
                    {
                        $primary->addSubject($subject);
                        $em->persist($primary);
                    }
                }
                
            break;

            case 'article':
                $results = $em->getRepository(Article::class)->findBy(['submitterUser' => $slave->getId()]);

                foreach ($results as $article)
                {
                    $article->setSubmitterUser($primary);
                    $em->persist($article);
                }

            break;

            default:
                $results = $em->getRepository($class)->findBy(['user' => $slave]);
                foreach ($results as $result)
                {
                    $result->setUser($primary);
                    $em->persist($result);
                }
            break;

        }
    }

    /**
     * @param User $primaryUser
     * @param User $slaveUser
     */
    private function migrateMails(User $primaryUser, User $slaveUser)
    {
        $em = $this->getDoctrine()->getManager();

        $multipleMail = new MultipleMail();
        $multipleMail->setUser($primaryUser);
        $multipleMail->setIsConfirmed(1);
        $multipleMail->setMail($slaveUser->getEmail());

        $em->persist($multipleMail);

        foreach ($slaveUser->getMultipleMails() as $multipleMail)
        {
            $em->remove($multipleMail);
            $em->flush();
            $mail = new MultipleMail();
            $mail->setUser($primaryUser);
            $mail->setIsConfirmed(1);
            $mail->setMail($multipleMail->getMail());
            $em->persist($mail);
        }
    }

    /**
     * @return array
     */
    private function migrateEntities()
    {
        return
            [
                'board' => BoardMember::class,
                'journal.setup.progress' => JournalSetupProgress::class,
                'author' => Author::class,
                'subject' => Subject::class,
                'journal.user' => JournalUser::class,
            ];
    }
}
