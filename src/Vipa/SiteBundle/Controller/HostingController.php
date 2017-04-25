<?php

namespace Vipa\SiteBundle\Controller;

use Doctrine\ORM\EntityManager;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Params\JournalStatuses;
use Vipa\JournalBundle\Entity\Journal;
use Symfony\Component\HttpFoundation\Request;

/**
 * Journal & Publisher Hosting pages controller
 * Class HostingController
 * @package Vipa\SiteBundle\Controller
 */
class HostingController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:journalIndex', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;


    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function contactsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:journalContacts', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;


    }


    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function boardAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:journalBoard', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;


    }


    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function issuePageAction(Request $request, $id)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Issue:issuePage', array(
            'id' => $id,
            'isJournalHosting' => true,
        ));

        return $response;

    }

    /**
     * @param Request $request
     * @param $article_id
     * @param null $issue_id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function articlePageAction(Request $request, $article_id, $issue_id = null)
    {
        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);


        $response = $this->forward('VipaSiteBundle:Article:articlePage', array(
            'slug' => $journal->getSlug(),
            'article_id' => $article_id,
            'issue_id' => $issue_id,
            'isJournalHosting' => true,
        ));

        return $response;

    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function archiveIndexAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:archiveIndex', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function journalArticlesAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Article:journalArticles', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function lastAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:lastArticlesIndex', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function announcementAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:JournalCms:announcementIndex', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function earlyPreviewAction(Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:Journal:earlyPreviewIndex', array(
            'slug' => $journal->getSlug(),
            'isJournalHosting' => true,
        ));

        return $response;
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pageAction(Request $request, $slug)
    {

        $em = $this->getDoctrine()->getManager();
        $currentHost = $request->getHttpHost();

        $journal = $em->getRepository(Journal::class)->findOneBy(
            array('domain' => $currentHost, 'status' => JournalStatuses::STATUS_PUBLISHED)
        );
        $this->throw404IfNotFound($journal);

        $response = $this->forward('VipaSiteBundle:JournalCms:journalPageDetail', array(
            'journal_slug' => $journal->getSlug(),
            'slug' => $slug,
            'isJournalHosting' => true,
        ));

        return $response;
    }
}
