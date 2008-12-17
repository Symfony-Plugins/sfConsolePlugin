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
     * Stores the last exception catched
     * @var Exception
     */

    protected $exception;

    /**
     * Error codes to name map
     * @var array
     */

    protected $errorNames = array(
      E_USER_ERROR        => 'User Error',
      E_USER_WARNING      => 'User Warning',
      E_USER_NOTICE       => 'User Notice',
      E_PARSE             => 'Parse Error',
      E_NOTICE            => 'Notice',
      E_WARNING           => 'Warning',
      E_STRICT            => 'Strict Standards',
      E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
      E_DEPRECATED        => 'Deprecated',
      E_USER_DEPRECATED   => 'User Deprecated',
    );

  /**
   * The file to store the history in
   * @param string $historyFile
   */

  protected $historyFile;


  /**
   * @see sfTask::configure()
   */

  protected function configure()
  {

    if (!function_exists('readline'))
    {
      throw new sfException('You need the readline extension enabled to use the console');
    }

    // since we are in a task, current dir is the project root
    $this->historyFile = 'cache/console.history';

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
    set_error_handler(array($this, 'errorHandler'));

    $this->initHistory();

    while ($line = trim((readline($this->getPrompt()))))
    {
      $this->addHistoryLine($line);
      $this->nbLines++;

      if (!$this->doCommands($line))
      {
        continue;
      }

      $line = $this->fixLine($line);

      ob_start();
      try
      {
        eval($line);
      }
      catch (Exception $e)
      {
        $this->lastException = $e;
        $this->logSection('console', 'exception catched ('.get_class($e).'): '.$e->getMessage());
        $this->logSection('console', 'use the "backtrace" command to get a the full backtrace');
      }
      $buffer = ob_get_clean();

      if (!empty($buffer))
      {
        echo trim($buffer);
        echo PHP_EOL;
      }
    }
  }

  /**
   * Reads the history file (if any) and adds all lines
   * to current history
   */

  protected function initHistory()
  {
    if (!file_exists($this->historyFile))
    {
      touch($this->historyFile);
    }

    $fp = fopen($this->historyFile, 'r');
    while ($line = trim(fgets($fp)))
    {
      if (!empty($line))
      {
        readline_add_history($line);
      }
    }
    fclose($fp);
  }

  /**
   * Adds a line to the console history
   *
   * @param string $line
   * @return boolean
   */

  protected function addHistoryLine($line)
  {
    $fp = fopen($this->historyFile, 'a+');
    fputs($fp, $line.PHP_EOL);
    fclose($fp);
    return readline_add_history($line);
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

    // add classes
    $completion = array_merge($completion, get_declared_classes());

    // add constants
    $completion = array_merge($completion, array_keys(get_defined_constants()));

    return $completion;
  }

  /**
   * Error handler
   * 
   * @param integer $errno
   * @param string $errstr
   */

  public function errorHandler($errno, $errstr)
  {
    echo (isset($this->errorNames[$errno]) ? $this->errorNames[$errno] : 'Unknown Error').': '.$errstr;

    return true;
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

    if ($line == 'backtrace')
    {
      if (is_null($this->lastException))
      {
        $this->logSection('console', 'No exception to display');
      }
      else
      {
        echo $this->lastException->getTraceAsString().PHP_EOL;
      }
      return false;
    }

    return true;
  }
}
