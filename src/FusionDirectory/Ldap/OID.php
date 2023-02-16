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
 * Standard OID lists, with description and associated RFC number
 */
class OID
{
  /**
   * Standard controls OID list
   *
   * @var array<string,array<string|int>> Keys are OID, values are array with desc and rfc items
   */
  public const CONTROLS = [
    LDAP_CONTROL_MANAGEDSAIT => [
      'desc'  => 'Manage DSA IT',
      'rfc'   => 3296,
    ],
    LDAP_CONTROL_PROXY_AUTHZ => [
      'desc'  => 'Proxied Authorization',
      'rfc'   => 4370,
    ],
    LDAP_CONTROL_SUBENTRIES => [
      'desc'  => 'Subentries',
      'rfc'   => 3672,
    ],
    LDAP_CONTROL_VALUESRETURNFILTER => [
      'desc'  => 'Filter returned values',
      'rfc'   => 3876,
    ],
    LDAP_CONTROL_ASSERT => [
      'desc'  => 'Assertion',
      'rfc'   => 4528,
    ],
    LDAP_CONTROL_PRE_READ => [
      'desc'  => 'Pre read',
      'rfc'   => 4527,
    ],
    LDAP_CONTROL_POST_READ => [
      'desc'  => 'Post read',
      'rfc'   => 4527,
    ],
    LDAP_CONTROL_SORTREQUEST => [
      'desc'  => 'Sort request',
      'rfc'   => 2891,
    ],
    LDAP_CONTROL_SORTRESPONSE => [
      'desc'  => 'Sort response',
      'rfc'   => 2891,
    ],
    LDAP_CONTROL_PAGEDRESULTS => [
      'desc'  => 'Paged results',
      'rfc'   => 2696,
    ],
    '2.16.840.1.113730.3.4.16' => [
      'desc'  => 'Authorization Identity Request',
      'rfc'   => 3829,
    ],
    '2.16.840.1.113730.3.4.15' => [
      'desc'  => 'Authorization Identity Response',
      'rfc'   => 3829,
    ],
    LDAP_CONTROL_SYNC => [
      'desc'  => 'Content Synchronization Operation',
      'rfc'   => 4533,
    ],
    LDAP_CONTROL_SYNC_STATE => [
      'desc'  => 'Content Synchronization Operation State',
      'rfc'   => 4533,
    ],
    LDAP_CONTROL_SYNC_DONE => [
      'desc'  => 'Content Synchronization Operation Done',
      'rfc'   => 4533,
    ],
    LDAP_CONTROL_DONTUSECOPY => [
      'desc'  => 'Don\'t Use Copy',
      'rfc'   => 6171,
    ],
    /* LDAP_CONTROL_PASSWORDPOLICYREQUEST and LDAP_CONTROL_PASSWORDPOLICYRESPONSE are the same */
    LDAP_CONTROL_PASSWORDPOLICYREQUEST => [
      'desc'  => 'Password Policy',
    ],
    LDAP_CONTROL_X_INCREMENTAL_VALUES => [
      'desc'  => 'Active Directory Incremental Values',
    ],
    LDAP_CONTROL_X_DOMAIN_SCOPE => [
      'desc'  => 'Active Directory Domain Scope',
    ],
    LDAP_CONTROL_X_PERMISSIVE_MODIFY => [
      'desc'  => 'Active Directory Permissive Modify',
    ],
    LDAP_CONTROL_X_SEARCH_OPTIONS => [
      'desc'  => 'Active Directory Search Options',
    ],
    LDAP_CONTROL_X_TREE_DELETE => [
      'desc'  => 'Active Directory Tree Delete',
    ],
    LDAP_CONTROL_X_EXTENDED_DN => [
      'desc'  => 'Active Directory Extended DN',
    ],
    LDAP_CONTROL_VLVREQUEST => [
      'desc'  => 'Virtual List View Request',
    ],
    LDAP_CONTROL_VLVRESPONSE => [
      'desc'  => 'Virtual List View Response',
    ],
  ];

  public const LDAP_EXOP_CANCEL = '1.3.6.1.1.8';

  /**
   * Standard extended operations OID list
   *
   * @var array<string,array<string|int>> Keys are OID, values are array with desc and rfc items
   */
  public const EXOPS = [
    LDAP_EXOP_START_TLS => [
      'desc'  => 'Start TLS',
      'rfc'   => 4511,
    ],
    LDAP_EXOP_MODIFY_PASSWD => [
      'desc'  => 'Modify password',
      'rfc'   => 3062,
    ],
    LDAP_EXOP_REFRESH => [
      'desc'  => 'Refresh',
      'rfc'   => 2589,
    ],
    LDAP_EXOP_WHO_AM_I => [
      'desc'  => 'WHOAMI',
      'rfc'   => 4532,
    ],
    LDAP_EXOP_TURN => [
      'desc'  => 'Turn',
      'rfc'   => 4531,
    ],
    OID::LDAP_EXOP_CANCEL => [
      'desc'  => 'Cancel operation',
      'rfc'   => 3909,
    ],
  ];

  public const LDAP_FEATURE_MODIFYINCREMENT           = '1.3.6.1.1.14';
  public const LDAP_FEATURE_ALLOPERATIONALATTRIBUTES  = '1.3.6.1.4.1.4203.1.5.1';
  public const LDAP_FEATURE_RETURNALLATTRIBUTES       = '1.3.6.1.4.1.4203.1.5.2';
  public const LDAP_FEATURE_ABSOLUTEFILTERS           = '1.3.6.1.4.1.4203.1.5.3';
  public const LDAP_FEATURE_LANGUAGETAG               = '1.3.6.1.4.1.4203.1.5.4';
  public const LDAP_FEATURE_RANGEMATCHING             = '1.3.6.1.4.1.4203.1.5.5';

  /**
   * Standard features OID list
   *
   * @var array<string,array<string|int>> Keys are OID, values are array with desc and rfc items
   */
  public const FEATURES = [
    OID::LDAP_FEATURE_MODIFYINCREMENT => [
      'desc'  => 'Modify-Increment Extension',
      'rfc'   => 4525,
    ],
    OID::LDAP_FEATURE_ALLOPERATIONALATTRIBUTES => [
      'desc'  => 'All Operational Attributes',
      'rfc'   => 3673,
    ],
    OID::LDAP_FEATURE_RETURNALLATTRIBUTES => [
      'desc'  => 'Return of All Attributes of an Object Class',
      'rfc'   => 4529,
    ],
    OID::LDAP_FEATURE_ABSOLUTEFILTERS => [
      'desc'  => 'Absolute True and False Filters',
      'rfc'   => 4526,
    ],
    OID::LDAP_FEATURE_LANGUAGETAG => [
      'desc'  => 'Language Tag Options',
      'rfc'   => 3866,
    ],
    OID::LDAP_FEATURE_RANGEMATCHING => [
      'desc'  => 'Language Range Matching of Attributes',
      'rfc'   => 3866,
    ],
  ];
}
