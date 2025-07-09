<?php

namespace Kenjiefx\Themepack;

use Kenjiefx\ScratchPHP\App\Events\ListensTo;
use Kenjiefx\ScratchPHP\App\Events\PageBuildStartedEvent;
use Kenjiefx\ScratchPHP\App\Extensions\ExtensionsInterface;
use Kenjiefx\Themepack\Services\AppRouterService;

class ThemePackExtension implements ExtensionsInterface {

    public function __construct(
        public readonly AppRouterService $appRouterService
    ) {}
    
    #[ListensTo(PageBuildStartedEvent::class)]
    public function beforePageBuild(PageBuildStartedEvent $event){
        $this->appRouterService->build($event->getPageModel());
    }

}