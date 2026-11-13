<?php

/**
 * Contract for a single chart-completeness check.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module\Service\Completeness;

interface CompletenessRuleInterface
{
    /**
     * Stable machine code stored on tasks (unique per patient while open).
     */
    public function getCode(): string;

    /**
     * Human-readable task title (English source string for xl()).
     */
    public function getTitle(): string;

    /**
     * Short description of what is missing.
     */
    public function getDescription(): string;

    /**
     * Relative path under webroot to fix the gap (no leading slash required).
     * May include {pid} placeholder.
     */
    public function getFixPath(): string;

    /**
     * @return bool true when data is incomplete and a task should be open
     */
    public function fails(int $pid): bool;
}
