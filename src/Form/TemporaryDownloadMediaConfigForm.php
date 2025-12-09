<?php

namespace Drupal\temporary_downloadable_media\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Mirador Settings Form.
 */
class TemporaryAccessConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_lite.temporary_downloadable_media.form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('temporary_downloadable_media.settings');
    
    $form['temporary_downloadable_media_fieldset'] = [
      '#type' => 'fieldset',
    ];
   
    // Call the helper function to get the options list.
    $user_options = get_user_select_options($this->entityTypeManager);
    
    // Add a placeholder/empty option at the beginning.
    $user_options = ['' => $this->t('- Select a User -')] + $user_options;

    // 1. Create the select list element.
    $form['temporary_downloadable_media_fieldset']['user_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select User to generate a valid token'),
      '#options' => $user_options,
      '#required' => TRUE,
      '#description' => $this->t('A list of all active users on the site.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('temporary_downloadable_media.settings');
    $config->set('temporary_downloadable_media', $form_state->getValue('temporary_downloadable_media'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'temporary_downloadable_media.settings',
    ];
  }

}