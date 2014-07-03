<?php

namespace Ojstr\JournalBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Ojstr\Common\Controller\OjsController as Controller;
use Ojstr\JournalBundle\Entity\Article;
//use Ojstr\JournalBundle\Form\ArticleType;
//use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Article submission step controller
 */
class ArticleSubmissionStepController extends Controller {

    /**
     * Lists all new Article submissions entities.
     *
     */
    public function step1Action(Request $request, $locale) {
        $em = $this->getDoctrine()->getManager();
        $article = new Article();
        $article->setTitle($request->get('title'));
        $article->setSubtitle($request->get('subtitle'));
        $article->setTitleTransliterated($request->get('titleTransliterated'));
        $article->setDoi($request->get('doi'));
        $article->setKeywords($request->get('keywords'));
        $article->setAbstract($request->get('abstract'));
        $article->setJournalId($request->get('journalId'));
        $em->persist($article);
        $em->flush();
        /* todo */
        return new JsonResponse(array('id' => $article->getId()));
    }

    /**
     * @todo
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function step2Action(Request $request) {
        $em = $this->getDoctrine()->getManager();
    }

    /**
     * @todo
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function step3Action(Request $request) {
        $em = $this->getDoctrine()->getManager();
    }

}
