<?php

namespace FusionDirectory\Audit;

class AuditLib
{

  private $subTaskDN, $subTaskCN;
  private int $auditRetention;
  // Usage of CLI bool is to make sure we use proper method in case of direct CLI call. (Instead of Orchestrator).
  private bool $CLI;

  public function __construct (INT $auditRetention, BOOL $CLI = FALSE, STRING $subTaskDN = NULL, STRING $subTaskCN = NULL)
  {
   $this->auditRetention = $auditRetention;
   $this->CLI = $CLI;
   $this->subTaskDN = $subTaskDN;
   $this->subTaskCN = $subTaskCN;
  }

  /**
   * @param $auditRetention
   * @param $subTaskDN
   * @param $subTaskCN
   * @return array
   * Note : This will return a validation of audit log suppression
   */

  public function checkAuditPassedRetention ($auditRetention, $subTaskDN, $subTaskCN): array
  {
    $result = [];

    // Date time object will use the timezone defined in FD, code is in index.php
    $today = new DateTime();

    // Search in LDAP for audit entries (All entries ! This can be pretty heavy.
    $audit = $this->gateway->getLdapTasks('(objectClass=fdAuditEvent)', ['fdAuditDateTime'], '', '');
    // Remove the count key from the audit array.
    $this->gateway->unsetCountKeys($audit);

    // In case no audit exists, we have to update the tasks as well. Meaning below loop won't be reached.
    if (empty($audit)) {
      $result[$subTaskCN]['result']       = TRUE;
      $result[$subTaskCN]['info']         = 'No audit to be removed.';
      $result[$subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($subTaskDN, $subTaskCN, "2");
    }

    foreach ($audit as $record) {
      // Record in Human Readable date time object
      $auditDateTime = $this->generalizeLdapTimeToPhpObject($record['fdauditdatetime'][0]);

      $interval = $today->diff($auditDateTime);

      // Check if the interval is equal or greater than auditRetention setting
      if ($interval->days >= $auditRetention) {
        // If greater, delete the DN audit entry, we reuse removeSubTask method from gateway and get ldap response.(bool).
        $result[$subTaskCN]['result'] = $this->gateway->removeSubTask($record['dn']);
        $result[$subTaskCN]['info']   = 'Audit record removed.';

        // Update tasks accordingly if LDAP succeeded. TRUE Boolean returned by ldap.
        if ($result[$subTaskCN]['result']) {
          // Update the subtask with the status completed a.k.a "2".
          $result[$subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($subTaskDN, $subTaskCN, "2");
        } else {
          // Update the task with the LDAP potential error code.
          $result[$subTaskCN]['statusUpdate'] = $this->gateway->updateTaskStatus($subTaskDN, $subTaskCN, $result[$record['dn']]['result']);
        }
      }
    }

    return $result;
  }
}