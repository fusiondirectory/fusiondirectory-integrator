<?php

require_once __DIR__ . '/../src/autoloader.php';

use FusionDirectory\Audit\AuditLib;
use FusionDirectory\Audit\Action\RemoveAuditRecord;
use FusionDirectory\Audit\Action\MarkTaskCompleted;

$passed = 0;
$failed = 0;

function assert_true (bool $condition, string $message): void
{
  global $passed, $failed;
  if ($condition) {
    $passed++;
    echo "  PASS: $message\n";
  } else {
    $failed++;
    echo "  FAIL: $message\n";
  }
}

function assert_equals ($expected, $actual, string $message): void
{
  global $passed, $failed;
  if ($expected === $actual) {
    $passed++;
    echo "  PASS: $message\n";
  } else {
    $failed++;
    echo "  FAIL: $message (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
  }
}

function assert_instance_of (string $class, $object, string $message): void
{
  global $passed, $failed;
  if ($object instanceof $class) {
    $passed++;
    echo "  PASS: $message\n";
  } else {
    $failed++;
    echo "  FAIL: $message (expected instance of $class, got " . get_debug_type($object) . ")\n";
  }
}

echo "=== Test 1: Empty audit list returns MarkTaskCompleted ===\n";
$auditLib = new AuditLib(30, []);
$actions  = $auditLib->getRetentionActions('cn=sub1,dc=example', 'sub1', 'cn=main,dc=example', 'daily');
assert_equals(1, count($actions), 'One action returned');
assert_instance_of(MarkTaskCompleted::class, $actions[0], 'Action is MarkTaskCompleted');
assert_equals('cn=sub1,dc=example', $actions[0]->subTaskDN, 'subTaskDN correct');
assert_equals('sub1', $actions[0]->subTaskCN, 'subTaskCN correct');
assert_equals('cn=main,dc=example', $actions[0]->mainTaskDn, 'mainTaskDn correct');
assert_equals('daily', $actions[0]->repeatableSchedule, 'repeatableSchedule correct');

echo "\n=== Test 2: No expired records returns empty array ===\n";
$yesterday = (new DateTime())->modify('-1 day')->format('Ymd') . '120000Z';
$auditLib  = new AuditLib(30, [
  ['dn' => 'cn=audit1,dc=example', 'fdauditdatetime' => [$yesterday]],
]);
$actions = $auditLib->getRetentionActions('cn=sub1,dc=example', 'sub1');
assert_equals(0, count($actions), 'No actions returned for non-expired record');

echo "\n=== Test 3: Expired record returns RemoveAuditRecord ===\n";
$oldDate = (new DateTime())->modify('-60 days')->format('Ymd') . '120000Z';
$auditLib = new AuditLib(30, [
  ['dn' => 'cn=audit1,dc=example', 'fdauditdatetime' => [$oldDate]],
]);
$actions = $auditLib->getRetentionActions('cn=sub1,dc=example', 'sub1');
assert_equals(1, count($actions), 'One action returned');
assert_instance_of(RemoveAuditRecord::class, $actions[0], 'Action is RemoveAuditRecord');
assert_equals('cn=audit1,dc=example', $actions[0]->dn, 'DN correct');

echo "\n=== Test 4: Mixed expired and non-expired records ===\n";
$oldDate1  = (new DateTime())->modify('-60 days')->format('Ymd') . '120000Z';
$oldDate2  = (new DateTime())->modify('-5 days')->format('Ymd') . '120000Z';
$auditLib  = new AuditLib(30, [
  ['dn' => 'cn=audit1,dc=example', 'fdauditdatetime' => [$oldDate1]],
  ['dn' => 'cn=audit2,dc=example', 'fdauditdatetime' => [$oldDate2]],
]);
$actions = $auditLib->getRetentionActions('cn=sub1,dc=example', 'sub1');
assert_equals(1, count($actions), 'One action returned for expired record only');
assert_instance_of(RemoveAuditRecord::class, $actions[0], 'Action is RemoveAuditRecord');
assert_equals('cn=audit1,dc=example', $actions[0]->dn, 'DN of expired record correct');

echo "\n=== Test 5: No gateway dependency (constructor only takes retention + list) ===\n";
$reflection = new ReflectionClass(AuditLib::class);
$constructor = $reflection->getConstructor();
$params = $constructor->getParameters();
assert_equals(2, count($params), 'Constructor has 2 parameters');
assert_equals('auditRetention', $params[0]->getName(), 'First param is auditRetention');
assert_equals('auditList', $params[1]->getName(), 'Second param is auditList');

echo "\n=== Results: $passed passed, $failed failed ===\n";
exit($failed > 0 ? 1 : 0);
