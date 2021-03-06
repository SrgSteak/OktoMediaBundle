<?php

namespace Okto\MediaBundle\Controller;

use Oktolab\MediaBundle\Controller\SeriesController as BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Okto\MediaBundle\Form\EpisodeType;
use Okto\MediaBundle\Form\SeriesUserType;
use Okto\MediaBundle\Form\SeriesTagType;
use Okto\MediaBundle\Form\SeriesEpisodeType;
use Okto\MediaBundle\Form\SeriesStateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * @Route("/backend/oktolab_media/series")
 * @ Security("has_role('ROLE_OKTOLAB_MEDIA_READ')")
 */
class SeriesController extends BaseController
{
    /**
     * Finds and displays a Series entity.
     * @ ParamConverter("series", class="OktoMediaBundle:Series")
     * @Route("/show/{series}", name="oktolab_series_show")
     * @Method("GET")
     * @Template()
     */
    public function showAction($series)
    {
        $series = $this->get('oktolab_media')->getSeries($series);
        if ($series) {
            return ['series' => $series];
        }

        $this->get('session')->getFlashBag()->add(
            'info',
            'oktolab_media.series_not_found'
        );
        return $this->redirect($this->generateUrl('oktolab_series'));
    }

    /**
     * Finds and displays a Series entity.
     * @ParamConverter("series", class="OktoMediaBundle:Series")
     * @Route("/{series}/paginate", name="media_episode_paginator")
     * @Method("GET")
     * @Template()
     */
    public function paginationEpisodesAction(Request $request, $series)
    {
        $em = $this->getDoctrine()->getManager();
        $dql = "SELECT e FROM ".$this->container->getParameter('oktolab_media.episode_class')." e WHERE e.series = :series ORDER By e.firstranAt DESC";
        $query = $em->createQuery($dql);
        $query->setParameter('series', $series->getId());

        $page = $request->query->get('page', 1);
        $results = $request->query->get('results', 20);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $page/*page number*/,
            $results
        );
        $pagination->setUsedRoute('media_episode_paginator', ['series' => $series]);
        $pagination->setParam('series', $series->getId());

        return ['pagination' => $pagination];
    }

    /**
     * @Route("/{series}/edit_user", name="oktolab_series_edit_user")
     * @Method({"GET", "POST"})
     * @Template()
     */
    public function editUserAction(Request $request, $series)
    {
        $series = $this->get('oktolab_media')->getSeries($series);
        $form = $this->createForm(SeriesUserType::class, $series);
        $form->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'flux2.series_edit_user_button',
                'attr' => ['class' => 'btn btn-default']
            ]
        );

        if ($request->getMethod() == "POST") {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($series);
                $em->flush();
                $this->get('session')->getFlashBag()->add('success', 'okto_media.series_edit_user_success');
                return $this->redirect($this->generateUrl('oktolab_series_show', ['series' => $series->getUniqID()]));
            } else {
                $this->get('session')->getFlashBag()->add('error', 'okto_media.series_edit_user_error');
            }
        }
        return ['form' => $form->createView(), 'series' => $series];
    }

    /**
     * @Route("/{series}/edit_tags", name="oktolab_series_edit_tags")
     * @Method({"GET", "POST"})
     * @Template()
     */
    public function editTagAction(Request $request, $series)
    {
        $series = $this->get('oktolab_media')->getSeries($series);
        $form = $this->createForm(SeriesTagType::class, $series);
        $form->add(
            'submit',
            SubmitType::class,
            [
                'label' => 'okto_media.series_edit_tag_button',
                'attr' => ['class' => 'btn btn-primary']
            ]
        );

        if ($request->getMethod() == "POST") {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($series);
                $em->flush();
                $this->get('session')->getFlashBag()->add(
                    'success',
                    'okto_media.series_edit_tag_success'
                );
                return $this->redirect(
                    $this->generateUrl(
                        'oktolab_series_show',
                        ['series' => $series->getUniqID()]
                    )
                );

            } else {
                $this->get('session')->getFlashBag()->add(
                    'error',
                    'okto_media.series_edit_tag_error'
                );
            }
        }
        return ['form' => $form->createView(), 'series' => $series];
    }

    /**
     * @Route("/user_by_username", name="oktolab_series_user_by_username")
     */
    public function ajaxUserByUsernameAction(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $users = $em->getRepository('AppBundle:User')->findAll();
            $json = [];
            foreach ($users as $user) {
                $json[] = $user->getUsername();
            }
            return new Response(json_encode($json));
        }
        return $this->redirect('homepage');
    }

    /**
     * @Route("/{uniqID}/tag/publicate", name="okto_media_series_tag_publicate")
     */
    public function publicateTagsAction($uniqID)
    {
        $this->get('okto_media')->publicateSeriesTags($uniqID);
        $this->get('session')->getFlashBag()->add('success', 'okto_media.series_publicate_tag_success');
        return $this->redirect($this->generateUrl('oktolab_series_show', ['series' => $uniqID]));
    }

    /**
     * @Route("/{uniqID}/episode/new", name="okto_media_new_episode_for_series")
     * @Template("OktoMediaBundle:series:new_episode.html.twig")
     */
    public function newEpisodeAction(Request $request, $uniqID)
    {
        $episode = $this->get('okto_media')->createEpisodeForSeries($uniqID);
        $form = $this->createForm(SeriesEpisodeType::class, $episode);
        $form->add('submit', SubmitType::class, ['label' => 'oktolab_media.create_episode_button', 'attr' => ['class' => 'btn btn-primary']]);
        $form->add('submitAndEncode', SubmitType::class, ['label' => 'okto_media.create_episode_submitAndEncode_button', 'attr' => ['class' => 'btn btn-default']]);

        if ($request->getMethod() == "POST") { //sends form
            $form->handleRequest($request);
            $em = $this->getDoctrine()->getManager();
            if ($form->isValid() || $form->get('delete')->isClicked()) { //form is valid, save or preview
                if ($form->get('submit')->isClicked()) { //save me
                    $em->persist($episode);
                    $em->flush();
                    $this->get('session')->getFlashBag()->add('success', 'oktolab_media.success_edit_episode');
                    return $this->redirect(
                        $this->generateUrl(
                            'oktolab_episode_show',
                            ['uniqID' => $episode->getUniqID()]
                        )
                    );
                } elseif ($form->get('submitAndEncode')->isClicked()) {
                    $em->persist($episode);
                    $em->flush();
                    $this->get('oktolab_media')->addEncodeEpisodeJob($episode->getUniqID());
                    $this->get('session')->getFlashBag()->add('success', 'okto_media.success_create_and_encode_episode');
                    return $this->redirect($this->generateUrl('oktolab_episode_show', ['uniqID' => $episode->getUniqID()]));
                } elseif ($form->get('delete')->isClicked()) {
                    $em->remove($episode);
                    $em->flush();
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'oktolab_media.success_delete_episode'
                    );
                    return $this->redirect($this->generateUrl('backend'));
                } else { //???
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'oktothek.info_edit_episode_unknown_button'
                    );
                    return $this->redirect(
                        $this->generateUrl(
                            'oktolab_episode_show',
                            ['uniqID' => $episode->getUniqID()]
                        )
                    );
                }
            }
            $this->get('session')->getFlashBag()->add('error', 'oktothek.error_edit_episode');
        }

        return ['form' => $form->createView()];
    }

    /**
     * @Route("/{uniqID}/set_state", name="okto_media_set_state")
     * @Template()
     */
    public function setActiveAction(Request $request, $uniqID)
    {
        $mediaService = $this->get('okto_media');
        $series = $mediaService->getSeries($uniqID);
        $form = $this->createForm(SeriesStateType::class);
        $form->add(
            'sumbit',
            SubmitType::class,
            [
                'label' =>'okto_media.set_series_state_button',
                'attr' => ['class' => 'btn btn-primary']
            ]
        );

        if ($form->isSubmitted()) { //sends form
            $form->handleRequest($request);
            if ($form->isValid()) {
                $mediaService->setSeriesState(
                    $series,
                    $form->get('state')->getData()
                );
                $this->get('session')->getFlashBag()->add('success', 'okto_media.success_set_state');
                return $this->redirect(
                    $this->generateUrl('oktolab_series_show', ['series' => $uniqID])
                );
            }
        }

        return ['form' => $form->createView(), 'series' => $series];
    }
}
