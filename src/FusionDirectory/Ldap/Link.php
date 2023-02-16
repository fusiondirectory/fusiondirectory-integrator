<?php
/*
  This code is part of FusionDirectory\Ldap (https://www.fusiondirectory.org/)

  Copyright (C) 2020  FusionDirectory

  SPDX-License-Identifier: GPL-2.0-or-later

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

declare(strict_types = 1);

namespace FusionDirectory\Ldap;

/**
 * This class handles an LDAP connection towards an LDAP server.
 */
class Link
{
  /**
  * @var resource
  */
  protected $cid;
  /**
  * @var string
  */
  protected $hostname;
  /**
  * @var bool
  */
  protected $tls;

  /**
   * Link constructor. Does not open the connection.
   *
   * @param string $hostname  LDAP URI to use for ldap_connect
   * @param bool   $tls       wether to use TLS
   */
  public function __construct (string $hostname, bool $tls = FALSE)
  {
    $this->hostname = $hostname;
    $this->tls      = $tls;
  }

  /**
   * Actually open the connection and bind to the LDAP server.
   * Uses SASL for the bind.
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function saslBind (string $binddn = '', string $password = '', string $mech = '', string $realm = '', string $authc_id = '', string $authz_id = '', string $props = ''): void
  {
    $cid = ldap_connect($this->hostname);

    if ($cid === FALSE) {
      throw new Exception('Invalid URI: '.$this->hostname);
    }

    ldap_set_option($cid, LDAP_OPT_PROTOCOL_VERSION, 3);

    if ($this->tls) {
      ldap_start_tls($cid);
    }

    if (ldap_sasl_bind($cid, $binddn, $password, $mech, $realm, $authc_id, $authz_id, $props) !== TRUE) {
      throw new Exception('Failed to bind to '.$this->hostname);
    }

    $this->cid = $cid;
  }

  /**
   * Actually open the connection and bind to the LDAP server.
   *
   * @param array<array{oid:string,iscritical:bool,value:mixed}> $controls
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function bind (string $dn = '', string $password = '', array $controls = []): void
  {
    $cid = ldap_connect($this->hostname);

    if ($cid === FALSE) {
      throw new Exception('Invalid URI: '.$this->hostname);
    }

    ldap_set_option($cid, LDAP_OPT_PROTOCOL_VERSION, 3);

    if ($this->tls) {
      ldap_start_tls($cid);
    }

    $res = ldap_bind_ext($cid, $dn, $password, $controls);
    if ($res === FALSE) {
      throw new Exception('Failed to bind to '.$this->hostname);
    }

    $result = new Result($cid, $res);
    $result->assert();

    $this->cid = $cid;
  }

  /**
   * Perform an LDAP search
   *
   * @param string        $basedn   Base to search in
   * @param string        $filter   Filter to use for the search
   * @param array<string> $attrs    Which attributes to fetch
   * @param string        $scope    One of 'base','one' or 'subtree'
   * @param array<array>  $controls Controls to pass along with the search
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function search (string $basedn, string $filter, array $attrs = [], string $scope = 'subtree', array $controls = NULL): Result
  {
    $functions = ['base' => 'ldap_read','one' => 'ldap_list','subtree' => 'ldap_search'];

    if (isset($controls)) {
      $result = @$functions[strtolower($scope)]($this->cid, $basedn, $filter, $attrs, 0, 0, 0, LDAP_DEREF_NEVER, $controls);
    } else {
      $result = @$functions[strtolower($scope)]($this->cid, $basedn, $filter, $attrs);
    }
    if ($result === FALSE) {
      throw new Exception('Search failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Add values to attributes on an existing entry. Adds attributes if needed.
   *
   * @param string        $dn       The LDAP node to modify
   * @param array<string,string|array<string>> $attrs The attributes values to add
   * @param array<array>  $controls Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function mod_add (string $dn, array $attrs, array $controls = []): Result
  {
    $result = ldap_mod_add_ext($this->cid, $dn, $attrs, $controls);
    if ($result === FALSE) {
      throw new Exception('Mod add failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Replaces values of attributes on an existing entry. Adds attributes if needed.
   *
   * @param string        $dn       The LDAP node to modify
   * @param array<string,string|array<string>> $attrs The attributes values to replace
   * @param array<array>  $controls Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function mod_replace (string $dn, array $attrs, array $controls = []): Result
  {
    $result = ldap_mod_replace_ext($this->cid, $dn, $attrs, $controls);
    if ($result === FALSE) {
      throw new Exception('Mod replace failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Delete values of attributes on an existing entry. Deletes attributes if needed.
   *
   * @param string        $dn       The LDAP node to modify
   * @param array<string,string|array<string>> $attrs The attributes values to delete
   * @param array<array>  $controls Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function mod_del (string $dn, array $attrs, array $controls = []): Result
  {
    $result = ldap_mod_del_ext($this->cid, $dn, $attrs, $controls);
    if ($result === FALSE) {
      throw new Exception('Mod del failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Delete an LDAP entry
   *
   * @param string        $dn       The LDAP node to delete
   * @param array<array>  $controls Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function delete (string $dn, array $controls = []): Result
  {
    $result = ldap_delete_ext($this->cid, $dn, $controls);
    if ($result === FALSE) {
      throw new Exception('Delete failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Add an entry to the LDAP
   *
   * @param string        $dn       The LDAP node to create
   * @param array<string,string|array<string>> $attrs The attributes values to add
   * @param array<array>  $controls Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function add (string $dn, array $attrs, array $controls = []): Result
  {
    $result = ldap_add_ext($this->cid, $dn, $attrs, $controls);
    if ($result === FALSE) {
      throw new Exception('Add failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Renames an existing entry.
   *
   * @param string        $dn             The LDAP node to rename
   * @param string        $new_rdn        The new RDN
   * @param string        $new_parent     The new parent/superior entry
   * @param bool          $delete_old_rdn If true the old RDN value(s) is removed, else the old RDN value(s) is retained as non-distinguished values of the entry.
   * @param array<array>  $controls       Controls to send along with the request
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function rename (string $dn, string $new_rdn, string $new_parent, bool $delete_old_rdn, array $controls = []): Result
  {
    $result = ldap_rename_ext($this->cid, $dn, $new_rdn, $new_parent, $delete_old_rdn, $controls);
    if ($result === FALSE) {
      throw new Exception('Rename failed: '.ldap_error($this->cid), ldap_errno($this->cid));
    }

    return new Result($this->cid, $result);
  }

  /**
   * Get exaustive list of object classes declared on the LDAP server
   *
   * @return array<string,array<string,string|true|array<string>>> List of object classes with their properties, indexed by name
   *
   * @throws \FusionDirectory\Ldap\Exception When an object class has no NAME or several
   */
  public function getObjectClasses (): array
  {
    // Get base to look for schema
    $dse            = $this->getDSE(['subschemaSubentry']);
    $objectclasses  = [];
    foreach ($dse['subschemaSubentry'] as $subschemaSubentry) {
      /* Get list of objectclasses and fill array */
      $res  = $this->search($subschemaSubentry, 'objectClass=*', ['objectClasses'], 'base');
      foreach ($res as $attrs) {
        foreach ($attrs['objectClasses'] as $val) {
          $infos = Schema::parseDefinition($val);
          if (isset($infos['NAME']) && is_string($infos['NAME'])) {
            $objectclasses[$infos['NAME']] = $infos;
          } else {
            throw new Exception('Invalid NAME in class definition: '.print_r($infos['NAME'] ?? NULL, TRUE));
          }
        }
      }
    }

    return $objectclasses;
  }

  public function getDSE ($attributes = ['*','+']): array
  {
    $list = $this->search('', '(objectClass=*)', $attributes, 'base');
    $list->assert();
    $list->rewind();
    return $list->current();
  }
}
