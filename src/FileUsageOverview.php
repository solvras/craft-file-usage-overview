<?php

namespace solvras\craftcraftfileusageoverview;

use Craft;
use craft\base\Plugin;
use solvras\craftcraftfileusageoverview\services\AssetUsageService;
use solvras\craftcraftfileusageoverview\services\FileUsageOverviewService;
use yii\base\Event;
use craft\elements\Asset;
use craft\events\DefineElementEditorHtmlEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\DefineAttributeHtmlEvent;
use craft\controllers\ElementsController;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\services\Elements;
use craft\redactor\Field as Redactor;
use craft\ckeditor\Field as CkEditor;
use craft\fields\Matrix;
use craft\web\View;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterTemplateRootsEvent;
use \craft\fieldlayoutelements\CustomField;

/**
 * craft-file-usage-overview plugin
 *
 * @method static FileUsageOverview getInstance()
 * @author Solvr AS <utviklere@solvr.no>
 * @copyright Solvr AS
 * @license MIT
 * @property-read FileUsageOverviewService $fileUsageOverviewService
 * @property-read AssetUsageService $assetUsageService
 */
class FileUsageOverview extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => ['assetUsageService' => AssetUsageService::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Register Components (Services)
        $this->setComponents([
            'assetUsageService' => AssetUsageService::class,
        ]);

        $this->registerTableAttributes();
        $this->attachEventHandlers();
        $this->registerTemplate();
    }

    /**
     * Attach event handlers
     */
    private function attachEventHandlers(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function ($event) {
                $element = $event->element;
                if ($element instanceof Entry) {
                    $entryId = $element->canonicalId;
                    $redactorFields = $this->findCkEditorFields($element);
                    foreach ($redactorFields as $field) {
                        $content = $element->{$field->handle}->getRawContent();
                        $results = $this->extractIds($content);
                        if (!empty($results)) {
                            foreach ($results as $key => $result) {
                                $this->assetUsageService->saveRedactorRelation($entryId, $result['assetId'], $result['siteId']);
                            }
                        }
                    }
                }
            }
        );
    }

    /**
     * Register table attributes
     */
    private function registerTableAttributes()
    {
        Event::on(Asset::class, Asset::EVENT_REGISTER_TABLE_ATTRIBUTES, function (RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['usage'] = [
                'label' => Craft::t('file-usage-overview', 'Usage'),
            ];
        });

        Event::on(Asset::class, Asset::EVENT_DEFINE_ATTRIBUTE_HTML, function (DefineAttributeHtmlEvent $event) {
            if ($event->attribute === 'usage') {
                /** @var Asset $asset */
                $asset = $event->sender;

                $event->html = $this->assetUsageService->getAssetUsageCount($asset->id);
                $event->handled = true;
            }
        });
    }

    /**
     * Find all Redactor fields in an entry
     * @param Entry $entry
     * @return array
     */
    public static function findCkEditorFields(Entry $entry): array
    {
        $ckeditorFields = [];
        $fieldLayout = $entry->getFieldLayout();

        foreach ($fieldLayout->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof CustomField) {
                    $field = $element->getField();
                    if ($field instanceof CkEditor) {
                        $ckeditorFields[] = $field;
                    }
                }
            }
        }

        return $ckeditorFields;
    }

    /**
     * Extract asset IDs from Redactor content
     * @param string $ckEditorContent
     * @return array
     */
    public static function extractIds(string $ckEditorContent): array
    {
        $assetIds = [];
        $siteIds = [];
        preg_match_all('/{asset:(\d+)@(\d+):url\|\|([^}]+)}/', $ckEditorContent, $matches);

        if (!empty($matches[1])) {
            $assetIds = array_map('intval', $matches[1]);
            $siteIds = array_map('intval', $matches[2]);
        }

        $result = [];
        foreach ($assetIds as $index => $assetId) {
            $result[] = ['assetId' => $assetId, 'siteId' => $siteIds[$index]];
        }

        return $result;
    }

    private function registerTemplate()
    {
        Event::on(
            ElementsController::class,
            ElementsController::EVENT_DEFINE_EDITOR_CONTENT,
            function (DefineElementEditorHtmlEvent $event) {
                if ($event->element instanceof Asset) {
                    /** @var Asset */
                    $asset = $event->element;
                    $event->html .= Craft::$app->getView()->renderTemplate('file-usage-overview/_asset-usage-details', [
                        'elements' => $this->assetUsageService->getUsedIn($asset),
                    ]);
                }
            });

    }
}