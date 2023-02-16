<?php
/*
  This code is part of FusionDirectory (https://www.fusiondirectory.org/)

  Copyright (C) 2020-2021 FusionDirectory

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
 * Trait for cli tools using a variable system through their options
 */
trait VarHandling
{
  /**
   * Current values of variables
   * @var array<string,string>
   */
  protected $vars;

  /**
   * Get the options related to variables handling
   * @return array<string,array<string,string>>
   */
  protected function getVarOptions (): array
  {
    return [
      'list-vars' => [
        'help'    => 'List possible vars to give --set-var',
        'command' => 'cmdListVars',
      ],
      'set-var:' => [
        'help'    => 'Set the variable value',
        'command' => 'cmdSetVar',
      ],
    ];
  }

  abstract protected function verbose (): bool;

  /**
   * Read variables.inc file from FusionDirectory and update variables accordingly
   */
  protected function readFusionDirectoryVariablesFile (): void
  {
    if ($this->verbose()) {
      printf('Reading vars from %s'."\n", $this->vars['fd_home'].'/include/variables.inc');
    }
    require_once($this->vars['fd_home'].'/include/variables.inc');

    $fd_cache = LdapApplication::removeFinalSlash(CACHE_DIR);
    $varsToSet = [
      'fd_config_dir'    => LdapApplication::removeFinalSlash(CONFIG_DIR),
      'config_file'      => LdapApplication::removeFinalSlash(CONFIG_FILE),
      'fd_smarty_path'   => LdapApplication::removeFinalSlash(SMARTY),
      'fd_spool_dir'     => LdapApplication::removeFinalSlash(SPOOL_DIR),
      'fd_cache'         => $fd_cache,
      'locale_cache_dir' => LdapApplication::removeFinalSlash(str_replace($fd_cache.'/', '', LOCALE_DIR)),
      'tmp_dir'          => LdapApplication::removeFinalSlash(str_replace($fd_cache.'/', '', TEMP_DIR)),
      'template_dir'     => LdapApplication::removeFinalSlash(str_replace($fd_cache.'/', '', CONFIG_TEMPLATE_DIR)),
      'fai_log_dir'      => LdapApplication::removeFinalSlash(str_replace($fd_cache.'/', '', FAI_LOG_DIR)),
      'class_cache'      => LdapApplication::removeFinalSlash(CLASS_CACHE),
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
   * Set variables values
   * @param array<string> $vars
   */
  protected function cmdSetVar (array $vars): void
  {
    $varsToSet = [];
    foreach ($vars as $var) {
      if (preg_match('/^([^=]+)=(.+)$/', $var, $m)) {
        if (isset($this->vars[strtolower($m[1])])) {
          $varsToSet[strtolower($m[1])] = $m[2];
        } else {
          throw new \Exception('Var "'.$m[1].'" does not exists. Use --list-vars to get the list of vars.');
        }
      } else {
        throw new \Exception('Incorrect syntax for --set-var: "'.$var.'". Use var=value');
      }
    }

    if (isset($varsToSet['fd_home'])) {
      if ($this->verbose()) {
        printf('Setting var %s to "%s"'."\n", 'fd_home', LdapApplication::removeFinalSlash($varsToSet['fd_home']));
      }
      $this->vars['fd_home'] = LdapApplication::removeFinalSlash($varsToSet['fd_home']);
    }
    $this->readFusionDirectoryVariablesFile();
    unset($varsToSet['fd_home']);
    foreach ($varsToSet as $var => $value) {
      if ($this->verbose()) {
        printf('Setting var %s to "%s"'."\n", $var, $value);
      }
      $this->vars[$var] = $value;
    }
  }
}
