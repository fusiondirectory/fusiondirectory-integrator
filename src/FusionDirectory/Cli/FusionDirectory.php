<?php
/*
  This code is part of ldap-config-manager (https://www.fusiondirectory.org/)

  Copyright (C) 2020-2024  FusionDirectory

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

use Exception;
use SimpleXMLElement;
use SodiumException;

/**
 * Base class for interacting with FusionDirectory specifics
 */
class FusionDirectory extends Application
{
  /**
   * Current values of variables
   * @var array<string,string>
   */
  protected $vars;
  protected $configFilePath;
  /**
   * @var string path to the FusionDirectory secrets file containing the key to decrypt passwords
   */
  protected string $secretsFilePath;

  public function __construct ()
  {
    parent::__construct();

    // Variables to be set during script calling.
    $this->vars = [
      'fd_home'          => '/usr/share/fusiondirectory',
      'fd_config_dir'    => '/etc/fusiondirectory',
      'config_file'      => 'fusiondirectory.conf',
      'secrets_file'     => 'fusiondirectory.secrets',
      'fd_cache'         => '/var/cache/fusiondirectory',
      'fd_smarty_path'   => '/usr/share/php/smarty3/Smarty.class.php',
      'fd_spool_dir'     => '/var/spool/fusiondirectory',
      'locale_dir'       => 'locale',
      'class_cache'      => 'class.cache',
      'locale_cache_dir' => 'locale',
      'tmp_dir'          => 'tmp',
      'fai_log_dir'      => 'fai',
      'template_dir'     => 'template'
    ];
  }

  /**
   * @return array[]
   */
  public function getVarOptions (): array
  {
    return [
      'list-vars' => [
        'help'    => 'List possible vars to give --set-var',
        'command' => 'cmdListVars',
      ],
      'set-var:'  => [
        'help'    => 'Set the variable value',
        'command' => 'cmdSetVar',
      ],
    ];
  }


  /**
   * Read variables.inc file from FusionDirectory and update variables accordingly
   */
  protected function readFusionDirectoryVariablesFile (): void
  {
    if ($this->verbose()) {
      printf('Reading vars from %s' . "\n", $this->vars['fd_home'] . '/include/variables.inc');
    }
    require_once($this->vars['fd_home'] . '/include/variables.inc');

    $fd_cache  = $this->removeFinalSlash(CACHE_DIR);
    $varsToSet = [
      'fd_config_dir'    => $this->removeFinalSlash(CONFIG_DIR),
      'config_file'      => $this->removeFinalSlash(CONFIG_FILE),
      'fd_smarty_path'   => $this->removeFinalSlash(SMARTY),
      'fd_spool_dir'     => $this->removeFinalSlash(SPOOL_DIR),
      'fd_cache'         => $fd_cache,
      'locale_cache_dir' => $this->removeFinalSlash(str_replace($fd_cache . '/', '', LOCALE_DIR)),
      'tmp_dir'          => $this->removeFinalSlash(str_replace($fd_cache . '/', '', TEMP_DIR)),
      'template_dir'     => $this->removeFinalSlash(str_replace($fd_cache . '/', '', CONFIG_TEMPLATE_DIR)),
      'fai_log_dir'      => $this->removeFinalSlash(str_replace($fd_cache . '/', '', FAI_LOG_DIR)),
      'class_cache'      => $this->removeFinalSlash(CLASS_CACHE),
    ];
    foreach ($varsToSet as $var => $value) {
      if (isset($this->vars[$var])) {
        $this->vars[$var] = $value;
      }
    }
  }

  /**
   * Output variables and their current values
   */
  protected function cmdListVars (): void
  {
    foreach ($this->vars as $key => $value) {
      printf("%-20s [%s]\n", $key, $value);
    }
  }

  /**
   * @throws Exception
   */
  protected function cmdSetVar (array $vars): void
  {
    $varsToSet = [];
    foreach ($vars as $var) {
      if (preg_match('/^([^=]+)=(.+)$/', $var, $m)) {
        if (isset($this->vars[strtolower($m[1])])) {
          $varsToSet[strtolower($m[1])] = $m[2];
        } else {
          throw new Exception('Var "' . $m[1] . '" does not exists. Use --list-vars to get the list of vars.');
        }
      } else {
        throw new Exception('Incorrect syntax for --set-var: "' . $var . '". Use var=value');
      }
    }

    if (isset($varsToSet['fd_home'])) {
      if ($this->verbose()) {
        printf('Setting var %s to "%s"' . "\n", 'fd_home', $this->removeFinalSlash($varsToSet['fd_home']));
      }
      $this->vars['fd_home'] = $this->removeFinalSlash($varsToSet['fd_home']);
    }
    $this->readFusionDirectoryVariablesFile();
    unset($varsToSet['fd_home']);
    foreach ($varsToSet as $var => $value) {
      if ($this->verbose()) {
        printf('Setting var %s to "%s"' . "\n", $var, $value);
      }
      $this->vars[$var] = $value;
    }
  }

  /**
   * Load locations information from FusionDirectory configuration file
   * @return array<array{tls: bool, uri: string, base: string, bind_dn: string, bind_pwd: string}> locations
   * @throws SodiumException
   * @throws Exception
   */
  protected function loadFusionDirectoryConfigurationFile (): array
  {
    if ($this->verbose()) {
      printf('Loading configuration file from %s' . "\n", $this->configFilePath);
    }

    $secret = NULL;
    if (file_exists($this->secretsFilePath)) {
      if ($this->verbose()) {
        printf('Using secrets file %s' . "\n", $this->secretsFilePath);
      }
      $lines = file($this->secretsFilePath, FILE_SKIP_EMPTY_LINES);
      if ($lines === FALSE) {
        throw new Exception('Could not open "' . $this->secretsFilePath . '"');
      }
      foreach ($lines as $line) {
        if (preg_match('/RequestHeader set FDKEY ([^ \n]+)\n/', $line, $m)) {
          $secret = sodium_base642bin($m[1], SODIUM_BASE64_VARIANT_ORIGINAL);
          break;
        }
      }
    }

    // Note: this function is case sensitive with xml tags and attributes, FD is not
    $xml       = new SimpleXMLElement($this->configFilePath, 0, TRUE);
    $locations = [];
    foreach ($xml->main->location as $loc) {
      $ref      = $loc->referral[0];
      $location = [
        'tls'      => (isset($loc['ldapTLS']) && (strcasecmp((string)$loc['ldapTLS'], 'TRUE') === 0)),
        'uri'      => (string)$ref['URI'],
        'base'     => (string)($ref['base'] ?? $loc['base'] ?? ''),
        'bind_dn'  => (string)$ref['adminDn'],
        'bind_pwd' => (string)$ref['adminPassword'],
      ];
      if ($location['base'] === '') {
        if (preg_match('|^(.*)/([^/]+)$|', $location['uri'], $m)) {
          /* Format from FD<1.3 */
          $location['uri']  = $m[1];
          $location['base'] = $m[2];
        } else {
          throw new Exception('"' . $location['uri'] . '" does not contain any base!');
        }
      }
      if ($secret !== NULL) {
        if (!class_exists('SecretBox')) {
          /* Temporary hack waiting for core namespace/autoload refactor */
          require_once($this->vars['fd_home'] . '/include/SecretBox.inc');
        }
        $location['bind_pwd'] = SecretBox::decrypt($location['bind_pwd'], $secret);
      }
      $locations[(string)$loc['name']] = $location;
      if ($this->verbose()) {
        printf('Found location %s (%s)' . "\n", (string)$loc['name'], $location['uri']);
      }
    }
    if (count($locations) < 1) {
      throw new Exception('No location found in configuration file');
    }

    return $locations;
  }

}