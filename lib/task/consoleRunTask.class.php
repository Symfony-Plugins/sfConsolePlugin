<?php

/**
 * This task runs a php interactive shell with symfony capabilities
 *
 * @package sfConsolePlugin
 * @subpackage task
 * @author Geoffrey Bachelet <geoffrey.bachelet@gmail.com>
 */

class consoleRunTask extends sfBaseTask
{

    /**
     * Number of lines already processed by the console
     * @var integer
     */

    protected $nbLines = 0;

    /**
     * The default prompt
     * @var string
     */

    protected $prompt = '(%nb_lines%)> ';

  /**
   * @see sfTask::configure()
   */

  protected function configure()
  {

    if (!function_exists('readline'))
    {
      throw new sfException('You need the readline extension enabled to use the console');
    }

    $this->namespace        = 'console';
    $this->name             = 'run';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [console:run|INFO] task does things.
Call it with:

  [php symfony console:run|INFO]
EOF;
  }

  /**
   * @see sfTask::execute()
   */

  protected function execute($arguments = array(), $options = array())
  {
    $configuration = new ProjectConfiguration();
    $database      = new sfDatabaseManager($configuration);

    $this->logSection('console', 'Welcome to the symfony console. Type exit to exit (no shit !)');
    readline_completion_function(array($this, 'completion'));
    while ($line = trim((readline($this->getPrompt()))))
    {
      readline_add_history($line);
      $this->nbLines++;

      if (!$this->doCommands($line))
      {
        continue;
      }

      $line = $this->fixLine($line);

      ob_start();
      eval($line);
      $buffer = ob_get_clean();

      if (!empty($buffer))
      {
        echo trim($buffer);
        echo PHP_EOL;
      }
    }
  }

  /**
   * Returns the prompt
   *
   * Possible replacements are:
   *  - %nb_lines% : the number of lines already processed by the console
   *
   * @param string $mode
   * @return string
   */

  protected function getPrompt($mode = 'default')
  {
    $replacements = array(
      '%nb_lines%' => sprintf('%03d', $this->nbLines),
    );
    $prompt = str_replace(array_keys($replacements), array_values($replacements), $this->prompt);
    return $prompt;
  }

  /**
   * Fixes the line before execution
   *
   * @param string $line
   * @return string
   */

  protected function fixLine($line)
  {
    // if the line consists of one single variable, wrap it inside a var_dump
    if (preg_match('/^\$\w+$/', $line))
    {
      $line = 'var_dump('.$line.');';
    }

    // add missing ending ; if necessary
    $line = preg_replace('/[^;]$/', '\\0;', $line);

    return $line;
  }

  /**
   * Returns an array of possible matches for completion
   *
   * @param string $line
   * @return array
   */

  protected function completion($line)
  {
    $completion = array();

    // add functions to the completion list
    foreach(get_defined_functions() as $functions)
    {
      $completion = array_merge($completion, array_map(create_function('$v', 'return $v.\'(\';'), $functions));
    }

    // add vars
    $completion = array_merge($completion, array_map(create_function('$v', 'return \'$\'.$v;'), array_keys(get_defined_vars())));

    // add classes
    $completion = array_merge($completion, get_declared_classes());

    // add constants
    $completion = array_merge($completion, array_keys(get_defined_constants()));

    return $completion;
  }

  /**
   * Detects if the command is a console command
   * and run the appropriate routines if so
   *
   * @param string $line
   * @return boolean false to stop the command workflow, true otherwise
   */

  protected function doCommands($line)
  {
    if ($line == 'exit')
    {
      exit;
    }

    if ($line == 'help')
    {
      $this->logSection('console', 'Sorry, help is not implemented yet.');
      return false;
    }

    return true;
  }
}
