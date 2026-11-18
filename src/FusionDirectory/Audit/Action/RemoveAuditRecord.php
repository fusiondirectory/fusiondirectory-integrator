<?php

namespace FusionDirectory\Audit\Action;

class RemoveAuditRecord
{
  public function __construct (
    public readonly string $dn,
  ) {}
}
