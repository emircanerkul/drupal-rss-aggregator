<?php

namespace Drupal\rss_aggregator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Module settings form.
 */
class RssAggregatorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rss_aggregator_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rss_aggregator.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('rss_aggregator.settings');

    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('HTTP request timeout (seconds)'),
      '#description' => $this->t('Maximum time to wait for a feed document.'),
      '#min' => 1,
      '#max' => 300,
      '#default_value' => $config->get('request_timeout') ?: 30,
      '#required' => TRUE,
    ];

    $form['item_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Item lifetime (days)'),
      '#description' => $this->t('Items older than this are deleted on cron. Enter 0 to keep items forever.'),
      '#min' => 0,
      '#max' => 3650,
      '#default_value' => $config->get('item_lifetime') ?: 0,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('rss_aggregator.settings')
      ->set('request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('item_lifetime', (int) $form_state->getValue('item_lifetime'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
