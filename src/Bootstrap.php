<?php

/**
 * Task Manager module bootstrap (menu + completeness task hooks).
 *
 * Staff-wide work queue (providers, front desk, billing, supervisors).
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

namespace Juggernaut\ProviderDashboard\Module;

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;
use OpenEMR\Core\Kernel;
use OpenEMR\Events\Patient\PatientCreatedEvent;
use OpenEMR\Events\Patient\PatientUpdatedEvent;
use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

class Bootstrap
{
    private EventDispatcherInterface $eventDispatcher;
    private ?Kernel $kernel;

    public function __construct(EventDispatcherInterface $dispatcher, ?Kernel $kernel = null)
    {
        $this->eventDispatcher = $dispatcher;
        $this->kernel = $kernel;
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addMenuItem']);
        $this->eventDispatcher->addListener(PatientCreatedEvent::EVENT_HANDLE, [$this, 'onPatientCreated']);
        $this->eventDispatcher->addListener(PatientUpdatedEvent::EVENT_HANDLE, [$this, 'onPatientUpdated']);
    }

    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $menu = $event->getMenu();

        $menuItem = new \stdClass();
        $menuItem->requirement = 0;
        // Tab target id stays "pdb" for default_open_tabs compatibility.
        $menuItem->target = 'pdb';
        $menuItem->menu_id = 'pdb0';
        $menuItem->label = xlt('Task Manager');
        $menuItem->url = '/interface/modules/custom_modules/oe-provider-dashboard/public/index.php';
        $menuItem->children = [];
        // Menu visible to demographics staff; page _init.php enforces full staff ACL set.
        $menuItem->acl_req = ['patients', 'demo'];
        $menuItem->global_req = [];

        // Prefer Misc so all staff find it; fall back to Encounters, then top-level.
        $placed = false;
        foreach ($menu as $item) {
            if (($item->menu_id ?? '') === 'misimg') {
                $item->children[] = $menuItem;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            foreach ($menu as $item) {
                if (($item->menu_id ?? '') === 'encimg') {
                    $item->children[] = $menuItem;
                    $placed = true;
                    break;
                }
            }
        }
        if (!$placed) {
            $menu[] = $menuItem;
        }

        $event->setMenu($menu);
        return $event;
    }

    public function onPatientCreated(PatientCreatedEvent $event): void
    {
        $data = $event->getPatientData();
        $pid = (int) ($data['pid'] ?? 0);
        if ($pid <= 0) {
            return;
        }

        $this->syncCompleteness($pid);
    }

    public function onPatientUpdated(PatientUpdatedEvent $event): void
    {
        $data = $event->getNewPatientData();
        $pid = (int) ($data['pid'] ?? 0);
        if ($pid <= 0) {
            return;
        }

        $this->syncCompleteness($pid);
    }

    private function syncCompleteness(int $pid): void
    {
        try {
            $userId = (int) ($_SESSION['authUserID'] ?? 0);
            $service = new CompletenessTaskService();
            $service->syncPatient($pid, $userId > 0 ? $userId : null, $userId > 0 ? $userId : null);
        } catch (Throwable $e) {
            error_log('Task Manager completeness sync failed for pid ' . $pid . ': ' . $e->getMessage());
        }
    }
}
