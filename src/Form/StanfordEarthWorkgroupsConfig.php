<?php

namespace Drupal\stanford_earth_workgroups\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Contains Drupal\stanford_earth_workgroups\Form\StanfordEarthWorkgroupsConfig.
 */
class StanfordEarthWorkgroupsConfig extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * StanfordEarthSamlForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactory interface.
     *
     *   @param \GuzzleHttp\ClientInterface $http_client
     *   The Guzzle HTTP client.
     */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'stanford_earth_workgroups_service.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_workgroups_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stanford_earth_workgroups_service.adminsettings');

    $form['stanford_earth_workgroups_cert'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Certificate Path'),
      '#description' => $this->t('Location on server of the MAIS WG API cert.'),
      '#required' => true,
      '#default_value' => $config->get('stanford_earth_workgroups_cert'),
    ];

    $form['stanford_earth_workgroups_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Key Path'),
      '#description' => $this->t('Location on server of the MAIS WG API key.'),
      '#required' => true,
      '#default_value' => $config->get('stanford_earth_workgroups_key'),
    ];

    $form['stanford_earth_workgroups_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Workgroup'),
      '#description' => $this->t('A Stanford Workgroup to test your cert and key.'),
      '#required' => true,
      '#default_value' => $config->get('stanford_earth_workgroups_test'),
    ];

    return parent::buildForm($form, $form_state);
  }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
        $wg = $form_state->getValue('stanford_earth_workgroups_test');
        $wg_service = \Drupal::service('stanford_earth_workgroups_service.workgroup');
        $wg_data = $wg_service->getMembers($wg,
            $form_state->getValue('stanford_earth_workgroups_cert'),
            $form_state->getValue('stanford_earth_workgroups_key'));
        if (empty($wg_data['status']['member_count'])) {
            $form_state->setErrorByName('stanford_earth_workgroups_test',
                $wg_data['status']['message']);
        }
    }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable('stanford_earth_workgroups_service.adminsettings')
      ->set('stanford_earth_workgroups_test', $form_state->getValue('stanford_earth_workgroups_test'))
      ->set('stanford_earth_workgroups_cert', $form_state->getValue('stanford_earth_workgroups_cert'))
      ->set('stanford_earth_workgroups_key', $form_state->getValue('stanford_earth_workgroups_key'))
      ->save();
  }

}
