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
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\controllers\ElementsController;
use craft\elements\Entry;
use craft\services\Elements;
use craft\redactor\Field as Redactor;
use craft\fields\Matrix;
use craft\web\View;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterTemplateRootsEvent;

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
    public $schemaVersion = '1.0.0';

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

                // Check if the element is an entry
                if ($element instanceof Entry) {
                    $entryId = $element->canonicalId;

                    // Fetch all Redactor fields in the entry
                    $redactorFields = $this->findRedactorFields($element);
                    foreach ($redactorFields as $field) {
                        if (!empty($field->text) && is_object($field->text)) {
                            $results = $this->extractIds($field->text->getRawContent());
                        }

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

        Event::on(Asset::class, Asset::EVENT_SET_TABLE_ATTRIBUTE_HTML, function (SetElementTableAttributeHtmlEvent $event) {
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
    public static function findRedactorFields(Entry $entry): array
    {
        $redactorFields = [];
        $fieldLayout = $entry->getFieldLayout();

        // Loop through all fields in the field layout
        foreach ($fieldLayout->getFields() as $field) {
            if ($field instanceof Redactor) {
                $redactorFields[] = $field;
            } elseif ($field instanceof Matrix) {
                $blocks = $entry->getFieldValue($field->handle)->all();
                
                foreach ($blocks as $block) {
                    $redactorFields[] = $block;
                }
            }
        }

        return $redactorFields;
    }

    /**
     * Extract asset IDs from Redactor content
     * @param string $redactorContent
     * @return array
     */
    public static function extractIds(string $redactorContent): array
    {
        $assetIds = [];
        $siteIds = [];
        preg_match_all('/{asset:(\d+)@(\d+):url\|\|([^}]+)}/', $redactorContent, $matches);

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
            View::class, 
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, 
            function (RegisterTemplateRootsEvent $event) {
                if (is_dir($baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates')) {
                    $event->roots['file-usage-overview'] = $baseDir;
                }
            }
        );

        Craft::$app->getView()->hook('cp.assets.edit.content', function(array &$context) {
            $templatePath = 'file-usage-overview/_asset-usage-details';
            $elements = $this->assetUsageService->getUsedIn($context['element']);
            $html = Craft::$app->view->renderTemplate(
                $templatePath, ['elements' => $elements], Craft::$app->view::TEMPLATE_MODE_CP
            );

            return $html;
        });
    }
}