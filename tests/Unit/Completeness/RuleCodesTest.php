<?php

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Tests\Unit\Completeness;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessRuleInterface;
use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;
use PHPUnit\Framework\TestCase;

final class RuleCodesTest extends TestCase
{
    public function testDefaultRulesHaveUniqueCodesAndTitles(): void
    {
        $rules = CompletenessTaskService::defaultRules();
        $this->assertNotEmpty($rules);

        $codes = [];
        foreach ($rules as $rule) {
            $this->assertInstanceOf(CompletenessRuleInterface::class, $rule);
            $code = $rule->getCode();
            $this->assertNotSame('', $code);
            $this->assertArrayNotHasKey($code, $codes);
            $codes[$code] = true;
            $this->assertNotSame('', $rule->getTitle());
            $this->assertNotSame('', $rule->getDescription());
            $this->assertNotSame('', $rule->getFixPath());
        }
    }
}
