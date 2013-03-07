<?php

/**
 * @author Christian Wygoda
 */

namespace Mapbender\WmsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOM\ManagerBundle\Configuration\Route as ManagerRoute;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Mapbender\WmsBundle\Entity\WmsInstance;
use Mapbender\WmsBundle\Entity\WmsLayerSource;
use Mapbender\WmsBundle\Entity\WmsSource;
use Mapbender\WmsBundle\Component\WmsCapabilitiesParser;
use Mapbender\WmsBundle\Form\Type\WmsInstanceInstanceLayersType;
use Mapbender\WmsBundle\Form\Type\WmsSourceSimpleType;
use Mapbender\WmsBundle\Form\Type\WmsSourceType;
use Mapbender\WmsBundle\Form\Type\WmsInstanceType;
use Mapbender\CoreBundle\Component\Utils;
use Mapbender\CoreBundle\Component\Exception\XmlParseException;
use Mapbender\CoreBundle\Component\Exception\NotSupportedVersionException;
use Mapbender\WmsBundle\Component\Exception\WmsException;
use OwsProxy3\CoreBundle\Component\ProxyQuery;
use OwsProxy3\CoreBundle\Component\CommonProxy;

/**
 * @ManagerRoute("/repository/wms")
 */
class RepositoryController extends Controller
{

    /**
     * @ManagerRoute("/new")
     * @Method({ "GET" })
     * @Template
     */
    public function newAction()
    {
        $form = $this->get("form.factory")->create(new WmsSourceSimpleType(),
                                                   new WmsSource());
        return array(
            "form" => $form->createView()
        );
    }

    /**
     * @ManagerRoute("/start")
     * @Method({ "GET" })
     * @Template
     */
    public function startAction()
    {
        $form = $this->get("form.factory")->create(new WmsSourceSimpleType(),
                                                   new WmsSource());
        return array(
            "form" => $form->createView()
        );
    }

    /**
     * @ManagerRoute("{wms}")
     * @Method({ "GET"})
     * @Template
     */
    public function viewAction(WmsSource $wms)
    {
        return array("wms" => $wms);

        /*
          return $this
          ->get("templating")
          ->render("MapbenderWmsBundle:Repository:view.html.twig",array("wms" => $wms));
         */
    }

    /**
     * @ManagerRoute("/new")
     * @Method({ "POST" })
     * @Template("MapbenderWmsBundle:Repository:new.html.twig")
     */
    public function createAction()
    {
        $request = $this->get('request');
        $wmssource_req = new WmsSource();
        $form = $this->get("form.factory")->create(new WmsSourceSimpleType(),
                                                   $wmssource_req);
        $form->bindRequest($request);
        if($form->isValid())
        {
            $proxy_config = $this->container->getParameter("owsproxy.proxy");
//            $headers = \OwsProxy3\CoreBundle\Component\Utils::getHeadersFromRequest($request);
            $proxy_query = ProxyQuery::createFromUrl(trim($wmssource_req->getOriginUrl()),
                                                          $wmssource_req->getUsername(),
                                                          $wmssource_req->getPassword());
            if($proxy_query->getGetPostParamValue("request", true) === null)
            {
                $proxy_query->addQueryParameter("request", "GetCapabilities");
            }
            if($proxy_query->getGetPostParamValue("service", true) === null)
            {
                $proxy_query->addQueryParameter("service", "WMS");
            }
            $wmssource_req->setOriginUrl($proxy_query->getGetUrl());
            $proxy = new CommonProxy($proxy_config, $proxy_query);

            $wmssource = null;
            try
            {
                $browserResponse = $proxy->handle();
                $content = $browserResponse->getContent();
                $doc = WmsCapabilitiesParser::createDocument($content);
                $wmsParser = WmsCapabilitiesParser::getParser($doc);
                $wmssource = $wmsParser->parse();
            } catch(\Exception $e)
            {
                $this->get("logger")->debug($e->getMessage());
                $this->get('session')->setFlash('error', $e->getMessage());
                return $this->redirect($this->generateUrl(
                                        "mapbender_manager_repository_new",
                                        array(), true));
            }

            if(!$wmssource)
            {
                $this->get("logger")->debug('Could not parse data for url "'
                        . $wmssource_req->getOriginUrl() . '"');
                $this->get('session')->setFlash('error',
                                                'Could not parse data for url "'
                        . $wmssource_req->getOriginUrl() . '"');
                return $this->redirect($this->generateUrl(
                                        "mapbender_manager_repository_new",
                                        array(), true));
            }
            $wmsWithSameTitle = $this->getDoctrine()
                    ->getEntityManager()
                    ->getRepository("MapbenderWmsBundle:WmsSource")
                    ->findByTitle($wmssource->getTitle());

            if(count($wmsWithSameTitle) > 0)
            {
                $wmssource->setAlias(count($wmsWithSameTitle));
            }

            $wmssource->setOriginUrl($wmssource_req->getOriginUrl());

            $this->getDoctrine()->getEntityManager()->persist($wmssource);
            $this->getDoctrine()->getEntityManager()->flush();
        }
        return $this->redirect($this->generateUrl(
                                "mapbender_manager_repository_view",
                                array(
                            "sourceId" => $wmssource->getId()), true));
    }

    /**
     * deletes a WmsSource
     * @ManagerRoute("/{sourceId}/delete")
     * @Method({"GET"})
     */
    public function deleteAction($sourceId)
    {
        $wmssource = $this->getDoctrine()
                ->getRepository("MapbenderWmsBundle:WmsSource")
                ->find($sourceId);
        $wmsinstances = $this->getDoctrine()
                ->getRepository("MapbenderWmsBundle:WmsInstance")
                ->findBySource($sourceId);
        $em = $this->getDoctrine()->getEntityManager();
        foreach($wmsinstances as $wmsinstance)
        {
            $em->remove($wmsinstance);
            $em->flush();
        }
        $this->removeRecursive($wmssource->getRootlayer(), $em);
        $em->remove($wmssource);
        $em->flush();
        $this->get('session')->setFlash('info', "Service deleted");
        return $this->redirect($this->generateUrl("mapbender_manager_repository_index"));
    }

    /**
     * Recursively remove a nested Layerstructure
     * @param GroupLayer
     * @param EntityManager
     */
    public function removeRecursive(WmsLayerSource $wmslayer, $em)
    {
//        $this->removeRecursive($wmssource->getRootlayer(),
//                        $this->getDoctrine()->getEntityManager());
        foreach($wmslayer->getSublayer() as $sublayer)
        {
            $this->removeRecursive($sublayer, $em);
        }
        $em->remove($wmslayer);
        $em->flush();
    }

    /**
     * Edits, saves the WmsInstance
     * 
     * @ManagerRoute("/instance/{slug}/{instanceId}")
     * @Template("MapbenderWmsBundle:Repository:instance.html.twig")
     */
    public function instanceAction($slug, $instanceId)
    {
        if($this->getRequest()->getMethod() == 'POST')
        { //save
            $wmsinstance = $this->getDoctrine()
                    ->getRepository("MapbenderWmsBundle:WmsInstance")
                    ->find($instanceId);
            $wmsinstance_req = new WmsInstance();
            $wmsinstance_req->setSource($wmsinstance->getSource());
            $form_req = $this->createForm(
                    new WmsInstanceInstanceLayersType(), $wmsinstance_req);
            $form_req->bindRequest($this->get('request'));
            $form = $this->createForm(
                    new WmsInstanceInstanceLayersType(), $wmsinstance);
            $form->bindRequest($this->get('request'));
            $wmsinstance->setTransparency(
                    Utils::getBool($wmsinstance_req->getTransparency()));
            $wmsinstance->setVisible(
                    Utils::getBool($wmsinstance_req->getVisible()));
            $wmsinstance->setOpacity(
                    Utils::getBool($wmsinstance_req->getOpacity()));
            $wmsinstance->setProxy(
                    Utils::getBool($wmsinstance_req->getProxy()));
            $wmsinstance->setTiled(
                    Utils::getBool($wmsinstance_req->getTiled()));
            foreach($wmsinstance->getLayers() as $layer)
            {
                foreach($wmsinstance_req->getLayers() as $layer_tmp)
                {
                    if($layer_tmp->getId() === $layer->getId())
                    {
                        $layer->setActive(Utils::getBool(
                                        $layer_tmp->getActive()));
                        $layer->setSelected(Utils::getBool(
                                        $layer_tmp->getSelected()));
                        $layer->setSelectedDefault(Utils::getBool(
                                        $layer_tmp->getSelectedDefault()));
                        $layer->setInfo(Utils::getBool(
                                        $layer_tmp->getInfo(), true));
                        $layer->setAllowinfo(Utils::getBool(
                                        $layer_tmp->getAllowinfo(), true));
                        break;
                    }
                }
            }
            if($form->isValid())
            { //save
                $this->getDoctrine()->getEntityManager()->persist($wmsinstance);
//                $configuration = $wmsinstance->getConfiguration();
//                foreach ($wmsinstance->getMblayer() as $mblayer) {
//                    $mblayer->setConfiguration($configuration);
//                    $this->getDoctrine()->getEntityManager()->persist($mblayer);
//                }
                $this->getDoctrine()->getEntityManager()->flush();

                $this->get('session')->setFlash(
                        'notice', 'Your Wms Instance has been changed.');
                return $this->redirect($this->generateUrl(
                                        'mapbender_manager_application_edit',
                                        array("slug" => $slug)) . '#layersets');
            } else
            { // edit
                return array(
                    "form" => $form->createView(),
                    "slug" => $slug,
                    "instance" => $wmsinstance);
            }
        } else
        { // edit
            $wmsinstance = $this->getDoctrine()
                    ->getRepository("MapbenderWmsBundle:WmsInstance")
                    ->find($instanceId);
            $form = $this->createForm(
                    new WmsInstanceInstanceLayersType(), $wmsinstance);
            $fv = $form->createView();
            return array(
                "form" => $form->createView(),
                "slug" => $slug,
                "instance" => $wmsinstance);
        }
    }

//    /**
//     * Changes the priority of WmsInstanceLayers
//     * 
//     * @ManagerRoute("/{slug}/instance/{layersetId}/weight/{instanceId}")
//     */
//    public function instanceWeightAction($slug, $layersetId, $instanceId)
//    {
//        $number = $this->get("request")->get("number");
//        $new_layersetId = $this->get("request")->get("new_layersetId");
////        $number = intval($number) - 1;
//        $instance = $this->getDoctrine()
//                ->getRepository('MapbenderWmsBundle:WmsInstance')
//                ->findOneById($instanceId);
//        
//        if(!$instance)
//        {
//            throw $this->createNotFoundException('The wms instance with"
//                ." the id "' . $instanceId . '" does not exist.');
//        }
//        if(intval($number) === $instance->getWeight() && $layersetId === $new_layersetId)
//        {
//            return new Response(json_encode(array(
//                                'error' => '',
//                                'result' => 'ok')), 200,
//                            array('Content-Type' => 'application/json'));
//        }
//        $ls = $instance->getLayerset();
//        if($layersetId === $new_layersetId)
//        {
//            $em = $this->getDoctrine()->getEntityManager();
//            $instance->setWeight($number);
//            $em->persist($instance);
//            $em->flush();
//            $query = $em->createQuery(
//                    "SELECT i FROM MapbenderWmsBundle:WmsInstance i"
//                    . " WHERE i.layerset=:lsid ORDER BY i.weight ASC");
//            $query->setParameters(array("lsid" => $layersetId));
//            $instList = $query->getResult();
//
//            $num = 0;
//            foreach($instList as $inst)
//            {
//                if($num === intval($instance->getWeight()))
//                {
//                    if($instance->getId() === $inst->getId())
//                    {
//                        $num++;
//                    } else
//                    {
//                        $num++;
//                        $inst->setWeight($num);
//                        $num++;
//                    }
//                } else
//                {
//                    if($instance->getId() !== $inst->getId())
//                    {
//                        $inst->setWeight($num);
//                        $num++;
//                    }
//                }
//            }
//            foreach($instList as $inst)
//            {
//                $em->persist($inst);
//            }
//            $em->flush();
//        } else
//        {
//            
//        }
//
//        return new Response(json_encode(array(
//                            'error' => '',
//                            'result' => 'ok')), 200, array(
//                    'Content-Type' => 'application/json'));
//    }

    /**
     * Changes the priority of WmsInstanceLayers
     * 
     * @ManagerRoute("/{slug}/instance/{instanceId}/priority/{instLayerId}")
     */
    public function instanceLayerPriorityAction($slug, $instanceId, $instLayerId)
    {
        $number = $this->get("request")->get("number");
//        $number = intval($number) - 1;
        $instLay = $this->getDoctrine()
                ->getRepository('MapbenderWmsBundle:WmsInstanceLayer')
                ->findOneById($instLayerId);

        if(!$instLay)
        {
//            throw $this->createNotFoundException('The wms instance layer with"
//                ." the id "'. $instanceId .'" does not exist.');
            return new Response(json_encode(array(
                                'error' => 'The wms instance layer with'
                                . ' the id "' . $instanceId . '" does not exist.',
                                'result' => '')), 200,
                            array('Content-Type' => 'application/json'));
        }
        if(intval($number) === $instLay->getPriority())
        {
            return new Response(json_encode(array(
                                'error' => '',
                                'result' => 'ok')), 200,
                            array('Content-Type' => 'application/json'));
        }
        $em = $this->getDoctrine()->getEntityManager();
        $instLay->setPriority($number);
        $em->persist($instLay);
        $em->flush();
        $query = $em->createQuery(
                "SELECT il FROM MapbenderWmsBundle:WmsInstanceLayer il"
                . " WHERE il.wmsinstance=:wmsi ORDER BY il.priority ASC");
        $query->setParameters(array("wmsi" => $instanceId));
        $instList = $query->getResult();

        $num = 0;
        foreach($instList as $inst)
        {
            if($num === intval($instLay->getPriority()))
            {
                if($instLay->getId() === $inst->getId())
                {
                    $num++;
                } else
                {
                    $num++;
                    $inst->setPriority($num);
                    $num++;
                }
            } else
            {
                if($instLay->getId() !== $inst->getId())
                {
                    $inst->setPriority($num);
                    $num++;
                }
            }
        }
        foreach($instList as $inst)
        {
            $em->persist($inst);
        }
        $em->flush();
        return new Response(json_encode(array(
                            'error' => '',
                            'result' => 'ok')), 200, array(
                    'Content-Type' => 'application/json'));
    }

    /**
     * Sets enabled/disabled for the WmsInstance
     * 
     * @ManagerRoute("/instance/{slug}/enabled/{instanceId}")
     */
    public function instanceEnabledAction($slug, $instanceId)
    {
        $enabled = $this->get("request")->get("enabled");
        $wmsinstance = $this->getDoctrine()
                ->getRepository("MapbenderWmsBundle:WmsInstance")
                ->find($instanceId);
        if(!$wmsinstance)
        {
            return new Response(
                            json_encode(array(
                                'error' => 'The wms instance with the id "' . $instanceId . '" does not exist.',
                                'result' => 'ok')),
                            200,
                            array('Content-Type' => 'application/json'));
        } else
        {
            $enabled = $enabled === null ? false : $enabled;
            $wmsinstance->setEnabled($enabled);
            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($wmsinstance);
            $em->flush();
            return new Response(
                            json_encode(array(
                                'error' => '',
                                'result' => 'ok')),
                            200,
                            array('Content-Type' => 'application/json'));
        }
    }

}
