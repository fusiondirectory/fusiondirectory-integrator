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
 * This class is used by Ldif to store record data
 */
class LdifRecord
{
  /**
   * @var ?string
   */
  public $dn          = NULL;
  /**
   * @var ?string
   */
  public $changetype  = NULL;
  /**
   * @var array<string>
   */
  public $controls    = [];
  /**
   * @var array<string,array<string>>
   */
  public $attrs       = [];
  /**
   * @var array<array<array<string,array<string>>>>
   */
  public $changesets  = [];

  public function isEmpty (): bool
  {
    return (
      ($this->dn === NULL) &&
      ($this->changetype === NULL) &&
      (count($this->controls) === 0) &&
      (count($this->attrs) === 0) &&
      (count($this->changesets) === 0)
    );
  }

  public function addChangeset (): void
  {
    $this->changesets[] = [];
  }

  public function appendChangesetData (string $key, string $value): void
  {
    if (count($this->changesets) === 0) {
      $this->addChangeset();
    }
    $changeset = array_key_last($this->changesets);
    if (!isset($this->changesets[$changeset][$key])) {
      $this->changesets[$changeset][$key] = [];
    }
    $this->changesets[$changeset][$key][] = $value;
  }

  public function addAttributeValue (string $key, string $value): void
  {
    if (!isset($this->attrs[$key])) {
      $this->attrs[$key] = [];
    }
    $this->attrs[$key][] = $value;
  }

  public function addControl (string $value): void
  {
    $this->controls[] = $value;
  }
}
