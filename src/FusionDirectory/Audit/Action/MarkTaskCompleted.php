<?php

namespace FusionDirectory\Audit\Action;

class MarkTaskCompleted
{
  public function __construct (
    public readonly string  $subTaskDN,
    public readonly string  $subTaskCN,
    public readonly ?string $mainTaskDn,
    public readonly ?string $repeatableSchedule,
  ) {}
}
