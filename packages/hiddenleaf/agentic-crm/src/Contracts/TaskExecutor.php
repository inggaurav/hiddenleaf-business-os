<?php

namespace HiddenLeaf\AgenticCrm\Contracts;

use HiddenLeaf\AgenticCrm\Domain\AgentRunResult;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;

interface TaskExecutor
{
    public function execute(AgentTask $task): AgentRunResult;
}
