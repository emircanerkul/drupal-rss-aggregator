<?php

namespace Drupal\rss_aggregator\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rss_aggregator\Entity\RssFeed;

/**
 * Add or edit an RSS feed source.
 */
class RssFeedForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\rss_aggregator\Entity\RssFeed $feed */
    $feed = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $feed->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $feed->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#disabled' => !$feed->isNew(),
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Feed URL'),
      '#description' => $this->t('Full URL of the RSS or Atom document, e.g. https://example.com/rss.xml.'),
      '#default_value' => $feed->getUrl(),
      '#required' => TRUE,
    ];

    $form['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh interval (minutes)'),
      '#description' => $this->t('Minimum time between two imports of this feed. Cron enqueues the feed when this interval has elapsed.'),
      '#min' => 1,
      '#max' => 10080,
      '#default_value' => $feed->getRefreshInterval(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $feed->getDescription(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Only enabled feeds are scheduled on cron.'),
      '#default_value' => $feed->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\rss_aggregator\Entity\RssFeed $feed */
    $feed = $this->entity;
    $result = $feed->save();

    $this->messenger()->addStatus($this->t('RSS feed %label has been saved.', ['%label' => $feed->label()]));
    $form_state->setRedirectUrl($feed->toUrl('collection'));
    return $result;
  }

  /**
   * Determines whether the machine name already exists.
   */
  public function exists($value): bool {
    return (bool) $this->entityTypeManager
      ->getStorage('rss_feed')
      ->load($value);
  }

}
