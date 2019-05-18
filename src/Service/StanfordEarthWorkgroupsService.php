<?php

namespace Drupal\stanford_earth_workgroups\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Session\AccountProxy;
use GuzzleHttp\ClientInterface;

/**
 * Service to return members of a Stanford Workgroup.
 */
class StanfordEarthWorkgroupsService {

  /**
   * Global config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Mail Manager service.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Global site settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a StanfordEarthWorkgroupsService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Drupal config data.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Mail\MailManager $mailmgr
   *   The mail manager service.
   * @param \Drupal\Core\Session\AccountProxy $curUser
   *   The current Drupal user.
   */
  public function __construct(ClientInterface $http_client,
                              ConfigFactory $config = NULL,
                              LoggerChannelFactoryInterface $logger_factory,
                              MailManager $mailmgr,
                              AccountProxy $curUser) {
    $this->httpClient = $http_client;
    $this->config = $config->get('stanford_earth_workgroups.adminsettings');
    $this->logger = $logger_factory->get('system');
    $this->mailManager = $mailmgr;
    $this->currentUser = $curUser;
    $this->settings = $config->get('system.site');
  }

  /**
   * Email a workgroup error message to the site admin.
   *
   * @param array $status
   *   The request status including an error message.
   */
  private function emailErrorToAdmin(array $status) {
    $mod = 'stanford_earth_workgroups';
    $key = 'workgroup_error';
    $lang = $this->currentUser->getPreferredLangcode();
    $to = $this->settings->get('mail');
    $send = TRUE;
    $result = $this->mailManager->mail($mod, $key, $to, $lang, $status,
      NULL, $send);
    if ($result['result'] !== TRUE) {
      $this->logger->error($status['message']);
    }
  }

  /**
   * Queries the Workgroup API and returns workgroup members in an array.
   *
   * @param string $wg
   *   The name of the workgroup.
   * @param string $certin
   *   The SSL certificate allowing access to the Workgroup API.
   * @param string $keyin
   *   The SSL key allowing access to the Workgroup API.
   *
   * @return array
   *   An array of workgroup members.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getMembers(string $wg, string $certin = NULL, string $keyin = NULL) {
    $status = ['workgroup' => $wg, 'member_count' => 0];
    try {
      if (!empty($certin) || !empty($keyin)) {
        $key = $keyin;
        $cert = $certin;
      }
      else {
        $key = $this->config->get('stanford_earth_workgroups_key');
        $cert = $this->config->get('stanford_earth_workgroups_cert');
      }

      if (empty($key) || empty($cert)) {
        $errmsg = 'Error getting workgroup ' . $wg .
          '. Workgroup API credentials have not been set.';
        $this->logger->notice($errmsg);
        $status['message'] = $errmsg;
        return ['members' => [], 'status' => $status];
      }

      $result = $this->httpClient->request('GET',
        'https://workgroupsvc.stanford.edu/v1/workgroups/' . $wg,
        ['cert' => $cert, 'ssl_key' => $key]);
      if ($result->getStatusCode() != 200) {
        $errmsg = 'Error getting workgroup ' . $wg . '. ' . $result->getReasonPhrase();
        $this->logger->error($errmsg);
        $status['message'] = $errmsg;
        $this->emailErrorToAdmin($status);
        return ['members' => [], 'status' => $status];
      }
      $xml = simplexml_load_string($result->getBody());
      $xpath = $xml->xpath('members');
      $id_attribute = 'id';
      $name_attribute = 'name';
      $sunets = [];
      if (is_array($xpath)) {
        $xpath0 = reset($xpath);
        if ($xpath0 !== FALSE) {
          $members = $xpath0->xpath('member');
          if (is_array($members)) {
            foreach ($members as $member) {
              $sunetid = (string) $member->attributes()->$id_attribute;
              $name = (string) $member->attributes()->$name_attribute;
              $sunets[$sunetid] = $name;
            }
          }
          $workgroups = $xpath0->xpath('workgroup');
          if (is_array($workgroups)) {
            foreach ($workgroups as $next_wg) {
              $nested = (string) $next_wg->attributes()->$id_attribute;
              $subsunets = $this->getMembers($nested, $cert, $key);
              if ($subsunets['status']['member_count'] > 0) {
                $sunets = array_merge($sunets, $subsunets['members']);
              }
            }
          }
        }
      }
      if (!empty($sunets)) {
        $status['member_count'] = count($sunets);
        $status['message'] = 'okay';
      }
      else {
        $status['message'] = 'You may not use an empty workgroup.';
        $this->emailErrorToAdmin($status);
      }
      return ['members' => $sunets, 'status' => $status];
    }
    catch (\Exception $e) {

      $errmsg = 'Error getting workgroup ' . $wg . '. ' . $e->getMessage();
      $this->logger->error($errmsg);
      $status['message'] = $errmsg;
      $this->emailErrorToAdmin($status);
      return ['members' => [], 'status' => $status];
    }
  }

}
