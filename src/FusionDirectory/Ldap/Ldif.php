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
 * This class parses LDIF data
 */
class Ldif
{
  /**
   * @var ?int
   */
  protected $version;
  /**
   * Whether this LDIF represents changes or records
   * @var bool
   */
  protected $changes;
  /**
   * @var array<int, LdifRecord>
   */
  protected $entries;

  /**
   * LDIF constructor
   * @param array<int, LdifRecord> $entries
   */
  public function __construct (bool $changes, array $entries = [], ?int $version = NULL)
  {
    $this->version = $version;
    $this->changes = $changes;
    $this->entries = $entries;
  }

  public function isChangesSet (): bool
  {
    return $this->changes;
  }

  /**
   * @return array<int, LdifRecord>
   */
  public function getEntries (): array
  {
    return $this->entries;
  }

  /**
   * @param array<int, LdifRecord> $entries
   * @param LdifRecord $entry
   * @throws \FusionDirectory\Ldap\Exception
   */
  static protected function parseLine (
    int $lineNumber,
    string $fileLine,
    ?string &$line,
    LdifRecord &$entry,
    array &$entries,
    int &$entryStart,
    ?int &$version,
    bool &$changes
  ): void
  {
    if (preg_match('/^ /', $fileLine) === 1) {
      if ($line === NULL) {
        throw new Exception(sprintf(_('Error line %s, first line of an entry cannot start with a space'), $lineNumber));
      }
      /* Append to current line */
      $line .= substr($fileLine, 1);
    } else {
      if ($line !== NULL) {
        if (preg_match('/^#/', $line) === 1) {
          /* Ignore comment */
        } elseif ($line === '-') {
          /* Changeset split */
          $entry->addChangeset();
        } else {
          /* Line has ended */
          if (strpos($line, ':') === FALSE) {
            throw new Exception(sprintf(_('Error line %s, expected ":"'), $lineNumber));
          }
          list ($key, $value) = explode(':', $line, 2);
          $value = ltrim($value);
          if (preg_match('/^:/', $value) === 1) {
            $value = base64_decode(trim(substr($value, 1)), TRUE);
            if ($value === FALSE) {
              throw new Exception(sprintf(_('Error line %s, invalid base64 data for attribute "%s"'), $lineNumber, $key));
            }
          }
          if (preg_match('/^</', $value) === 1) {
            throw new Exception(sprintf(_('Error line %s, references to an external file are not supported'), $lineNumber));
          }
          if ($value === '') {
            throw new Exception(sprintf(_('Error line %s, attribute "%s" has no value'), $lineNumber, $key));
          }
          if (($key === 'version') && ($entry->isEmpty()) && (count($entries) === 0) && ($version === NULL)) {
            /* Store version number */
            $version = intval($value);
          } elseif ($key == 'dn') {
            if (!$entry->isEmpty()) {
              throw new Exception(sprintf(_('Error line %s, an entry bloc can only have one dn'), $lineNumber));
            }
            $entry->dn    = $value;
            $entryStart   = $lineNumber;
          } elseif ($entry->isEmpty()) {
            throw new Exception(sprintf(_('Error line %s, an entry bloc should start with the dn'), $lineNumber));
          } elseif ($key === 'changetype') {
            $changes            = TRUE;
            $entry->changetype  = $value;
          } elseif ($key === 'control') {
            $entry->addControl($value);
          } elseif ($changes) {
            if (!isset($entry->changetype)) {
              throw new Exception(sprintf(_('Error line %s, all entry blocs must set changetype, or none of them should'), $lineNumber));
            }
            $entry->appendChangesetData($key, $value);
          } else {
            $entry->addAttributeValue($key, $value);
          }
        }
      }
      /* Start new line */
      $line = ltrim($fileLine);
      if ($line == '') {
        if (!$entry->isEmpty()) {
          /* Entry is finished */
          $entries[$entryStart] = $entry;
        }
        /* Start a new entry */
        $entry      = new LdifRecord();
        $entryStart = -1;
        $line       = NULL;
      }
    }
  }

  /**
   * @throws \FusionDirectory\Ldap\Exception
   */
  static public function parseString (string $data): Ldif
  {
    /* First we split the string into lines */
    $fileLines = preg_split("/\n/", $data);
    if ($fileLines === FALSE) {
      throw new Exception('Preg split failed');
    }
    if (end($fileLines) != '') {
      $fileLines[] = '';
    }

    /* Parsing lines */
    $line       = NULL;
    $entry      = new LdifRecord();
    $entries    = [];
    $entryStart = -1;
    $version    = NULL;
    $changes    = FALSE;
    foreach ($fileLines as $lineNumber => $fileLine) {
      static::parseLine($lineNumber, $fileLine, $line, $entry, $entries, $entryStart, $version, $changes);
    }

    return new Ldif($changes, $entries, $version);
  }

  /**
   * @throws \FusionDirectory\Ldap\Exception
   * @param resource $fh
   */
  static public function parseFromFileHandle ($fh): Ldif
  {
    $line       = NULL;
    $entry      = new LdifRecord;
    $entries    = [];
    $entryStart = -1;
    $version    = NULL;
    $changes    = FALSE;
    $lineNumber = 1;
    while (($fileLine = fgets($fh)) !== FALSE) {
      static::parseLine($lineNumber, rtrim($fileLine, "\n\r"), $line, $entry, $entries, $entryStart, $version, $changes);
      $lineNumber++;
    }
    if (!$entry->isEmpty()) {
      static::parseLine($lineNumber, '', $line, $entry, $entries, $entryStart, $version, $changes);
    }

    return new Ldif($changes, $entries, $version);
  }
}
