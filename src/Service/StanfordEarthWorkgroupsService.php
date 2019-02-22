<?php

namespace Drupal\stanford_earth_workgroups\Service;

use Drupal\Core\Config\ConfigFactory;
use GuzzleHttp\ClientInterface;

/**
 * Service to return members of a Stanford Workgroup.
 */
class StanfordEarthWorkgroupsService {

  protected $config;
  protected $httpClient;

  /**
   * Constructs a StanfordEarthWorkgroupsService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   *
   * @param \Drupal\Core\Config\ConfigFactory
   *   Drupal config data.
   */
  public function __construct(ClientInterface $http_client,
                              ConfigFactory $config = null) {
    $this->httpClient = $http_client;
    $this->config = $config->get('stanford_earth_workgroups_service.adminsettings');
  }

  public function getMembers($wg, $certin = null, $keyin = null) {
    $status = ['workgroup' => $wg, 'member_count' => 0];
    try {
      if (!empty($certin) || !empty($keyin)) {
        $key = $keyin;
        $cert = $certin;
      } else {
        $key = $this->config->get('stanford_earth_workgroups_key');
        $cert = $this->config->get('stanford_earth_workgroups_cert');
      }

      $result = $this->httpClient->request('GET',
        'https://workgroupsvc.stanford.edu/v1/workgroups/' . $wg,
        ['cert' => $cert, 'ssl_key' => $key]);
      if ($result->getStatusCode() != 200) {
        $errmsg = 'Error getting workgroup ' . $wg . '. ' . $result->getReasonPhrase();
        \Drupal::logger('type')->error($errmsg);
        $status['message'] = $errmsg;
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
              $sunetid = (string)$member->attributes()->$id_attribute;
              $name = (string)$member->attributes()->$name_attribute;
              $sunets[$sunetid] = $name;
            }
          }
          $workgroups = $xpath0->xpath('workgroup');
          if (is_array($workgroups)) {
            foreach ($workgroups as $next_wg) {
              $nested = (string)$next_wg->attributes()->$id_attribute;
              $sunets = array_merge($sunets, $this->getMembers($nested, $cert, $key));
            }
          }
        }
      }
      if (!empty($sunets)) {
        $status['member_count'] = count($sunets);
        $status['message'] = 'okay';
      } else {
        $status['message'] = 'You may not use an empty workgroup.';
      }
      return ['members' => $sunets, 'status' => $status];
    }
    catch (\Exception $e) {
      $errmsg = 'Error getting workgroup ' . $wg . '. ' . $e->getMessage();
      \Drupal::logger('type')->error($errmsg);
      $status['message'] = $errmsg;
      return ['members' => [], 'status' => $status];
    }
  }

}
