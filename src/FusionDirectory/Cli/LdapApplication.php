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

use \FusionDirectory\Ldap;

/**
 * Base class for cli applications that needs an LDAP connection from fusiondirectory configuration file
 */
class LdapApplication extends Application
{
  /**
   * @var Ldap\Link|null
   */
  protected $ldap = NULL;

  /**
   * @var string Ldap tree base
   */
  protected $base;

  /**
   * @var string path to the FusionDirectory configuration file
   */
  protected $configFilePath;

  /**
   * @var string path to the FusionDirectory secrets file containing the key to decrypt passwords
   */
  protected $secretsFilePath;

  /**
   * May not be needed once SecretBox situation clears up
   * @var array<string,string>
   */
  protected $vars;

  public function __construct ()
  {
    parent::__construct();

    $this->options  = [
      'ldapuri:'  => [
        'help'        => 'URI to connect to, defaults to configuration file value',
      ],
      'binddn:'  => [
        'help'        => 'DN to bind with, defaults to configuration file value',
      ],
      'bindpwd:'  => [
        'help'        => 'Password to bind with, defaults to configuration file value',
      ],
      'saslmech:'  => [
        'help'        => 'SASL mech, activates SASL if specified',
      ],
      'saslrealm:'  => [
        'help'        => 'SASL realm',
      ],
      'saslauthcid:'  => [
        'help'        => 'SASL authcid',
      ],
      'saslauthzid:'  => [
        'help'        => 'SASL authzid',
      ],
      'yes'           => [
        'help'        => 'Answer yes to all questions',
      ],
      'verbose'       => [
        'help'        => 'Verbose output',
      ],
      'help'          => [
        'help'        => 'Show this help',
      ],
    ];
  }

  /**
   * Read FusionDirectory configuration file, and open a connection to the LDAP server
   * If there already is a connection opened, do nothing
   */
  protected function readFusionDirectoryConfigurationFileAndConnectToLdap (): void
  {
    if ($this->ldap !== NULL) {
      return;
    }

    $locations  = $this->loadFusionDirectoryConfigurationFile();
    $location   = (string)key($locations);
    if (count($locations) > 1) {
      /* Give the choice between locations to user */
      $question = 'There are several locations in your config file, which one should be used: ('.implode(',', array_keys($locations)).')';
      do {
        $answer = $this->askUserInput($question, $location);
      } while (!isset($locations[$answer]));
      $location = $answer;
    }
    $config = $locations[$location];

    if ($this->verbose()) {
      printf('Connecting to LDAP at %s'."\n", $this->getopt['ldapuri'][0] ?? $config['uri']);
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

  /**
   * Load locations information from FusionDirectory configuration file
   * @return array<array{tls: bool, uri: string, base: string, bind_dn: string, bind_pwd: string}> locations
   */
  protected function loadFusionDirectoryConfigurationFile (): array
  {
    if ($this->verbose()) {
      printf('Loading configuration file from %s'."\n", $this->configFilePath);
    }

    $secret = NULL;
    if (file_exists($this->secretsFilePath)) {
      if ($this->verbose()) {
        printf('Using secrets file %s'."\n", $this->secretsFilePath);
      }
      $lines = file($this->secretsFilePath, FILE_SKIP_EMPTY_LINES);
      if ($lines === FALSE) {
        throw new \Exception('Could not open "'.$this->secretsFilePath.'"');
      }
      foreach ($lines as $line) {
        if (preg_match('/RequestHeader set FDKEY ([^ \n]+)\n/', $line, $m)) {
          $secret = \sodium_base642bin($m[1], SODIUM_BASE64_VARIANT_ORIGINAL);
          break;
        }
      }
    }

    // FIXME this function is case sensitive with xml tags and attributes, FD is not
    $xml = new \SimpleXMLElement($this->configFilePath, 0, TRUE);
    $locations = [];
    foreach ($xml->main->location as $loc) {
      $ref = $loc->referral[0];
      $location = [
        'tls'       => (isset($loc['ldapTLS']) && (strcasecmp((string)$loc['ldapTLS'], 'TRUE') === 0)),
        'uri'       => (string)$ref['URI'],
        'base'      => (string)($ref['base'] ?? $loc['base'] ?? '' ),
        'bind_dn'   => (string)$ref['adminDn'],
        'bind_pwd'  => (string)$ref['adminPassword'],
      ];
      if ($location['base'] === '') {
        if (preg_match('|^(.*)/([^/]+)$|', $location['uri'], $m)) {
          /* Format from FD<1.3 */
          $location['uri']   = $m[1];
          $location['base']  = $m[2];
        } else {
          throw new \Exception('"'.$location['uri'].'" does not contain any base!');
        }
      }
      if ($secret !== NULL) {
        if (!class_exists('SecretBox')) {
          /* Temporary hack waiting for core namespace/autoload refactor */
          require_once($this->vars['fd_home'].'/include/SecretBox.inc');
        }
        $location['bind_pwd'] = SecretBox::decrypt($location['bind_pwd'], $secret);
      }
      $locations[(string)$loc['name']] = $location;
      if ($this->verbose()) {
        printf('Found location %s (%s)'."\n", (string)$loc['name'], $location['uri']);
      }
    }
    if (count($locations) < 1) {
      throw new \Exception('No location found in configuration file');
    }

    return $locations;
  }
}
