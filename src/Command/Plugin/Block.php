<?php

namespace DrupalCodeGenerator\Command\Plugin;

/**
 * Implements plugin:block command.
 */
final class Block extends PluginGenerator {

  protected $name = 'plugin:block';
  protected $description = 'Generates block plugin';
  protected $alias = 'block';
  protected $pluginClassSuffix = 'Block';
  protected $pluginLabelQuestion = 'Block admin label';

  /**
   * {@inheritdoc}
   */
  protected function generate() :void {
    $vars = &$this->collectDefault();

    $vars['category'] = $this->ask('Block category', 'Custom');
    $vars['configurable'] = $this->confirm('Make the block configurable?', FALSE);

    $this->collectServices(FALSE);

    $vars['access'] = $this->confirm('Create access callback?', FALSE);

    $this->addFile('src/Plugin/Block/{class}.php', 'plugin/block');

    if ($vars['configurable']) {
      $this->addSchemaFile()->template('plugin/block-schema');
    }
  }

}
