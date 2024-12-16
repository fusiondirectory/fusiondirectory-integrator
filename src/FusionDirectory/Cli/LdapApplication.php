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

use FusionDirectory\Ldap;
use SodiumException;

/**
 * Base class for cli applications that needs an LDAP connection from fusiondirectory configuration file
 */
class LdapApplication extends FusionDirectory
{
  /**
   * @var Ldap\Link|null
   */
  protected ?Ldap\Link $ldap = NULL;

  /**
   * @var string Ldap tree base
   */
  protected string $base;

  //  /**
  //   * @var string path to the FusionDirectory configuration file
  //   */
  //  protected string $configFilePath;

  //  /**
  //   * @var string path to the FusionDirectory secrets file containing the key to decrypt passwords
  //   */
  //  protected string $secretsFilePath;

  /**
   * May not be needed once SecretBox situation clears up
   * @var array<string,string>
   */
  protected $vars;

  public function __construct ()
  {
    parent::__construct();

    $this->options = [
      'ldapuri:'     => [
        'help' => 'URI to connect to, defaults to configuration file value',
      ],
      'binddn:'      => [
        'help' => 'DN to bind with, defaults to configuration file value',
      ],
      'bindpwd:'     => [
        'help' => 'Password to bind with, defaults to configuration file value',
      ],
      'saslmech:'    => [
        'help' => 'SASL mech, activates SASL if specified',
      ],
      'saslrealm:'   => [
        'help' => 'SASL realm',
      ],
      'saslauthcid:' => [
        'help' => 'SASL authcid',
      ],
      'saslauthzid:' => [
        'help' => 'SASL authzid',
      ],
      'yes'          => [
        'help' => 'Answer yes to all questions',
      ],
      'verbose'      => [
        'help' => 'Verbose output',
      ],
      'help'         => [
        'help' => 'Show this help',
      ],
    ];
  }

  /**
   * Read FusionDirectory configuration file, and open a connection to the LDAP server
   * If there already is a connection opened, do nothing
   * @throws Ldap\Exception
   * @throws SodiumException
   */
  protected function readFusionDirectoryConfigurationFileAndConnectToLdap (): void
  {
    if ($this->ldap !== NULL) {
      return;
    }

    $locations = $this->loadFusionDirectoryConfigurationFile();
    $location  = (string)key($locations);
    if (count($locations) > 1) {
      /* Give the choice between locations to user */
      $question = 'There are several locations in your config file, which one should be used: (' . implode(',', array_keys($locations)) . ')';
      do {
        $answer = $this->askUserInput($question, $location);
      } while (!isset($locations[$answer]));
      $location = $answer;
    }
    $config = $locations[$location];

    if ($this->verbose()) {
      printf('Connecting to LDAP at %s' . "\n", $this->getopt['ldapuri'][0] ?? $config['uri']);
    }
    $this->ldap = new Ldap\Link($this->getopt['ldapuri'][0] ?? $config['uri']);
    if (($this->getopt['saslmech'][0] ?? '') === '') {
      $this->ldap->bind(($this->getopt['binddn'][0] ?? $config['bind_dn']), ($this->getopt['bindpwd'][0] ?? $config['bind_pwd']));
    } else {
      $this->ldap->saslBind(
        ($this->getopt['binddn'][0] ?? $config['bind_dn']),
        ($this->getopt['bindpwd'][0] ?? $config['bind_pwd']),
        ($this->getopt['saslmech'][0] ?? ''),
        ($this->getopt['saslrealm'][0] ?? ''),
        ($this->getopt['saslauthcid'][0] ?? ''),
        ($this->getopt['saslauthzid'][0] ?? '')
      );
    }

    $this->base = $config['base'];
  }
}
