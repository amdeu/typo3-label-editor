<?php

declare(strict_types=1);

namespace UBOS\LabelEditor\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use UBOS\LabelEditor\Backend\Service\ExtensionScannerService;
use UBOS\LabelEditor\Backend\Service\RegistryService;
use UBOS\LabelEditor\Backend\Service\XliffService;

#[AsController]
class LabelEditorController extends ActionController
{
	protected ModuleTemplate $moduleTemplate;

	public function __construct(
		protected readonly ModuleTemplateFactory $moduleTemplateFactory,
		private readonly RegistryService $registryService,
		private readonly ExtensionScannerService $scannerService,
		private readonly XliffService $xliffService,
		private readonly BackendUriBuilder $backendUriBuilder,
		protected IconFactory $iconFactory,
	) {}

	protected function initializeAction(): void
	{
		$this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
		$this->moduleTemplate->setTitle('Label Editor');
	}

	public function indexAction(): ResponseInterface
	{
		$managedExtensions = $this->registryService->getManagedExtensions();
		$availableExtensions = $this->scannerService->getAvailableExtensions();

		// Filter out already managed extensions
		$unmanaged = array_filter(
			$availableExtensions,
			fn($ext) => !isset($managedExtensions[$ext['key']])
		);
		$managedExtensions = array_map(
			fn($key, $files) => array_merge(
				$availableExtensions[$key] ?? [],
				[
				'key' => $key,
				'files' => $files,
				]
			),
			array_keys($managedExtensions),
			$managedExtensions
		);

		$this->moduleTemplate->assignMultiple([
			'managedExtensions' => $managedExtensions,
			'availableExtensions' => $unmanaged,
		]);

		return $this->moduleTemplate->renderResponse('LabelEditor/Index');
	}

	public function addExtensionAction(string $extensionKey): ResponseInterface
	{
		$locallangFiles = $this->scannerService->findLocallangFiles($extensionKey);

		if (empty($locallangFiles)) {
			$this->addFlashMessage(
				"No locallang files found in extension '{$extensionKey}'.",
				'No Files Found',
				ContextualFeedbackSeverity::WARNING
			);
		} else {
			$this->registryService->addExtension($extensionKey, $locallangFiles);

			// Clear core cache
			//$this->clearCoreCache();

			$this->addFlashMessage(
				sprintf(
					"Extension '%s' added with %d translation file(s). System cache cleared.",
					$extensionKey,
					count($locallangFiles)
				),
				'Extension Added',
				ContextualFeedbackSeverity::OK
			);
		}

		return $this->redirect('index');
	}

	public function removeExtensionAction(string $extensionKey): ResponseInterface
	{
		$this->registryService->removeExtension($extensionKey);

		// Clear core cache
		//$this->clearCoreCache();

		$this->addFlashMessage(
			"Extension '{$extensionKey}' removed from label management. System cache cleared.",
			'Extension Removed',
			ContextualFeedbackSeverity::OK
		);

		return $this->redirect('index');
	}

	public function editExtensionAction(
		string $extensionKey,
		string $sourceFile = '',
		string $languageKey = 'default'
	): ResponseInterface {
		$registry = $this->registryService->getRegistry();
		$extensionFiles = [];

		foreach (array_keys($registry) as $file) {
			if (str_starts_with($file, "EXT:{$extensionKey}/")) {
				$extensionFiles[] = $file;
			}
		}

		if (empty($sourceFile) && !empty($extensionFiles)) {
			$sourceFile = $extensionFiles[0];
		}

		$translations = [];
		$defaultLanguageData = [];

		if ($sourceFile) {
			$overridePath = $this->registryService->getOverridePath($sourceFile, $languageKey);

			if ($languageKey === 'default') {
				$translations = $this->xliffService->getTranslations($sourceFile, $overridePath);
			} else {
				// For non-default languages, also load default language data
				$defaultOverridePath = $this->registryService->getOverridePath($sourceFile, 'default');
				$defaultLanguageData = $this->xliffService->getTranslations($sourceFile, $defaultOverridePath);

				// Load current language translations
				$translations = $this->xliffService->getLanguageTranslations($sourceFile, $languageKey, $overridePath);

				// Merge default language data into translations array
				foreach ($translations as $key => $data) {
					if (isset($defaultLanguageData[$key])) {
						$translations[$key]['defaultSource'] = $defaultLanguageData[$key]['source'];
						$translations[$key]['defaultOverride'] = $defaultLanguageData[$key]['override'] ?? '';
						$translations[$key]['hasDefaultOverride'] = !empty($defaultLanguageData[$key]['override']);
					}
				}
			}
		}

		$this->moduleTemplate->assignMultiple([
			'extensionKey' => $extensionKey,
			'sourceFile' => $sourceFile,
			'languageKey' => $languageKey,
			'extensionFiles' => $extensionFiles,
			'availableLanguages' => $this->xliffService->getAvailableLanguages(),
			'translations' => $translations,
		]);

		$this->addDocHeaderCloseAndSaveButtons();

		return $this->moduleTemplate->renderResponse('LabelEditor/EditExtension');
	}


	protected function addDocHeaderCloseAndSaveButtons(): void
	{
		$closeUrl = GeneralUtility::sanitizeLocalUrl(
			(string)($this->request->getQueryParams()['returnUrl'] ?? '')
		) ?: (string)$this->backendUriBuilder->buildUriFromRoute('site_labeleditor');
		$languageService = $this->getLanguageService();
		$buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
		$closeButton = $buttonBar->makeLinkButton()
			->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:close'))
			->setIcon($this->iconFactory->getIcon('actions-close', IconSize::SMALL))
			->setShowLabelText(true)
			->setHref($closeUrl);
		$buttonBar->addButton($closeButton, ButtonBar::BUTTON_POSITION_LEFT, 2);
		$saveButton = $buttonBar->makeInputButton()
			->setName('CMD')
			->setValue('save')
			->setForm('labeleditor_form')
			->setIcon($this->iconFactory->getIcon('actions-document-save', IconSize::SMALL))
			->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_common.xlf:save'))
			->setShowLabelText(true);
		$buttonBar->addButton($saveButton, ButtonBar::BUTTON_POSITION_LEFT, 4);
	}

	public function saveTranslationAction(): ResponseInterface
	{
		$data = $this->request->getParsedBody();
		$extensionKey = $data['extensionKey'] ?? '';
		$sourceFile = $data['sourceFile'] ?? '';
		$languageKey = $data['languageKey'] ?? 'default';
		$translations = $data['translations'] ?? [];

		$overridePath = $this->registryService->getOverridePath($sourceFile, $languageKey);

		// Get full translation data including source values
		if ($languageKey === 'default') {
			$fullTranslations = $this->xliffService->getTranslations($sourceFile, $overridePath);
		} else {
			$fullTranslations = $this->xliffService->getLanguageTranslations($sourceFile, $languageKey, $overridePath);
		}

		// Merge POST data into full translation structure
		foreach ($translations as $key => $value) {
			if (isset($fullTranslations[$key])) {
				$fullTranslations[$key]['override'] = $value;
			}
		}

		$this->xliffService->saveTranslations($overridePath, $fullTranslations, $languageKey, $sourceFile);

		// Clear translation caches (NOT core cache)
		//$this->clearTranslationCaches();

		$this->addFlashMessage(
			'Labels saved and caches cleared.',
			'Labels Updated',
			ContextualFeedbackSeverity::OK
		);

		return $this->redirect('editExtension', null, null, [
			'extensionKey' => $extensionKey,
			'sourceFile' => $sourceFile,
			'languageKey' => $languageKey,
		]);
	}

	protected function getLanguageService(): LanguageService
	{
		return $GLOBALS['LANG'];
	}

	private function clearCoreCache(): void
	{
		$cacheManager = GeneralUtility::makeInstance(CacheManager::class);
		$cacheManager->flushCachesInGroup('system');
	}

	private function clearTranslationCaches(): void
	{
		$cacheManager = GeneralUtility::makeInstance(CacheManager::class);

		// Clear parsed translations
		$cacheManager->getCache('l10n')->flush();

		// Clear frontend page cache
		$cacheManager->flushCachesInGroup('pages');
	}
}