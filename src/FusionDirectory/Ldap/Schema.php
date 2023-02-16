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
 * This class parses LDAP schemas
 */
class Schema
{
  /**
   * @var string
   */
  protected $cn;
  /**
   * @var array<string>
   */
  protected $objectIdentifiers;
  /**
   * @var array<string>
   */
  protected $ldapSyntaxes;
  /**
   * @var array<string>
   */
  protected $attributeTypes;
  /**
   * @var array<string>
   */
  protected $objectClasses;
  /**
   * @var array<string>
   */
  protected $ditContentRules;

  public const ATTRIBUTES = ['olcObjectIdentifier','olcLdapSyntaxes','olcAttributeTypes','olcObjectClasses','olcDitContentRules'];

  /**
   * Schema constructor
   *
   * @param array<string> $objectIdentifiers
   * @param array<string> $ldapSyntaxes
   * @param array<string> $attributeTypes
   * @param array<string> $objectClasses
   * @param array<string> $ditContentRules
   */
  public function __construct (string $cn = '', array $objectIdentifiers = [], array $ldapSyntaxes = [], array $attributeTypes = [], array $objectClasses = [], array $ditContentRules = [])
  {
    $this->cn                 = $cn;
    $this->objectIdentifiers  = $objectIdentifiers;
    $this->ldapSyntaxes       = $ldapSyntaxes;
    $this->attributeTypes     = $attributeTypes;
    $this->objectClasses      = $objectClasses;
    $this->ditContentRules    = $ditContentRules;
  }

  /**
   * Returns an array to pass to Ldap\Link::mod_replace to replace a schema
   *
   * @param array<string, array<string>> $attrs
   * @return array<string, array<string>>
   */
  public function toModReplaceArray (array $attrs): array
  {
    $ourAttrs = [
      'olcObjectIdentifier' => $this->objectIdentifiers,
      'olcLdapSyntaxes'     => $this->ldapSyntaxes,
      'olcAttributeTypes'   => $this->attributeTypes,
      'olcObjectClasses'    => $this->objectClasses,
      'olcDitContentRules'  => $this->ditContentRules,
    ];
    $result = [];
    foreach ($ourAttrs as $attr => $values) {
      if ((count($attrs[$attr] ?? []) > 0) || (count($values) > 0)) {
        $result[$attr] = $values;
      }
    }
    return $result;
  }

  /**
   * Returns an array to pass to Ldap\Link::add to insert a schema
   *
   * @return array<string, array<string>>
   */
  public function toAddArray (): array
  {
    $entry = [
      'olcObjectIdentifier' => $this->objectIdentifiers,
      'olcLdapSyntaxes'     => $this->ldapSyntaxes,
      'olcAttributeTypes'   => $this->attributeTypes,
      'olcObjectClasses'    => $this->objectClasses,
      'olcDitContentRules'  => $this->ditContentRules,
    ];
    $entry = array_filter(
      $entry,
      function ($item): bool {
        return (count($item) > 0);
      }
    );
    $entry['cn']          = [$this->cn];
    $entry['objectClass'] = ['olcSchemaConfig'];
    return $entry;
  }

  /**
   * Builds dn based on cn and base
   */
  public function computeDn (string $base = 'cn=schema,cn=config'): string
  {
    return 'cn='.$this->cn.','.$base;
  }

  /**
   * @throws \FusionDirectory\Ldap\Exception
   */
  public static function parseSchemaFile (string $cn, string $path): Schema
  {
    $errors = [];
    set_error_handler(
      function (int $errno, string $errstr, string $errfile, int $errline, array $errcontext) use (&$errors): bool
      {
        $errors[] = $errstr;
        return TRUE;
      }
    );
    $fh = @fopen($path, 'r');
    restore_error_handler();
    if ($fh === FALSE) {
      throw new Exception(implode("\n", $errors));
    }
    if (preg_match('/\.ldif$/i', $path) === 1) {
      $ldifData = Ldif::parseFromFileHandle($fh);
      if (!feof($fh)) {
        throw new Exception('Reading '.$path.' failed');
      }
      fclose($fh);
      if ($ldifData->isChangesSet()) {
        throw new Exception($path.' LDIF file contains changes and not records');
      }
      $ldifEntries  = $ldifData->getEntries();
      $entry        = reset($ldifEntries);
      if ($entry === FALSE) {
        throw new Exception($path.' LDIF file does not contain any entry');
      } elseif (count($ldifEntries) > 1) {
        throw new Exception($path.' LDIF file contains several entries');
      }
      return new Schema(
        $entry->attrs['cn'][0] ?? $cn,
        $entry->attrs['olcObjectIdentifier'] ?? [],
        $entry->attrs['olcLdapSyntaxes'] ?? [],
        $entry->attrs['olcAttributeTypes'] ?? [],
        $entry->attrs['olcObjectClasses'] ?? [],
        $entry->attrs['olcDitContentRules'] ?? []
      );
    } else {
      $data = static::parseSchemaContent($fh);
      if (!feof($fh)) {
        throw new Exception('Reading '.$path.' failed');
      }
      fclose($fh);
      foreach ($data as $key => $defs) {
        if (!in_array($key, ['objectidentifier','ldapsyntax','attributetype','objectclass','ditcontentrule'], TRUE)) {
          throw new Exception('Unknown item type '.$key.' found in schema file');
        }
      }
      return new Schema(
        $cn,
        $data['objectidentifier'] ?? [],
        $data['ldapsyntax'] ?? [],
        $data['attributetype'] ?? [],
        $data['objectclass'] ?? [],
        $data['ditcontentrule'] ?? []
      );
    }
  }

  /**
   * @return array<string, array<string>>
   * @param resource $fh
   */
  protected static function parseSchemaContent ($fh): array
  {
    $currentItem      = '';
    $currentItemName  = '';
    $data             = [];
    while (($line = fgets($fh)) !== FALSE) {
      if (preg_match('/^#/', $line) === 1) {
        continue;
      }
      if (preg_match('/^(\w+)(.*)$/', $line, $m) === 1) {
        if (($currentItem != '') && ($currentItemName != '')) {
          $data[$currentItemName][] = $currentItem;
        }
        $currentItemName  = strtolower($m[1]);
        $currentItem      = trim($m[2]);
      } else {
        $currentItem .= ' '.trim($line);
      }
    }
    if (($currentItem != '') && ($currentItemName != '')) {
      $data[$currentItemName][] = $currentItem;
    }

    return $data;
  }

  /**
   * Parse an objectclass or attribute definition and returns its properties as an array
   *
   * @return array<string,string|true|array<string>> Array of properties
   */
  public static function parseDefinition (string $definition): array
  {
    $name     = 'OID';
    $value    = '';
    $pattern  = explode(' ', $definition);
    $infos    = [];

    foreach ($pattern as $chunk) {
      switch ($chunk) {
        case '(':
          $value = '';
          break;

        case ')':
          $chunk = '';

        case 'NAME':
        case 'DESC':
        case 'SUP':
        case 'STRUCTURAL':
        case 'ABSTRACT':
        case 'AUXILIARY':
        case 'MUST':
        case 'OBSOLETE':
        case 'MAY':
          if ($name != '') {
            $v = static::value2container($value);
            if (in_array($name, ['MUST','MAY'], TRUE)) {
              if ($v === TRUE) {
                $v = [];
              } else if (!is_array($v)) {
                $v = [$v];
              }
            }
            $infos[$name] = $v;
          }
          $name   = $chunk;
          $value  = '';
          break;

        default:  $value .= $chunk.' ';
      }
    }

    return $infos;
  }

  /**
   * @return string|true|array<string>
   */
  protected static function value2container (string $value)
  {
    /* Set empty values to "TRUE" only */
    if (preg_match('/^\s*$/', $value) === 1) {
      return TRUE;
    }

    /* Remove ' and " if needed */
    $value = (preg_replace('/^[\'"]/', '', $value) ?? '');
    $value = (preg_replace('/[\'"] *$/', '', $value) ?? '');
    $value = rtrim($value);

    /* Convert to array if $ is inside... */
    if (preg_match('/\$/', $value) === 1) {
      $container = preg_split('/\s*\$\s*/', $value);
      if ($container === FALSE) {
        $container = $value;
      }
    } else {
      $container = $value;
    }

    return $container;
  }
}
