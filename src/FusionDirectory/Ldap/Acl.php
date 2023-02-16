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
 * This class parses LDAP ACLs in parts
 */
class Acl
{
  /**
   * @var int
   */
  protected $index;
  /**
   * @var array<string,string>|string
   */
  protected $to;
  /**
   * @var array<int,array>
   */
  protected $by = [];

  /*
    <access directive> ::= to <what> [by <who> [<access>] [<control>]]+
    <what> ::= * | [dn[.<basic-style>]=<regex> | dn.<scope-style>=<DN>] [filter=<ldapfilter>] [attrs=<attrlist>]
    <basic-style> ::= regex | exact
    <scope-style> ::= base | one | subtree | children
    <attrlist> ::= <attr> [val[.<basic-style>]=<regex>] | <attr> , <attrlist>
    <attr> ::= <attrname> | entry | children
    <who> ::= * | [anonymous | users | self
            | dn[.<basic-style>]=<regex> | dn.<scope-style>=<DN>]
        [dnattr=<attrname>]
        [group[/<objectclass>[/<attrname>][.<basic-style>]]=<regex>]
        [peername[.<basic-style>]=<regex>]
        [sockname[.<basic-style>]=<regex>]
        [domain[.<basic-style>]=<regex>]
        [sockurl[.<basic-style>]=<regex>]
        [set=<setspec>]
        [aci=<attrname>]
    <access> ::= [self]{<level>|<priv>}
    <level> ::= none | disclose | auth | compare | search | read | write | manage
    <priv> ::= {=|+|-}{m|w|r|s|c|x|d|0}+
    <control> ::= [stop | continue | break]
  */

  /**
   * Acl constructor
   *
   * @param string $acl The ACL string from LDAP
   * @throws \FusionDirectory\Ldap\Exception
   */
  public function __construct (string $acl)
  {
    if (preg_match('/^{(\d+)}/', $acl, $m) === 1) {
      $this->index = (int)($m[1]);
      $acl = substr($acl, strlen($m[0]));
    }
    $tokens = preg_split('/\s/', $acl);
    if (($tokens === FALSE) || ($tokens[0] != 'to')) {
      throw new Exception('Invalid ACL format: missing "to" keyword');
    }
    $this->parseTo($tokens, 1);
  }

  /**
   * @param array<string> $tokens
   * @throws \FusionDirectory\Ldap\Exception
   */
  protected function parseTo (array $tokens, int $i): void
  {
    /*
      <what> ::= * | [dn[.<basic-style>]=<regex> | dn.<scope-style>=<DN>] [filter=<ldapfilter>] [attrs=<attrlist>]
      <basic-style> ::= regex | exact
      <scope-style> ::= base | one | subtree | children
    */
    if ($tokens[$i] == '*') {
      $this->to = '*';
      $i++;
    } else {
      $this->to = [];
      do {
        [$key, $value] = explode('=', $tokens[$i], 2);
        switch ($key) {
          case 'filter':
          case 'attrs':
          case 'dn':
          case 'dn.regex':
          case 'dn.exact':
          case 'dn.base':
          case 'dn.one':
          case 'dn.subtree':
          case 'dn.children':
            $this->to[$key] = $value;
            break;
          default:
            throw new Exception('Could not parse ACL: invalid "to" clause: "'.$tokens[$i].'"');
        }
        $i++;
      } while (($i < count($tokens)) && ($tokens[$i] != 'by'));
    }
    if (($i < count($tokens)) && ($tokens[$i] == 'by')) {
      $this->parseBy($tokens, $i + 1);
    } else {
      throw new Exception('Could not parse ACL: Missing "by" clause');
    }
  }

  /**
   * @param array<string> $tokens
   * @throws \FusionDirectory\Ldap\Exception
   */
  protected function parseBy (array $tokens, int $i): void
  {
    /* [by <who> [<access>] [<control>]]+ */
    $by = [];
    do {
      $by[] = $tokens[$i];
      $i++;
    } while (($i < count($tokens)) && ($tokens[$i] != 'by'));
    $this->by[] = $by;
    if ($i < count($tokens)) {
      if ($tokens[$i] == 'by') {
        $this->parseBy($tokens, $i + 1);
      } else {
        throw new Exception('Could not parse ACL: Invalid clause: "'.$tokens[$i].'"');
      }
    }
  }

  /**
   * Dump the ACL content to STDOUT
   *
   * @param string $indent String to add as a prefix to each line (usually spaces)
   */
  public function dump (string $indent): void
  {
    echo $indent.$this->index.': to ';
    if (is_array($this->to)) {
      foreach ($this->to as $key => $attr) {
        echo $key.'='.$attr.' ';
      }
    } else {
      echo $this->to;
    }
    echo "\n";
    foreach ($this->by as $by) {
      echo $indent.'   by '.implode(' ', $by)."\n";
    }
  }
}
