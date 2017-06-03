<?php

namespace DrupalCodeGenerator\Command\Drupal_7;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Implements d7:hook command.
 */
class Hook extends BaseGenerator {

  protected $name = 'd7:hook';
  protected $description = 'Generates a hook';

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $questions = Utils::defaultQuestions();
    $questions['hook_name'] = [
      'Hook name',
      NULL,
      function ($value) {
        if (!in_array($value, $this->supportedHooks())) {
          return 'This hook is not supported.';
        }
      },
      $this->supportedHooks(),
    ];

    $vars = $this->collectVars($input, $output, $questions);

    $install_hooks = [
      'install',
      'uninstall',
      'enable',
      'disable',
      'schema',
      'schema_alter',
      'field_schema',
      'requirements',
      'update_N',
      'update_last_removed',
    ];

    $file_type = in_array($vars['hook_name'], $install_hooks) ? 'install' : 'module';

    $header = $this->render("d7/file-docs/$file_type.twig", $vars);
    $content = $this->render('d7/hook/' . $vars['hook_name'] . '.twig', $vars);

    $this->files[$vars['machine_name'] . '.' . $file_type] = [
      'content' => $header . "\n" . $content,
      'merge_type' => 'append',
      'header_height' => 7,
    ];

  }

  /**
   * Returns list of supported hooks.
   */
  protected function supportedHooks() {
    return array_map(function ($file) {
      return pathinfo($file, PATHINFO_FILENAME);
    }, array_diff(scandir($this->templatePath . '/d7/hook'), ['.', '..']));
  }

}
