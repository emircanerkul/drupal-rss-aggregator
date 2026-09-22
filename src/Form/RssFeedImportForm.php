<?php

namespace Drupal\rss_aggregator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rss_aggregator\FeedFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manually enqueues a single feed for immediate import.
 */
class RssFeedImportForm extends FormBase {

  public function __construct(
    protected FeedFetcher $feedFetcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rss_aggregator.feed_fetcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rss_aggregator_feed_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\rss_aggregator\Entity\RssFeed|null $feed */
    $feed = \Drupal::routeMatch()->getParameter('rss_feed');
    if (!$feed) {
      $form['missing'] = ['#markup' => $this->t('Feed not found.')];
      return $form;
    }

    $last = $feed->getLastRefreshed();
    $form['info'] = [
      '#markup' => '<p>' . $this->t('Feed: <strong>@label</strong><br>URL: @url<br>Last refreshed: @last', [
        '@label' => $feed->label(),
        '@url' => $feed->getUrl(),
        '@last' => $last ? \Drupal::service('date.formatter')->format($last, 'short') : $this->t('Never'),
      ]) . '</p>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import now'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $feed = $this->getRequest()->attributes->get('rss_feed');
    if ($feed && $this->feedFetcher->enqueueFeed($feed->id())) {
      $this->messenger()->addStatus($this->t('Feed %name queued for import. Run cron (or <code>drush queue:run rss_aggregator_feeds</code> and <code>drush queue:run rss_aggregator_items</code>) to process it.', [
        '%name' => $feed->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Could not queue feed for import.'));
    }
  }

}
