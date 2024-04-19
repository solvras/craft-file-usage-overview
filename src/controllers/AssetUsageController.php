<?php

namespace solvras\craftcraftfileusageoverview\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use solvras\craftcraftfileusageoverview\services\AssetUsageService;

/**
 * Asset Usage controller
 */
class AssetUsageController extends Controller
{
    public $defaultAction = 'index';
    protected $allowAnonymous = ['index', 'categories'];

    /**
     * file-usage-overview/asset-usage action
     */
    public function actionIndex(): Response
    {
        $assetUsageService = new AssetUsageService();
        $assetUsage = $assetUsageService->fetchFileLists();

        return $this->asJson($assetUsage);
    }

    public function actionCategories(): Response
    {
        $assetUsageService = new AssetUsageService();
        $assetUsage = $assetUsageService->fetchCategories();

        return $this->asJson($assetUsage);
    }
}
