<?php

namespace DrupalCodeGenerator\Command\Drupal_8\Plugin;

use Symfony\Component\Console\Question\Question;

/**
 * Implements d8:plugin:entity-reference-selection command.
 */
class EntityReferenceSelection extends PluginGenerator {

  protected $name = 'd8:plugin:entity-reference-selection';
  protected $description = 'Generates entity reference selection plugin';
  protected $alias = 'entity-reference-selection';
  protected $pluginLabelDefault = 'Advanced {entity_type} selection';
  protected $pluginClassDefault = '{entity_type|camelize}Selection';

  /**
   * {@inheritdoc}
   */
  protected function generate() :void {
    $vars = &$this->collectDefault();

    $vars['configurable'] = $this->confirm('Provide additional plugin configuration?', FALSE);

    $vars['base_class_full'] = self::baseClasses()[$vars['entity_type']] ??
      'Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection';

    $vars['base_class'] = explode('EntityReferenceSelection\\', $vars['base_class_full'])[1];

    $this->addFile('src/Plugin/EntityReferenceSelection/{class}.php')
      ->template('d8/plugin/entity-reference-selection');
    $this->addSchemaFile()->template('d8/plugin/entity-reference-selection-schema');
  }

  /**
   * {@inheritdoc}
   */
  protected function &collectDefault(): array {
    $this->vars['name'] = $this->askNameQuestion();
    $this->vars['machine_name'] = $this->askMachineNameQuestion();

    $entity_type_question = new Question('Entity type that can be referenced by this plugin', 'node');
    $entity_type_question->setValidator([__CLASS__, 'validateRequiredMachineName']);
    $entity_type_question->setAutocompleterValues(array_keys(self::baseClasses()));
    $this->vars['entity_type'] = $this->io->askQuestion($entity_type_question);

    $this->vars['plugin_label'] = $this->askPluginLabelQuestion();
    $this->vars['plugin_id'] = $this->askPluginIdQuestion();
    $this->vars['class'] = $this->askPluginClassQuestion();
    return $this->vars;
  }

  /**
   * Base classes for the plugin.
   */
  private static function baseClasses() :array {
    return [
      'comment' => 'Drupal\comment\Plugin\EntityReferenceSelection\CommentSelection',
      'file' => 'Drupal\file\Plugin\EntityReferenceSelection\FileSelection',
      'node' => 'Drupal\node\Plugin\EntityReferenceSelection\NodeSelection',
      'taxonomy_term' => 'Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection',
      'user' => 'Drupal\user\Plugin\EntityReferenceSelection\UserSelection',
    ];
  }

}
