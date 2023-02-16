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
 * Result of an Ldap operation
 *
 * @implements \Iterator<string,array<string,array<string>>>
 */
class Result implements \Iterator,\Countable
{
  /**
   * Error code
   *
   * @var int
   */
  public $errcode;
  /**
   * Matched DN
   *
   * @var string
   */
  public $matcheddn;
  /**
   * Error message
   *
   * @var string
   */
  public $errmsg;
  /**
   * Referrals list
   *
   * @var array<int>
   */
  public $referrals;
  /**
   * Controls list
   *
   * @var array<array>
   */
  public $serverctrls;
  /**
   * @var resource
   */
  protected $link;
  /**
   * @var resource
   */
  protected $result;
  /**
   * @var resource|false
   */
  protected $cur;
  /**
   * @var int
   */
  protected $errno = 0;

  /**
   * @param resource $link
   * @param resource $result
   *
   * @throws \FusionDirectory\Ldap\Exception When ldap_parse_result fails
   */
  public function __construct ($link, $result)
  {
    $this->link   = $link;
    $this->result = $result;
    $success = ldap_parse_result($link, $result, $this->errcode, $this->matcheddn, $this->errmsg, $this->referrals, $this->serverctrls);
    if (!$success) {
      throw new Exception('Failed to parse result: '.ldap_error($this->link));
    }
  }

  /**
   * Assert that the result represents a successful LDAP operation, or throw an exception with the error message
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function assert (): void
  {
    if ($this->errcode != 0) {
      if ($this->errmsg == '') {
        $errmsg = ldap_err2str($this->errcode);
      } else {
        $errmsg = $this->errmsg;
      }
      $errmsg .= ' ('.$this->errcode.')';
      if ($this->matcheddn !== NULL) {
        if (strlen($this->matcheddn) > 0) {
          $errmsg .= '(matched dn: '.$this->matcheddn.')';
        }
      }
      throw new Exception($errmsg);
    }
  }

  /**
   * Count entries for a search result
   */
  public function count (): int
  {
    return ldap_count_entries($this->link, $this->result);
  }

  /**
   * Get current entry attributes as an associative array
   *
   * @return array<string, array<string>>
   */
  public function current ()
  {
    assert(is_resource($this->cur));
    $att = [];
    for ($a = ldap_first_attribute($this->link, $this->cur); $a !== FALSE; $a = ldap_next_attribute($this->link, $this->cur)) {
      $values = @ldap_get_values($this->link, $this->cur, $a);
      if ($values === FALSE) {
        $this->errno = ldap_errno($this->link);
      } else {
        unset($values['count']);
        $att[$a] = $values;
      }
    }
    return $att;
  }

  /**
   * Get the DN of current entry
   *
   * @return string
   */
  public function key ()
  {
    assert(is_resource($this->cur));
    return trim(ldap_get_dn($this->link, $this->cur));
  }

  /**
   * Go to next entry
   */
  public function next (): void
  {
    if ($this->valid()) {
      $this->cur = ldap_next_entry($this->link, $this->cur);
      if ($this->cur === FALSE) {
        $this->errno = ldap_errno($this->link);
      }
    }
  }

  /**
   * Rewind to first entry
   */
  public function rewind (): void
  {
    $this->cur    = ldap_first_entry($this->link, $this->result);
    $this->errno  = 0;
  }

  /**
   * Whether there is a current entry
   */
  public function valid (): bool
  {
    return (($this->errno === 0) && is_resource($this->cur));
  }

  /**
   * Throws if there was an error while iterating
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function assertIterationWentFine (): void
  {
    if ($this->errno !== 0) {
      throw new Exception(ldap_err2str($this->errno), $this->errno);
    }
  }
}
