<?php

namespace Vipa\SiteBundle\Controller;

use Elastica\Query\MatchAll;
use Vipa\CoreBundle\Controller\VipaController as Controller;
use Vipa\CoreBundle\Helper\TreeHelper;
use Vipa\CoreBundle\Params\PublisherStatuses;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\Subject;
use Vipa\JournalBundle\Entity\SubjectRepository;
use Symfony\Component\HttpFoundation\Response;

class SiteController extends Controller
{
    /**
     * Global index page
     * @return Response
     */
    public function indexAction()
    {
        $data['page'] = 'index';

        $em = $this->getDoctrine()->getManager();
        $data['journals'] = $em->getRepository('VipaJournalBundle:Journal')->getHomePageList($this->get('file_cache'));
        shuffle($data['journals']);

        /** @var SubjectRepository $repo */
        $repo = $em->getRepository('VipaJournalBundle:Subject');

        $allSubjects = $repo->findAll();
        usort($allSubjects, function($a, $b) {
            return $b->getRgt() > $a->getRgt();
        });
        $data['subjects'] = TreeHelper::createSubjectTreeView(TreeHelper::SUBJECT_SEARCH, $this->get('router'), $allSubjects);
        $data['page'] = 'index';

        $data['stats'] = [
            'journal' => 0,
            'article' => 0,
            'subject' => 0,
            'publisher' => 0,
            'user' => 0
        ];

        $data['stats']['journal'] = $this->get('fos_elastica.index.search.journal')->count(new MatchAll());
        $data['stats']['article'] = $this->get('fos_elastica.index.search.articles')->count(new MatchAll());
        $data['stats']['subject'] = $this->get('fos_elastica.index.search.subject')->count(new MatchAll());
        $data['stats']['publisher'] = $this->get('fos_elastica.index.search.publisher')->count(new MatchAll());
        $data['stats']['user'] = $this->get('fos_elastica.index.search.user')->count(new MatchAll());

        $data['announcements'] = $em->getRepository('VipaAdminBundle:AdminAnnouncement')->findAll();
        $data['announcement_count'] = count($data['announcements']);
        $data['posts'] = $em->getRepository('VipaAdminBundle:AdminPost')->findAll();

        // anything else is anonym main page
        return $this->render('VipaSiteBundle::Site/home.html.twig', $data);
    }

    public function publisherPageAction($slug)
    {
        $data['page'] = 'organizations';
        $journalService = $this->get('vipa.journal_service');
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('VipaJournalBundle:Publisher')->findOneBy(['slug' => $slug, 'status' => PublisherStatuses::STATUS_COMPLETE]);
        $this->throw404IfNotFound($entity);
        $data['entity'] = $entity;
        /** @var Journal $journal */
        foreach ($entity->getJournals() as $journal) {
            $journal->setPublicURI($journalService->generateUrl($journal));
        }

        return $this->render('VipaSiteBundle::Publisher/publisher_index.html.twig', $data);
    }


}
