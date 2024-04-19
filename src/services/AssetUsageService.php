<?php

namespace solvras\craftcraftfileusageoverview\services;

use Craft;
use craft\elements\Entry;
use craft\elements\Asset;
use yii\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ElementHelper;
use solvras\craftcraftfileusageoverview\records\AssetsUsage;

/**
 * Asset Usage Service service
 */
class AssetUsageService extends Component
{
    /**
     * Get asset usage
     *
     * @param int $assetId
     * @return array
     */
    public function getAssetsWithUsage() : array
    {
        $assetList = [];
        $assets= Asset::find()->all();
        foreach ($assets as $asset) {
            $assetInfo = [
                'assetId' => $asset->id,
                'assetUrl' => $asset->url,
                'assetTitle' => $asset->title,
                'usedIn' => $this->getAssetUsageCount($asset->id)
            ];
            $assetList[] = $assetInfo;
        }

        return $assetList;
    }

    /**
     * Get the relations for an asset
     * @param int $asset
     * @return array
     */
    private function queryRelations(int $assetId): array
    {
        return (new Query())
            ->select(['sourceId as id', 'sourceSiteId as siteId'])
            ->from(Table::RELATIONS)
            ->leftJoin(Table::ELEMENTS, 'elements.id = relations.sourceId')
            ->where([
                'targetId' => $assetId,
                'elements.canonicalId' => null,
            ])
            ->all();
    }

    /**
     * Get the relations for an asset used in a redactor field
     * @param int $entryId
     * @return array
     */
    private function queryRedactorRelations(int $assetId): array
    {
        return (new Query())
            ->select(['entryId as id', 'entrySiteId as siteId'])
            ->from('{{%file-usage-overview_redactor-relations}}')
            ->where(['assetId' => $assetId])
            ->all();
    }

    /**
     * Get the entries that use an asset
     * @param Asset $asset
     * @return array
     */
    public function getUsedIn(Asset $asset): array
    {
        $assetId = $asset->id;
    
        try {
            $allRelations = array_merge($this->queryRelations($assetId), $this->queryRedactorRelations($assetId));
    
            $elements = [];
    
            foreach ($allRelations as $relation) {
                $element = Craft::$app->elements->getElementById($relation['id'], null, $relation['siteId']);
                if ($element) {
                    $entry = ElementHelper::rootElement($element);
                    $isRevision = $this->isDraftOrRevision($entry);
                    if($entry && !$isRevision && $entry->enabled) {
                        $entryId = $entry->id;
                        if (!isset($elements[$entryId])) {
                            $elements[$entryId] = [
                                'entryId' => $entryId,
                                'elementTitle' => $entry->title,
                                'elementUrl' => $entry->url,
                                'cpEditUrl' => $entry->getCpEditUrl(),
                                'count' => 1
                            ];
                        } else {
                            $elements[$entryId]['count']++;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        
        return array_values($elements);
    }
    
    

    /**
     * Save entryId and asset Id to database
     * @param int $entryId
     * @param int $assetId
     * @param int $siteId
     * @return void
     */
    public function saveRedactorRelation(
        int $entryId,
        int $assetId,
        int $siteId
    ) : void {
        $existingRecord = AssetsUsage::find()
            ->where([
                'entryId' => $entryId,
                'assetId' => $assetId
            ])
            ->one();
        if ($existingRecord !== null) {
            $existingRecord->entryId = $entryId;
            $existingRecord->assetId = $assetId;
            $existingRecord->entrySiteId = $siteId;
            $existingRecord->update();
        } else {
            $redactorRelationsRecord = new AssetsUsage();
            $redactorRelationsRecord->assetId = $assetId;
            $redactorRelationsRecord->entryId = $entryId;
            $redactorRelationsRecord->entrySiteId = $siteId;
            $redactorRelationsRecord->save();
        }
    }

    /**
     * Fetch file lists
     * @return array
     */
    public function fetchFileLists(): array
    {
        $entries = Entry::find()
            ->section('*')
            ->with(['contentMatrix'])
            ->all();
        $files = [];

        foreach ($entries as $entry) {
            $blocks = $entry->contentMatrix;
            if ($blocks) {
                foreach ($blocks as $block) {
                    if ($block->getType()->handle === 'filesList') {
                        $fileAssets = $block->fileAssets;
                        foreach ($fileAssets as $fileAsset) {

                            $documentCategories = $fileAsset->documentCategory->all();
                            $documentCategoryTitles = [];
                            foreach ($documentCategories as $category) {
                                $documentCategoryTitles[] = [
                                    'id' => $category->id,
                                    'title' => $category->title
                                ];
                            }

                            $files[] = [
                                'entryId' => $entry->id,
                                'entryTitle' => $entry->title,
                                'fileTitle' => $fileAsset->title,
                                'sectionHandle' => $entry->section->handle,
                                'documentName' => $fileAsset->filename,
                                'documentUrl' => $fileAsset->url,
                                'documentExtension' => $fileAsset->getExtension(),
                                'documentSize' => $fileAsset->size,
                                'dateChanged' => $fileAsset->dateModified->format('Y-m-d H:i:s'),
                                'documentCategory' => $documentCategoryTitles ?: null,                      
                            ];
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Get count of assets used in entries
     * @param int $asset
     * @return string
     */
    public function getAssetUsageCount(int $assetId): string
    {
        try {
            $allRelations = array_merge($this->queryRelations($assetId), $this->queryRedactorRelations($assetId));
            $count = 0;
    
            foreach ($allRelations as $relation) {
                $element = Craft::$app->elements->getElementById($relation['id'], null, $relation['siteId']);
                if ($element) {
                    $entry = ElementHelper::rootElement($element);
                    $isRevision = $this->isDraftOrRevision($entry);
                    if (!$isRevision && $entry->enabled) {
                        $count++;
                    }
                }
            }
    
            return $count !== 0 ? ($count === 1 ? 'Used 1 time' : "Used $count times") : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Check if an entry is a draft or revision
     * @param Entry $entry
     * @return bool
     */
    private function isDraftOrRevision(Entry $entry): bool
    {
        return $entry->getIsDraft() || $entry->getIsRevision();
    }

    public function fetchCategories(): array
    {
        $groupCategory = (new Query())
            ->select(['id', 'handle'])
            ->from('{{%categorygroups}}')
            ->where(['handle' => 'documentCategories'])
            ->one();
        
        $categories = (new Query())
            ->select(
                [
                    'categories.id',
                    'content.title AS categoryTitle', 
                    'COUNT(files.id) AS fileCount'
                ]
            )
            ->from('{{%categories}} AS categories')
            ->innerJoin('{{%elements}} AS elements', 'categories.id = elements.id')
            ->innerJoin('{{%content}} AS content', 'elements.id = content.elementId')
            ->leftJoin('{{%relations}} AS relations', 'relations.targetId = elements.id')
            ->leftJoin('{{%assets}} AS files', 'relations.sourceId = files.id')
            ->where(['categories.groupId' => $groupCategory['id']])
            ->groupBy('categories.id')
            ->all();
        
        return $categories;
    }
}
