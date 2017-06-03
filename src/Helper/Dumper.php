<?php

namespace DrupalCodeGenerator\Helper;

use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper as YamlDumper;

/**
 * Output dumper form generators.
 */
class Dumper extends Helper {

  /**
   * The file system utility.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  public $filesystem;

  /**
   * The yaml dumper.
   *
   * @var \Symfony\Component\Yaml\Dumper
   */
  protected $yamlDumper;

  /**
   * The base directory.
   *
   * @var string
   */
  protected $baseDirectory;

  /**
   * Input instance.
   *
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * Output instance.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * Override flag.
   *
   * @var bool
   */
  protected $override;

  /**
   * Constructs a generator command.
   *
   * @param \Symfony\Component\Filesystem\Filesystem $filesystem
   *   The file system utility.
   * @param \Symfony\Component\Yaml\Dumper $yaml_dumper
   *   The yaml dumper.
   * @param bool $override
   *   (optional) Indicates weather or not existing files can be overridden.
   */
  public function __construct(Filesystem $filesystem, YamlDumper $yaml_dumper, $override = NULL) {
    $this->filesystem = $filesystem;
    $this->yamlDumper = $yaml_dumper;
    $this->yamlDumper->setIndentation(2);
    $this->override = $override;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'dcg_dumper';
  }

  /**
   * Dumps the generated code to file system.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output instance.
   *
   * @return array
   *   List of created or updated files.
   */
  public function dump(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $formatter_style = new OutputFormatterStyle('black', 'cyan', []);
    $this->output->getFormatter()->setStyle('title', $formatter_style);

    $interactive = $input->isInteractive();

    // NULL means we should ask user for confirmation.
    if ($this->override !== NULL) {
      $input->setInteractive(FALSE);
    }

    /** @var \DrupalCodeGenerator\Command\GeneratorInterface $command */
    $command = $this->getHelperSet()->getCommand();

    $directory = $command->getDirectory();
    $extension_root = Utils::getExtensionRoot($directory);
    $this->baseDirectory = ($extension_root ?: $directory) . '/';

    $assets = $command->getAssets();

    $dumped_files = array_merge(
      $this->dumpFiles($assets['files']),
      $this->dumpServices($assets['services'])
    );

    $input->setInteractive($interactive);
    return $dumped_files;
  }

  /**
   * Dumps files.
   *
   * @param array $files
   *   Files to dump.
   *
   * @return array
   *   List of created or updated files.
   */
  protected function dumpFiles(array $files) {
    $dumped_files = [];

    foreach ($files as $file_name => $file_info) {

      // Support short syntax `$this->files['File.php'] => 'Rendered content';`.
      $content = is_array($file_info) ? $file_info['content'] : $file_info;

      $header_height = isset($file_info['header_height']) ? $file_info['header_height'] : 0;

      $is_directory = $content === NULL;

      // Default mode for all parent directories is 0777. It can be modified by
      // changing umask.
      $mode = isset($file_info['mode']) ? $file_info['mode'] : ($is_directory ? 0755 : 0644);

      $merge_type = isset($file_info['merge_type']) ? $file_info['merge_type'] : 'replace';

      $file_path = $this->baseDirectory . $file_name;
      if ($this->filesystem->exists($file_path) && !$is_directory) {

        if ($merge_type == 'replace') {
          $question_text = sprintf('<info>The file <comment>%s</comment> already exists. Would you like to override it?</info> [<comment>Yes</comment>]: ', $file_path);
          if (!$this->askConfirmationQuestion($question_text)) {
            continue;
          }
        }
        elseif ($merge_type == 'append') {
          if ($header_height > 0) {
            $content = Utils::removeHeader($content, $header_height);
          }
          $content = file_get_contents($file_path) . "\n" . $content;
        }
        else {
          throw new \LogicException("Unsupported merge type: $merge_type.");
        }

      }

      // Save data to file system.
      if ($is_directory) {
        $this->filesystem->mkdir([$file_path], $mode);
      }
      else {
        $this->filesystem->dumpFile($file_path, $content);
        $this->filesystem->chmod($file_path, $mode);
      }

      $dumped_files[] = $file_name;
    }

    return $dumped_files;
  }

  /**
   * Dumps services.
   *
   * @param array $services
   *   Services to dump.
   *
   * @return array
   *   List of created or updated files.
   *
   * @todo Provide a method for dumping any kind of yml (not just services).
   */
  protected function dumpServices(array $services) {

    $dumped_files = [];

    $extension_name = basename($this->baseDirectory);

    $service_groups = [];

    // Sort services by type.
    foreach ($services as $service_name => $service) {
      if (isset($service['tags'])) {
        foreach ($service['tags'] as $tag) {

          switch ($tag['name']) {
            case 'drush.command':
              $service_groups['drush'][$service_name] = $service;
              break;

            case 'drupal.command':
              $service_groups['console'][$service_name] = $service;
              break;

            default:
              $service_groups[$extension_name][$service_name] = $service;
          }

        }
      }
    }

    foreach ($service_groups as $group_name => $group) {
      $inline = 3;

      $file = $this->baseDirectory . '/' . $group_name . '.services.yml';

      if ($this->filesystem->exists($file)) {
        $action = 'update';
        $intend = 2;
      }
      else {
        $group = ['services' => $group];
        $action = 'create';
        $intend = 0;
        $inline++;
      }

      $question_text = sprintf(
        '<info>Would you like to %s <comment>%s.services.yml</comment> file?</info> [<comment>Yes</comment>]: ',
        $action,
        $group_name
      );

      if ($this->askConfirmationQuestion($question_text)) {
        $yaml = $this->yamlDumper->dump($group, $inline, $intend);
        file_put_contents($file, $yaml, FILE_APPEND);
        $dumped_files[] = $group_name . '.services.yml';
      }

    }

    return $dumped_files;
  }

  /**
   * Asks user a confirmation question.
   *
   * @param string $question_text
   *   The question to ask to the user.
   *
   * @return bool
   *   User confirmation.
   */
  protected function askConfirmationQuestion($question_text) {
    // If the input is not interactive print the question with default answer.
    if ($this->override !== NULL) {
      $this->output->writeln($question_text . ($this->override ? 'Yes' : 'No'));
    }
    $question = new ConfirmationQuestion($question_text, $this->override !== FALSE);
    /** @var \Symfony\Component\Console\Helper\QuestionHelper $question_helper */
    $question_helper = $this->getHelperSet()->get('question');
    return $question_helper->ask($this->input, $this->output, $question);
  }

}
