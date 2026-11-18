<?php

namespace FusionDirectory\Audit;

use DateTime;
use DateTimeZone;
use Exception;
use FusionDirectory\Audit\Action\MarkTaskCompleted;
use FusionDirectory\Audit\Action\RemoveAuditRecord;

class AuditLib
{

  public function __construct (
    private readonly int   $auditRetention,
    private readonly array $auditList,
  ) {}

  /**
   * @return list<RemoveAuditRecord|MarkTaskCompleted>
   * @throws Exception
   */
  public function getRetentionActions (
    string  $subTaskDN,
    string  $subTaskCN,
    ?string $mainTaskDn         = NULL,
    ?string $repeatableSchedule = NULL
  ): array
  {
    $actions = [];
    $today   = new DateTime();

    if (empty($this->auditList)) {
      $actions[] = new MarkTaskCompleted($subTaskDN, $subTaskCN, $mainTaskDn, $repeatableSchedule);
      return $actions;
    }

    foreach ($this->auditList as $record) {
      $auditDateTime = $this->generalizeLdapTimeToPhpObject($record['fdauditdatetime'][0]);
      $interval      = $today->diff($auditDateTime);

      if ($interval->days >= $this->auditRetention) {
        $actions[] = new RemoveAuditRecord($record['dn']);
      }
    }

    return $actions;
  }

  /**
   * @param string $generalizeLdapDateTime
   * @return DateTime|string[]
   * @throws Exception
   */
  public function generalizeLdapTimeToPhpObject (string $generalizeLdapDateTime)
  {
    $auditTimeFormatted = substr($generalizeLdapDateTime, 0, 8);

    $auditDate = DateTime::createFromFormat('Ymd', $auditTimeFormatted, new DateTimeZone('UTC'));

    if (!$auditDate) {
      return ['Error in Time conversion from Audit record with timestamp :' . $generalizeLdapDateTime];
    }

    $auditDate->setTimezone(new DateTimeZone(date_default_timezone_get()));

    return $auditDate;
  }
}
