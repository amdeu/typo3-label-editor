<?php

declare(strict_types=1);

namespace Amdeu\LabelEditor\Backend\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Amdeu\LabelEditor\Backend\Service\ExtensionScannerService;
use Amdeu\LabelEditor\Backend\Service\RegistryService;
use Amdeu\LabelEditor\Backend\Service\XliffService;

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

		$unmanaged = array_filter($availableExtensions, fn($ext) => !isset($managedExtensions[$ext['key']]));
		$managedExtensions = array_map(
			fn($key, $files) => array_merge($availableExtensions[$key] ?? [], ['key' => $key, 'files' => $files]),
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
			$this->addFlashMessage(
				sprintf("Extension '%s' added with %d translation file(s). System cache cleared.", $extensionKey, count($locallangFiles)),
				'Extension Added',
				ContextualFeedbackSeverity::OK
			);
		}

		return $this->redirect('index');
	}

	public function removeExtensionAction(string $extensionKey): ResponseInterface
	{
		$this->registryService->removeExtension($extensionKey);
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
		array $languageKeys = []
	): ResponseInterface {
		$registry = $this->registryService->getRegistry();
		$extensionFiles = array_values(array_filter(array_keys($registry), fn($file) => str_starts_with($file, "EXT:{$extensionKey}/")));

		$sourceFile = $sourceFile ?: ($extensionFiles[0] ?? '');
		$languageKeys = $languageKeys ?: ['default'];

		$unifiedTranslations = $this->buildUnifiedTranslations($sourceFile, $languageKeys);
		$selectedLanguagesMap = array_fill_keys($languageKeys, true);

		$this->moduleTemplate->assignMultiple([
			'extensionKey' => $extensionKey,
			'sourceFile' => $sourceFile,
			'languageKeys' => $languageKeys,
			'extensionFiles' => $extensionFiles,
			'availableLanguages' => $this->xliffService->getAvailableLanguages(),
			'translations' => $unifiedTranslations,
			'selectedLanguagesMap' => $selectedLanguagesMap,
		]);

		$this->addDocHeaderCloseAndSaveButtons();
		return $this->moduleTemplate->renderResponse('LabelEditor/EditExtension');
	}

	public function saveTranslationAction(): ResponseInterface
	{
		$data = $this->request->getParsedBody();
		$extensionKey = $data['extensionKey'] ?? '';
		$sourceFile = $data['sourceFile'] ?? '';
		$languageKeys = $data['languageKeys'] ?? ['default'];
		$translations = $data['translations'] ?? [];

		if(!$languageKeys) {
			$languageKeys = ['default'];
		}
		foreach ($languageKeys as $languageKey) {
			if (!isset($translations[$languageKey])) {
				continue;
			}

			$overridePath = $this->registryService->getOverridePath($sourceFile, $languageKey);
			$fullTranslations = $this->loadTranslations($sourceFile, $languageKey, $overridePath);

			foreach ($translations[$languageKey] as $key => $value) {
				if (isset($fullTranslations[$key])) {
					$fullTranslations[$key]['override'] = $value;
				}
			}

			$this->xliffService->saveTranslations($overridePath, $fullTranslations, $languageKey, $sourceFile);
		}

		$this->addFlashMessage(
			"Labels saved for " . count($languageKeys) . " language(s) and caches cleared.",
			'Labels Updated',
			ContextualFeedbackSeverity::OK
		);

		return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
	}

	public function addLabelAction(
		string $extensionKey,
		string $sourceFile,
		array $languageKeys = [],
		string $labelKey = ''
	): ResponseInterface {
		if (!$labelKey) {
			return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
		}

		if (!preg_match('/^[a-zA-Z0-9._-]+$/', $labelKey)) {
			$this->addFlashMessage(
				'Label key can only contain letters, numbers, dots, underscores and hyphens.',
				'Invalid Label Key',
				ContextualFeedbackSeverity::ERROR
			);
			return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
		}

		$registry = $this->registryService->getRegistry();
		$allLanguagePaths = $registry[$sourceFile] ?? [];

		if (empty($allLanguagePaths)) {
			$this->addFlashMessage('Could not find language files for this source file.', 'Error', ContextualFeedbackSeverity::ERROR);
			return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
		}

		if ($this->labelKeyExists($sourceFile, $labelKey, $allLanguagePaths)) {
			$this->addFlashMessage("Label key '{$labelKey}' already exists. Please use a different key.", 'Duplicate Label Key', ContextualFeedbackSeverity::WARNING);
			return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
		}

		$this->addLabelToAllLanguages($sourceFile, $labelKey, $allLanguagePaths);
		$this->addFlashMessage("New label '{$labelKey}' has been added to all languages.", 'Label Added', ContextualFeedbackSeverity::OK);

		return $this->redirectToEditExtension($extensionKey, $sourceFile, $languageKeys);
	}

	protected function addDocHeaderCloseAndSaveButtons(): void
	{
		$closeUrl = GeneralUtility::sanitizeLocalUrl((string)($this->request->getQueryParams()['returnUrl'] ?? ''))
			?: (string)$this->backendUriBuilder->buildUriFromRoute('site_labeleditor');

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

	private function buildUnifiedTranslations(string $sourceFile, array $languageKeys): array
	{
		$translationsByLanguage = [];
		$allKeys = [];

		foreach ($languageKeys as $langKey) {
			$overridePath = $this->registryService->getOverridePath($sourceFile, $langKey);
			$translations = $this->loadTranslations($sourceFile, $langKey, $overridePath);
			$translationsByLanguage[$langKey] = $translations;
			$allKeys = array_merge($allKeys, array_keys($translations));
		}

		$allKeys = array_unique($allKeys);
		$unifiedTranslations = [];

		foreach ($allKeys as $key) {
			$unifiedTranslations[$key] = ['key' => $key, 'source' => '', 'languages' => []];

			foreach ($languageKeys as $langKey) {
				if (isset($translationsByLanguage[$langKey][$key])) {
					$trans = $translationsByLanguage[$langKey][$key];
					if ($langKey === 'default') {
						$trans['translation'] = $trans['source'] ?? '';
					}
					if (empty($unifiedTranslations[$key]['source'])) {
						$unifiedTranslations[$key]['source'] = $trans['source'] ?? '';
					}
					$unifiedTranslations[$key]['languages'][$langKey] = [
						'override' => $trans['override'] ?? '',
						'target' => $trans['translation'] ?? ''
					];
				} else {
					$unifiedTranslations[$key]['languages'][$langKey] = ['override' => '', 'target' => ''];
				}
			}
		}

		// sort by key
		ksort($unifiedTranslations);
		return $unifiedTranslations;
	}

	private function loadTranslations(string $sourceFile, string $languageKey, string $overridePath): array
	{
		return $languageKey === 'default'
			? $this->xliffService->getTranslations($sourceFile, $overridePath)
			: $this->xliffService->getLanguageTranslations($sourceFile, $languageKey, $overridePath);
	}

	private function labelKeyExists(string $sourceFile, string $labelKey, array $allLanguagePaths): bool
	{
		foreach ($allLanguagePaths as $langKey => $overridePath) {
			$existingTranslations = $this->loadTranslations($sourceFile, $langKey, $overridePath);
			if (isset($existingTranslations[$labelKey])) {
				return true;
			}
		}
		return false;
	}

	private function addLabelToAllLanguages(string $sourceFile, string $labelKey, array $allLanguagePaths): void
	{
		$value = ' ';

		foreach ($allLanguagePaths as $langKey => $overridePath) {
			$existingTranslations = $this->loadTranslations($sourceFile, $langKey, $overridePath);
			$existingTranslations[$labelKey] = ['key' => $labelKey, 'source' => $value, 'override' => $value];
			if ($langKey !== 'default') {
				$existingTranslations[$labelKey]['translation'] = '';
			}
			$this->xliffService->saveTranslations($overridePath, $existingTranslations, $langKey, $sourceFile);
		}
	}

	private function redirectToEditExtension(string $extensionKey, string $sourceFile, array $languageKeys): ResponseInterface
	{
		return $this->redirect('editExtension', null, null, [
			'extensionKey' => $extensionKey,
			'sourceFile' => $sourceFile,
			'languageKeys' => $languageKeys,
		]);
	}

	protected function getLanguageService(): LanguageService
	{
		return $GLOBALS['LANG'];
	}

	private function clearCoreCache(): void
	{
		GeneralUtility::makeInstance(CacheManager::class)->flushCachesInGroup('system');
	}

	private function clearTranslationCaches(): void
	{
		$cacheManager = GeneralUtility::makeInstance(CacheManager::class);
		$cacheManager->getCache('l10n')->flush();
		$cacheManager->flushCachesInGroup('pages');
	}
}