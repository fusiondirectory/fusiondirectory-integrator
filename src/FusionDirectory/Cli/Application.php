<?php
/*
  This code is part of ldap-config-manager (https://www.fusiondirectory.org/)

  Copyright (C) 2020-2021  FusionDirectory

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace FusionDirectory\Cli;

/**
 * Base class for cli applications, with helpers to parse arguments and options
 */
class Application
{
  /**
   * @var array<string,array>
   * Options this application supports. Should be filled in constructor's child.
   */
  protected $options = [];
  /**
   * @var array<string,array>
   * Arguments this application supports. Should be filled in constructor's child.
   */
  protected array $args = [];
  /**
   * @var array<string,mixed>
   * Result of options parsing.
   */
  protected array $getopt = [];

  public function __construct ()
  {
  }

  /**
   * Show the usage information and exits
   * @param array<string> $argv
   */
  protected function usage (array $argv): void
  {
    echo 'Usage: ' . $argv[0] . ' --' . str_replace(':', ' VALUE', implode(' --', array_keys($this->options))) . ' ' . strtoupper(implode(' ', array_keys($this->args))) . "\n\n";

    foreach ($this->options as $opt => $infos) {
      printf("\t--%-25s\t%s\n", $opt . (isset($infos['short']) ? ', -' . $infos['short'] : ''), $infos['help']);
    }

    foreach ($this->args as $arg => $infos) {
      printf("\t%-25s:\t%s\n", strtoupper($arg), $infos['help']);
    }

    exit(1);
  }

  /**
   * @param array $argv
   * @param int $optind
   * @return void
   */
  protected function parseArgs (array $argv, int $optind): void
  {
    // optind comes from setopt method, counting the matches in existing options and arguments passed. If not equals, print usage.
    if ((count($argv) - $optind) != count($this->args)) {
      $this->usage($argv);
    }

    $argv = array_slice($argv, $optind);

    foreach ($this->args as &$infos) {
      if ($infos['handler'] == '…') {
        /* All the last args */
        $infos['value'] = $argv;
      } elseif (isset($infos['handler'])) {
        $infos['value'] = $infos['handler'](array_shift($argv));
      } else {
        $infos['value'] = array_shift($argv);
      }
    }
    unset($infos);
  }

  /**
   * @return string
   * Note : This method is used to format the available options in children applications. Create short formats.
   * This class is responsible to output the helpers info and thus receive options from children.
   */
  protected function generateShortOptions (): string
  {
    return implode('', array_map(
      function (string $key, array $infos): string {
        if (isset($infos['short'])) {
          if (substr($key, -1) === ':') {
            return $infos['short'] . ':';
          } else {
            return $infos['short'];
          }
        } else {
          return '';
        }
      },
      array_keys($this->options),
      array_values($this->options)
    ));
  }

  /**
   * @param $optind
   * @param $argv
   * @param $shortOptions
   * @return void
   * Note : Simply output usage if required after verifying existing potential invalid arguments
   * Receive option index (returned by getopt method) and the arguments passed to the script.
   */
  protected function verifyArguments ($optind, $argv, $shortOptions): void
  {
    for ($i = 0; $i < $optind; $i++) {

      if (($argv[$i][0] === '-') && ($argv[$i] !== '--')) {
        if (preg_match('/^--(.+)$/', $argv[$i], $m)) {
          if (!isset($this->options[$m[1]]) && !isset($this->options[$m[1] . ':'])) {

            echo 'Unrecognized option ' . $argv[$i] . "\n";
            $this->usage($argv);
          }
        } elseif (preg_match('/^-(.+)$/', $argv[$i], $m)) {
          $shorts = str_split($m[1]);

          foreach ($shorts as $short) {
            if (strpos($shortOptions, $short) === FALSE) {
              echo 'Unrecognized option -' . $short . "\n";

              $this->usage($argv);
            }
          }
        } else {
          echo 'Failed to parse option ' . $argv[$i] . "\n";

          $this->usage($argv);
        }
      }
    }
  }

  /**
   * @param $key
   * @param $option
   * @param $getopt
   * @return void
   * Logic is that every options that are matched by an arguments will have an incremental int increased from zero.
   * This allows to have a final array of arguments (validated) on which we can work on to execute related functions.
   */
  private function handleShortOptions ($key, $option, &$getopt): void
  {
    if (isset($option['short']) && isset($getopt[$option['short']])) {
      if (is_array($getopt[$option['short']])) {
        $getopt[$key] += count($getopt[$option['short']]);

      } else {
        $getopt[$key]++;
      }

      unset($getopt[$option['short']]);
    }
  }

  /**
   * @param $key
   * @param $option
   * @param $getopt
   * @return void
   * Note : This method, like the handleShortOptions - incremented the integer. Arguments requiring data.
   */
  private function processNonValueOption ($key, $option, &$getopt): void
  {
    if (!isset($getopt[$key])) {
      $getopt[$key] = 0;
    } elseif (is_array($getopt[$key])) {
      $getopt[$key] = count($getopt[$key]);
    } else {
      $getopt[$key] = 1;
    }

    // Simply handle the short options
    $this->handleShortOptions($key, $option, $getopt);
  }

  /**
   * @param $key
   * @param $option
   * @param $getopt
   * @return void
   * Note : Method which process arguments requiring data input. (See processNonValueOption for non data arguments).
   */
  private function processValueOption ($key, $option, &$getopt): void
  {
    $key = substr($key, 0, -1);

    // Simply handle short option with value below
    if (isset($option['short']) && isset($getopt[$option['short']])) {
      if (!isset($getopt[$key])) {

        $getopt[$key] = $getopt[$option['short']];
      } else {

        if (is_string($getopt[$key])) {
          $getopt[$key] = [$getopt[$key]];
        }

        if (is_array($getopt[$option['short']])) {
          $getopt[$key] = array_merge($getopt[$key], $getopt[$option['short']]);
        } else {

          $getopt[$key][] = $getopt[$option['short']];
        }
      }
    }
  }

  /**
   * @param $key
   * @param $option
   * @param $getopt
   * @return void
   * Note: We are putting a numerical value on arguments allowing better error handling.
   * Note 2: getopt is passed by reference. Keep this function private.
   */
  private function processOptions ($key, $option, &$getopt): void
  {
    if (substr($key, -1) !== ':') {
      // Process non-value options (No added path or file or options to the arguments set).
      $this->processNonValueOption($key, $option, $getopt);
    } else {
      // Process value options (argument provided + data).
      $this->processValueOption($key, $option, $getopt);
    }

    if (isset($getopt[$key])) {
      if (is_string($getopt[$key])) {
        $getopt[$key] = [$getopt[$key]];
      }
      if (isset($option['handler'])) {
        $getopt[$key] = $option['handler']($getopt[$key]);
      }
    }
  }

  /**
   * @param array $argv
   * @return array
   * Note : This is the main method to verify arguments passed and generated a final array which summarize valid arguments
   * passed.
   */
  protected function parseOptionsAndArgs (array $argv): array
  {

    // Parse into a short format, options received by children applications.
    $shortOptions = $this->generateShortOptions();

    // using getopt method, arguments passed are matched to existing preset long/short options.
    $getopt = getopt($shortOptions, array_keys($this->options), $optind);

    // getopt does not return wrong arguments passed, therefore below verification allows to indicate it.
    $this->verifyArguments($optind, $argv, $shortOptions);

    foreach ($this->options as $key => $option) {
      // process each options retrieved by getopt
      $this->processOptions($key, $option, $getopt);
    }

    /* Parse arguments */
    $this->parseArgs($argv, $optind);

    return $getopt;
  }

  /**
   * Call appropriate methods depending on options passed to the tool
   */
  protected function runCommands (): void
  {
    foreach ($this->getopt as $key => $value) {
      if (isset($this->options[$key]['command']) && ($value > 0)) {

        call_user_func([$this, $this->options[$key]['command']]);
      } elseif (isset($this->options[$key . ':']['command'])) {

        call_user_func([$this, $this->options[$key . ':']['command']], $value);
      }
    }
  }

  /**
   * Main function.
   * By default, only parse options and arguments, and print help if needed.
   * Extend if you want to call runCommands.
   * @param array<string> $argv
   */
  public function run (array $argv): void
  {
    $this->getopt = $this->parseOptionsAndArgs($argv);

    if ($this->getopt['help'] > 0) {
      $this->usage($argv);
    }
  }

  /**
   * Ask a question send as parameter, and return true if the answer is "yes"
   */
  protected function askYnQuestion (string $question): bool
  {
    if (($this->getopt['yes'] ?? 0) > 0) {
      return TRUE;
    }
    echo "$question [Yes/No]?\n";
    $return = NULL;

    while ($line = fgets(STDIN)) {
      /* Remove the \n at the end of $input */
      $line = trim($line);

      if (in_array(strtolower($line), ['yes', 'y'])) {
        $return = TRUE;
        break;
      } elseif (in_array(strtolower($line), ['no', 'n'])) {
        $return = FALSE;
        break;
      }
    }
    return $return;
  }

  /**
   * Ask for a user input and do some checks
   */
  protected function askUserInput (string $thingToAsk, string $defaultAnswer = '', bool $hidden = FALSE): string
  {
    if ($defaultAnswer != '') {
      $thingToAsk .= " [$defaultAnswer]";
    }
    echo $thingToAsk . ":\n";

    if ($hidden) {
      /* FIXME maybe find a better way */
      system('stty -echo');
    }

    do {
      if ($answer = fgets(STDIN)) {
        $answer = trim($answer);
      } else {
        $answer = '';
      }
    } while (($answer === '') && ($defaultAnswer === ''));

    if ($hidden) {
      system('stty echo');
    }

    if ($answer === '') {
      return $defaultAnswer;
    }
    return $answer;
  }

  /**
   * Helper to check verbose flag value
   */
  protected function verbose (): bool
  {
    if (isset($this->getopt['verbose']) && !empty($this->getopt['verbose'])) {
      return ($this->getopt['verbose'] > 0);
    }

    return FALSE;
  }

  /**
   * Helper to remove final slash from a path, if there is one
   */
  protected static function removeFinalSlash (string $path): string
  {
    return preg_replace('|/$|', '', $path);
  }
}

