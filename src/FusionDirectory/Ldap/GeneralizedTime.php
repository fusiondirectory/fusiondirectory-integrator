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

use DateTime, DateTimeZone;

/**
 * Ldap\GeneralizedTime allows you to convert LDAP GeneralizedTime strings and PHP DateTime objects back and forth
 *
 * This class provides function to convert from LDAP GeneralizedTime to DateTime and the other way.
 * Please note that leap seconds will be lost as PHP has no support for it (see https://bugs.php.net/bug.php?id=70335).
 * 01:60 will become 02:00.
 * Also, this class does not support fraction of hours or fraction of minutes (fraction of seconds are supported).
*/
class GeneralizedTime
{
  /**
   * @brief Convert from LDAP GeneralizedTime formatted string to DateTime object
   * @param string $string GeneralizedTime formatted string to convert
   * @throws \FusionDirectory\Ldap\Exception
   */
  public static function fromString (string $string): DateTime
  {
    // century = 2(%x30-39) ; "00" to "99"
    // year    = 2(%x30-39) ; "00" to "99"
    $year = '(?P<year>\d{4})';
    // month   =   ( %x30 %x31-39 ) ; "01" (January) to "09"
    //           / ( %x31 %x30-32 ) ; "10" to "12"
    $month = '(?P<month>0[1-9]|1[0-2])';
    // day     =   ( %x30 %x31-39 )    ; "01" to "09"
    //           / ( %x31-32 %x30-39 ) ; "10" to "29"
    //           / ( %x33 %x30-31 )    ; "30" to "31"
    $day = '(?P<day>0[1-9]|[0-2]\d|3[01])';
    // hour    = ( %x30-31 %x30-39 ) / ( %x32 %x30-33 ) ; "00" to "23"
    $hour = '(?P<hour>[0-1]\d|2[0-3])';
    // minute  = %x30-35 %x30-39                        ; "00" to "59"
    $minute = '(?P<minute>[0-5]\d)';
    // second      = ( %x30-35 %x30-39 ) ; "00" to "59"
    // leap-second = ( %x36 %x30 )       ; "60"
    $second = '(?P<second>[0-5]\d|60)';
    // fraction        = ( DOT / COMMA ) 1*(%x30-39)
    $fraction = '([.,](?P<fraction>\d+))';
    // g-time-zone     = %x5A  ; "Z"
    //                   / g-differential
    // g-differential  = ( MINUS / PLUS ) hour [ minute ]
    $timezone = '(?P<timezone>Z|[-+]([0-1]\d|2[0-3])([0-5]\d)?)';

    // GeneralizedTime = century year month day hour
    //                      [ minute [ second / leap-second ] ]
    //                      [ fraction ]
    //                      g-time-zone
    $pattern = '/^'.
      "$year$month$day$hour".
      "($minute$second?)?".
      "$fraction?".
      $timezone.
      '$/';

    if (preg_match($pattern, $string, $m) === 1) {
      if (!isset($m['minute']) || ($m['minute'] === '')) {
        $m['minute'] = '00';
      }
      if (!isset($m['second']) || ($m['second'] === '')) {
        $m['second'] = '00';
      }
      if (!isset($m['fraction']) || ($m['fraction'] === '')) {
        $m['fraction'] = '0';
      }
      try {
        $date = new DateTime($m['year'].'-'.$m['month'].'-'.$m['day'].'T'.$m['hour'].':'.$m['minute'].':'.$m['second'].'.'.$m['fraction'].$m['timezone']);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date;
      } catch (\Exception $e) {
        throw new Exception("Failed to create DateTime object:".$e->getMessage(), 0, $e);
      }
    } else {
      throw new Exception("$string does not match LDAP GeneralizedTime format");
    }
  }

  /**
   * @brief Convert from DateTime object to LDAP GeneralizedTime formatted string
   * @param DateTime $date DateTime object to convert
   * @param boolean $setToUTC Whether or not to set the date timezone to UTC. Defaults to TRUE.
   */
  public static function toString (DateTime $date, bool $setToUTC = TRUE): string
  {
    if ($setToUTC) {
      $date->setTimezone(new DateTimeZone('UTC'));
    }
    $fraction = (preg_replace('/0+$/', '', $date->format('u')) ?? '');
    $string   = $date->format('YmdHis');
    if ($fraction === '') {
      return preg_replace('/(00){1,2}$/', '', $string).'Z';
    } else {
      return $string.'.'.$fraction.'Z';
    }
  }
}
