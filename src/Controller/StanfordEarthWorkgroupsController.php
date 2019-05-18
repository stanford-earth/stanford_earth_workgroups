<?php

namespace Drupal\stanford_earth_workgroups\Controller;

use Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\system\Controller\Http4xxController;

/**
 * Workgroup test output controller.
 */
class StanfordEarthWorkgroupsController extends ControllerBase {

  /**
   * PageCache KillSwitch service.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killswitch;
  /**
   * Stanford Earth Workgroups Service
   *
   * @var \Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService
   */
  protected $wg_service;
  /**
   * Symfony Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requeststack;


  /**
   * StanfordEarthSamlController constructor.
   *
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killswitch
   *   The KillSwitch service.
   * @param \Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService
   *   The Workgroups service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $rstack
   *   The Symfony request stack.
   */
  public function __construct(KillSwitch $killswitch,
                              StanfordEarthWorkgroupsService $wg_service,
                              RequestStack $rstack) {

    $this->killswitch = $killswitch;
    $this->wg_service = $wg_service;
    $this->requeststack = $rstack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('stanford_earth_workgroups.workgroup'),
      $container->get('request_stack')
    );
  }

  /**
   * Returns a render-able array for a test page.
   */
  public function content() {
    // Make sure Varnish doesn't cache the redirect.
    $this->killswitch->trigger();
    $wg = htmlspecialchars($this->requeststack->getCurrentRequest()->get('wg'));
    $wg_array = $this->wg_service->getMembers($wg);
    $wg_array_str = '<pre>' . print_r($wg_array, TRUE) . '</pre>';
    $build = [
      '#markup' => $wg_array_str,
    ];
    return $build;
  }

}

