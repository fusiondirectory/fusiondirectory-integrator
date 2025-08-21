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

namespace FusionDirectory\FusionDirectory;

/**
 * FusionDirectory Configuration class
 *
 * This class provides methods to retrieve configuration attributes from the FusionDirectory LDAP directory.
 */
class Configuration
{ 
 
 /**
   * Return all attributes under "cn=config,ou=fusiondirectory,<baseDn>"
   *
   * @param Link   $link    An active Link instance
   * @param string $baseDn  The directory base DN to append after ou=fusiondirectory
   * @param string $scope   LDAP search scope: 'base' | 'one' | 'subtree'
   * @return array Each attributes
   *
   * @throws \FusionDirectory\Ldap\Exception
   */
  public static function getFusionDirectoryConfigAttributes (Link $link, string $baseDn, string $scope = 'subtree'): array
  {
    $configDn = 'cn=config,ou=fusiondirectory,' . $baseDn;

    $result = $link->search($configDn, '(objectClass=*)', ['*','+'], $scope);
    $result->assert();

    $entries = [];
    foreach ($result as $attrs) {
      $entries[] = $attrs;
    }
    return $entries;
  }
}