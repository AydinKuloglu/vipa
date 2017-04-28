<?php

namespace Vipa\JournalBundle\Listeners;

use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Model\UserInterface;
use Vipa\CoreBundle\Service\Mailer;
use Vipa\JournalBundle\Event\JournalItemEvent;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractJournalItemMailer implements EventSubscriberInterface
{
    /** @var Mailer */
    protected $mailer;

    /** @var EntityManager */
    protected $em;

    /** @var UserInterface */
    protected $user;

    /** @var  RouterInterface */
    protected $router;

    /**
     * AbstractJournalItemMailer constructor.
     * @param Mailer $mailer
     * @param RegistryInterface $registry
     * @param TokenStorageInterface $tokenStorage
     * @param RouterInterface $router
     */
    public function __construct(
        Mailer $mailer,
        RegistryInterface $registry,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router
    )
    {
        $this->mailer = $mailer;
        $this->em = $registry->getManager();
        $this->user = $tokenStorage->getToken() ? $tokenStorage->getToken()->getUser(): null;
        $this->router = $router;
    }

    protected function sendMail(JournalItemEvent $itemEvent, $item, $action)
    {
        $journalItem = $itemEvent->getItem();
        foreach ($this->mailer->getJournalStaff() as $user) {
            $this->mailer->sendToUser(
                $user,
                'A '.$item.' '.$action.' -> '.$journalItem->getJournal()->getTitle(),
                'A '.$item.' '.$action.' -> '.$journalItem->getJournal()->getTitle()
                .' -> by '.$this->user->getUsername()
            );
        }
    }
}
