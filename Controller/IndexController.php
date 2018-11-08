<?php

namespace Modera\MJRSecurityIntegrationBundle\Controller;

use Modera\MjrIntegrationBundle\AssetsHandling\AssetsProvider;
use Modera\SecurityBundle\Security\Authenticator;
use Modera\MjrIntegrationBundle\Config\MainConfigInterface;
use Modera\MjrIntegrationBundle\ClientSideDependencyInjection\ServiceDefinitionsManager;
use Modera\MjrIntegrationBundle\DependencyInjection\ModeraMjrIntegrationExtension;
use Modera\MJRSecurityIntegrationBundle\ModeraMJRSecurityIntegrationBundle;
use Modera\MJRSecurityIntegrationBundle\DependencyInjection\ModeraMJRSecurityIntegrationExtension;
use Sli\ExpanderBundle\Ext\ContributorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entry point to web application.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class IndexController extends Controller
{
    /**
     * Entry point MF backend.
     *
     * @Route("/")
     *
     * @return array
     */
    public function indexAction()
    {
        $runtimeConfig = $this->container->getParameter(ModeraMjrIntegrationExtension::CONFIG_KEY);
        $securedRuntimeConfig = $this->container->getParameter(ModeraMJRSecurityIntegrationExtension::CONFIG_KEY);

        /* @var ContributorInterface $classLoaderMappingsProvider */
        $classLoaderMappingsProvider = $this->get('modera_mjr_integration.bootstrapping_class_loader_mappings_provider');

        /* @var MainConfigInterface $mainConfig */
        $mainConfig = $this->container->get($runtimeConfig['main_config_provider']);
        $runtimeConfig['home_section'] = $mainConfig->getHomeSection();
        $runtimeConfig['deployment_name'] = $mainConfig->getTitle();
        $runtimeConfig['deployment_url'] = $mainConfig->getUrl();
        $runtimeConfig['class_loader_mappings'] = $classLoaderMappingsProvider->getItems();

        // for docs regarding how to use "non-blocking" assets see
        // \Modera\MjrIntegrationBundle\AssetsHandling\AssetsProvider class

        /* @var AssetsProvider $assetsProvider */
        $assetsProvider = $this->get('modera_mjr_integration.assets_handling.assets_provider');

        /* @var RouterInterface $router */
        $router = $this->get('router');
        // converting URL like /app_dev.php/backend/ModeraFoundation/Application.js to /app_dev.php/backend/ModeraFoundation
        $appLoadingPath = $router->generate('modera_mjr_security_integration.index.application');
        $appLoadingPath = substr($appLoadingPath, 0, strpos($appLoadingPath, 'Application.js') - 1);

        /* @var Kernel $kernel */
        $kernel = $this->get('kernel');

        $content = $this->renderView(
            'ModeraMJRSecurityIntegrationBundle:Index:index.html.twig',
            array(
                'config' => array_merge($runtimeConfig, $securedRuntimeConfig),
                'css_resources' => $assetsProvider->getCssAssets(AssetsProvider::TYPE_BLOCKING),
                'js_resources' => $assetsProvider->getJavascriptAssets(AssetsProvider::TYPE_BLOCKING),
                'app_loading_path' => $appLoadingPath,
                'disable_caching' => $kernel->getEnvironment() != 'prod',
            )
        );

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/html');

        return $response;
    }

    /**
     * Dynamically generates an entry point to backend application, action's output is JavaScript class
     * which is used by ExtJs to bootstrap application.
     *
     * @see Resources/config/routing.yml
     * @see \Modera\MJRSecurityIntegrationBundle\Contributions\RoutingResourcesProvider
     */
    public function applicationAction()
    {
        /* @var AssetsProvider $assetsProvider */
        $assetsProvider = $this->get('modera_mjr_integration.assets_handling.assets_provider');

        $nonBlockingResources = array(
            'css' => $assetsProvider->getCssAssets(AssetsProvider::TYPE_NON_BLOCKING),
            'js' => $assetsProvider->getJavascriptAssets(AssetsProvider::TYPE_NON_BLOCKING),
        );

        /* @var ServiceDefinitionsManager $definitionsMgr */
        $definitionsMgr = $this->container->get('modera_mjr_integration.csdi.service_definitions_manager');
        $content = $this->renderView(
            'ModeraMJRSecurityIntegrationBundle:Index:application.html.twig',
            array(
                'non_blocking_resources' => $nonBlockingResources,
                'container_services' => $definitionsMgr->getDefinitions(),
                'config' => $this->container->getParameter(ModeraMjrIntegrationExtension::CONFIG_KEY),
            )
        );

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/javascript');

        return $response;
    }

    /**
     * Endpoint can be used by MJR to figure out if user is already authenticated and therefore
     * runtime UI can be loaded.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function isAuthenticatedAction(Request $request)
    {
        $this->initSession($request);

        /* @var TokenStorageInterface $sc */
        $sc = $this->get('security.token_storage');
        $token = $sc->getToken();

        $response = Authenticator::getAuthenticationResponse($token);

        if ($response['success']) {
            if (!$this->isGranted(ModeraMJRSecurityIntegrationBundle::ROLE_BACKEND_USER, $token->getUser())) {
                $response = array(
                    'success' => false,
                    'message' => "You don't have required rights to access administration interface.",
                );
            }
        }

        return new JsonResponse($response);
    }

    /**
     * @param Request $request
     */
    private function initSession(Request $request)
    {
        $session = $request->getSession();
        if ($session instanceof Session && !$session->getId()) {
            $session->start();
        }
    }
}
