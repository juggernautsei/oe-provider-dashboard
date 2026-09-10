<?php

/**
 * Document review status values.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

namespace Juggernaut\ProviderDashboard\Module\Controllers;

enum DocumentStatus: int
{
    case NotReady = 0;
    case Ready = 1;
    case InReview = 2;
    case InProgress = 3;
    case Reviewed = 4;
}
