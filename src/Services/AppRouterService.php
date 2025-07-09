<?php 

namespace Kenjiefx\Themepack\Services;

use Kenjiefx\ScratchPHP\App\Components\ComponentService;
use Kenjiefx\ScratchPHP\App\Configurations\ConfigurationInterface;
use Kenjiefx\ScratchPHP\App\Files\FileFactory;
use Kenjiefx\ScratchPHP\App\Files\FileService;
use Kenjiefx\ScratchPHP\App\Pages\PageModel;
use Kenjiefx\ScratchPHP\App\Templates\TemplateModel;
use Kenjiefx\ScratchPHP\App\Templates\TemplateService;
use Kenjiefx\ScratchPHP\App\Themes\ThemeFactory;
use Kenjiefx\ScratchPHP\App\Themes\ThemeService;

class AppRouterService {

    public function __construct(
        public readonly ConfigurationInterface $configuration,
        public readonly ThemeFactory $themeFactory,
        public readonly ThemeService $themeService,
        public readonly ComponentService $componentService,
        public readonly FileService $fileService,
        public readonly FileFactory $fileFactory,
        public readonly TemplateService $templateService
    ) {}

    public function build(PageModel $pageModel){
        $templateJsContent = $this->getTemplateContent($pageModel->templateModel);
        $templateJsContent = $this->updateImportPath(
            $this->getTemplateJsPath($pageModel->templateModel),
            $this->getAppRouterJsPath(),
            $templateJsContent
        );
        $appRouterContents = $this->getAppRouterContents();
        $appRouterContents = $this->injectTemplateJsIntoAppRouter($appRouterContents, $templateJsContent);
        $this->updateAppRouterContents($appRouterContents);
    }

    public function getThemeDir() {
        $themeModel = $this->getThemeModel();
        return $this->themeService->getThemeDir($themeModel);
    }

    public function getThemeModel() {
        $themeName = $this->configuration->getThemeName();
        return $this->themeFactory->create($themeName);
    }

    public function getComponentsDir() {
        $themeModel = $this->getThemeModel();
        return $this->componentService->getComponentsDir($themeModel);
    }

    public function getAppRouterJsPath(){
        $componentsDir = $this->getComponentsDir();
        return "{$componentsDir}/Themepack/AppRouter/AppRouter.js";
    }

    public function getAppRouterContents() {
        $filePath = $this->getAppRouterJsPath();
        $fileObject = $this->fileFactory->create($filePath);
        return $this->fileService->readFile($fileObject);
    }

    public function getTemplatesDir() {
        $themeModel = $this->getThemeModel();
        return $this->templateService->getTemplatesDir($themeModel);
    }

    public function getTemplateJsPath(TemplateModel $templateModel) {
        $templateName = $templateModel->name;
        $templatesDir = $this->getTemplatesDir();
        return "{$templatesDir}/{$templateName}.js";
    }

    public function getTemplateContent(TemplateModel $templateModel) {
        $templateJsPath = $this->getTemplateJsPath($templateModel);
        $fileObject = $this->fileFactory->create($templateJsPath);
        if (!$this->fileService->fileExists($fileObject)) {
            return $this->createBasicBootstrapper();
        } 
        return $this->fileService->readFile($fileObject);
    }

    public function createBasicBootstrapper() {
        return "export const bootstrap = async () => {};";
    }

    public function injectTemplateJsIntoAppRouter(string $appRouterContent, string $templateJsContent) {
        return str_replace("//#Template_Bootstrap", $templateJsContent, $appRouterContent);
    }

    private function updateAppRouterContents($appRouterContent) {
        $appRouterPath = $this->getAppRouterJsPath();
        $fileObject = $this->fileFactory->create($appRouterPath);
        $this->fileService->writeFile($fileObject, $appRouterContent);
    }

    public function updateImportPath($templateJsPath, $appRouterJsPath, string $jsContent) {
        $originalDir = realpath(dirname($templateJsPath));
        $newDir = realpath(dirname($appRouterJsPath));
        if (!$originalDir || !$newDir) {
            throw new \Exception("One or both of the provided paths are invalid.");
        }
        return preg_replace_callback('/import\s+[^\'"]+from\s+[\'"]([^\'"]+)[\'"];/',
            function ($matches) use ($originalDir, $newDir) {
                $importPath = $matches[1];

                // Only adjust relative imports
                if (strpos($importPath, '.') !== 0) {
                    return $matches[0];
                }

                $absoluteImportPath = $this->normalizePath($originalDir . '/' . $importPath);
                if (!$absoluteImportPath) {
                    return $matches[0]; // Leave unchanged if path doesn't resolve
                }

                // Calculate new relative path from newDir to import target
                $newRelativePath = $this->getRelativePath($newDir, $absoluteImportPath);

                $relative = $this->getRelativePath($newDir, $absoluteImportPath);
                return str_replace($importPath, $newRelativePath, $matches[0]);
            },
            $jsContent
        );
    }

    public function getRelativePath($from, $to) {
        $from = explode(DIRECTORY_SEPARATOR, $this->normalizePath($from));
        $to = explode(DIRECTORY_SEPARATOR, $this->normalizePath($to));

        // Windows: check if both are on the same drive
        if (preg_match('/^[a-zA-Z]:$/', $from[0] ?? '') && $from[0] !== ($to[0] ?? '')) {
            // Cannot make a relative path between different drives
            return implode('/', $to);
        }

        // Remove drive letter from comparison
        if (preg_match('/^[a-zA-Z]:$/', $from[0] ?? '')) array_shift($from);
        if (preg_match('/^[a-zA-Z]:$/', $to[0] ?? '')) array_shift($to);

        while (count($from) && count($to) && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)) . implode('/', $to);
    }

    public function normalizePath($path) {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        // Handle Windows drive letter (e.g., C:)
        if (preg_match('/^[a-zA-Z]:$/', $parts[0] ?? '')) {
            $drive = array_shift($parts);
            return $drive . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
        }
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

}