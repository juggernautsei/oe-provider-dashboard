<?php

/**
 * Provider Dashboard module bootstrap (menu registration).
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

namespace Juggernaut\ProviderDashboard\Module;

use OpenEMR\Core\Kernel;
use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Bootstrap
{
    private EventDispatcherInterface $eventDispatcher;
    private ?Kernel $kernel;

    public function __construct(EventDispatcher $dispatcher, ?Kernel $kernel = null)
    {
        $this->eventDispatcher = $dispatcher;
        $this->kernel = $kernel;
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addMenuItem']);
    }

    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $menu = $event->getMenu();

        $menuItem = new \stdClass();
        $menuItem->requirement = 0;
        $menuItem->target = 'pdb';
        $menuItem->menu_id = 'pdb0';
        $menuItem->label = xlt('Provider Dashboard');
        $menuItem->url = '/interface/modules/custom_modules/oe-provider-dashboard/public/index.php';
        $menuItem->children = [];
        $menuItem->acl_req = ['encounters', 'coding_a'];
        $menuItem->global_req = [];

        // Prefer Notes/Encounters area; fall back to Misc
        $placed = false;
        foreach ($menu as $item) {
            if (($item->menu_id ?? '') === 'encimg' || ($item->menu_id ?? '') === 'misimg') {
                $item->children[] = $menuItem;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $menu[] = $menuItem;
        }

        $event->setMenu($menu);
        return $event;
    }
}
