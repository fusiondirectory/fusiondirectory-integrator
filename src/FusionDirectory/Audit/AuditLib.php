<?php

namespace FusionDirectory\Audit;

// Simplify the base code by import (using) base php classes
use DateTime;
use DateTimeZone;
use Exception;
use FusionDirectory\Ldap;

class AuditLib
{

  private int        $auditRetention;
  private ?string    $subTaskDN;
  private ?string    $subTaskCN;
  private array      $auditList;
  private ?Ldap\Link $ldapBind;
  private ?object    $gateway;

  public function __construct (
    int $auditRetention,
    array $auditList,
    ?object $gateway = NULL,
    ?string $subTaskDN = NULL,
    ?string $subTaskCN = NULL,
    ?Ldap\Link $ldapBind = NULL
  )
  {
    $this->auditRetention = $auditRetention;
    $this->subTaskDN      = $subTaskDN;
    $this->subTaskCN      = $subTaskCN;
    $this->auditList      = $auditList;
    $this->ldapBind       = $ldapBind;
    $this->gateway        = $gateway;
  }

  /**
   * @return array
   * Note : This will return a validation of audit log suppression
   * @throws Exception
   */
  public function checkAuditPassedRetentionOrchestrator (): array
  {
    $result = [];

    $today = new DateTime();

    // In case no audit exists, we have to update the tasks as well. Meaning below loop won't be reached.
    if (empty($this->auditList)) {
      $result[$this->subTaskCN]['result']       = TRUE;
      $result[$this->subTaskCN]['info']         = 'No audit to be removed.';
      $result[$this->subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($this->subTaskDN, $this->subTaskCN, "2");
    }

    foreach ($this->auditList as $record) {
      // Record in Human Readable date time object
      $auditDateTime = $this->generalizeLdapTimeToPhpObject($record['fdauditdatetime'][0]);

      $interval = $today->diff($auditDateTime);

      // Check if the interval is equal or greater than auditRetention setting
      if ($interval->days >= $this->auditRetention) {
        // If greater, delete the DN audit entry, we reuse removeSubTask method from gateway and get ldap response.(bool).
        $result[$this->subTaskCN]['result'] = $this->gateway->removeSubTask($record['dn']);
        $result[$this->subTaskCN]['info']   = 'Audit record removed.';

        // Update tasks accordingly if LDAP succeeded. TRUE Boolean returned by ldap.
        if ($result[$this->subTaskCN]['result']) {
          // Update the subtask with the status completed a.k.a "2".
          $result[$this->subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($this->subTaskDN, $this->subTaskCN, "2");
        } else {
          // Update the task with the LDAP potential error code.
          $result[$this->subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($this->subTaskDN, $this->subTaskCN, $result[$record['dn']]['result']);
        }
      }
    }

    return $result;
  }


  /**
   * @return array
   * Note : This will return a validation of audit log suppression
   * @throws Exception
   */

  public function checkAuditPassedRetentionCLI (): array
  {
    $result = [];

    $today = new DateTime();

    // Enter condition if lib is used by CLI tools
    // In case no audit exists, we have to update the tasks as well. Meaning below loop won't be reached.
    if (empty($this->auditList)) {
      return ['No audit entries found.'];
    }

    foreach ($this->auditList as $record) {
      // Record in Human Readable date time object
      $auditDateTime = $this->generalizeLdapTimeToPhpObject($record['fdauditdatetime'][0]);

      $interval = $today->diff($auditDateTime);

      // Check if the interval is equal or greater than auditRetention setting
      if ($interval->days >= $this->auditRetention) {
        // If greater, delete the DN audit entry, we reuse removeSubTask method from gateway and get ldap response.(bool).

        $result[$record['dn']]               = 'audit entry requiring deletion';
        $result[$record['dn']]['ldapStatus'] = $this->ldapBind->delete($record['dn']);

      }

    }
    return $result;
  }

  /**
   * @param $generalizeLdapDateTime
   * @return DateTime|string[]
   * @throws Exception
   * Note : Simply take a generalized Ldap time (with UTC = Z) and transform it to php object dateTime.
   */
  public
  function generalizeLdapTimeToPhpObject ($generalizeLdapDateTime)
  {
    // Extract the date part (first 8 characters: YYYYMMDD), we do not care about hour and seconds.
    $auditTimeFormatted = substr($generalizeLdapDateTime, 0, 8);

    // Create a DateTime object using only the date part, carefully setting the timezone to UTC. Audit timestamp is UTC
    $auditDate = DateTime::createFromFormat('Ymd', $auditTimeFormatted, new DateTimeZone('UTC'));

    // Check if the DateTime object was created successfully
    if (!$auditDate) {
      return ['Error in Time conversion from Audit record with timestamp :' . $generalizeLdapDateTime];
    }

    // Transform dateTime object from UTC to local defined dateTime. (Timezone is set in index.php if used by orchestrator).
    $auditDate->setTimezone(new DateTimeZone(date_default_timezone_get()));

    return $auditDate;
  }
}