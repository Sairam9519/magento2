<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MediaContentCatalog\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\MediaContentApi\Model\GetAssetIdByContentFieldInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;

/**
 * Class responsible to return Asset id by content field
 */
class GetAssetIdByCategoryStore implements GetAssetIdByContentFieldInterface
{
    private const TABLE_CONTENT_ASSET = 'media_content_asset';

    /**
     * @var ResourceConnection
     */
    private $connection;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var GroupRepositoryInterface
     */
    private $storeGroupRepository;

    /**
     * @var string
     */
    private $entityType;

    /**
     * GetAssetIdByProductStore constructor.
     *
     * @param ResourceConnection $resource
     * @param StoreRepositoryInterface $storeRepository
     * @param GroupRepositoryInterface $storeGroupRepository
     */
    public function __construct(
        ResourceConnection $resource,
        StoreRepositoryInterface $storeRepository,
        GroupRepositoryInterface $storeGroupRepository
    ) {
        $this->connection = $resource;
        $this->storeRepository = $storeRepository;
        $this->storeGroupRepository = $storeGroupRepository;
        $this->entityType = 'catalog_category';
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $value): array
    {
        $storeView = $this->storeRepository->getById($value);
        $storeGroup = $this->storeGroupRepository->get($storeView->getStoreGroupId());
        $categoryIds = $this->getCategoryIdsByRootCategory($storeGroup->getRootCategoryId());
        $sql = $this->connection->getConnection()->select()->from(
            ['asset_content_table' => $this->connection->getTableName(self::TABLE_CONTENT_ASSET)],
            ['asset_id']
        )->where(
            'entity_type = ?',
            $this->entityType
        )->where(
            'entity_id IN (?)',
            $categoryIds
        );

        $result = $this->connection->getConnection()->fetchAll($sql);

        return array_map(function ($item) {
            return $item['asset_id'];
        }, $result);
    }

    private function getCategoryIdsByRootCategory($rootCategoryId)
    {
        $contentCategoriesSql = $this->connection->getConnection()->select()->from(
            ['asset_content_table' => $this->connection->getTableName(self::TABLE_CONTENT_ASSET)],
            ['entity_id']
        )->where(
            'entity_type = ?',
            $this->entityType
        )->joinInner(
            ['category_table' => $this->connection->getTableName('catalog_category_entity')],
            'asset_content_table.entity_id = category_table.entity_id',
            ['path']
        );

        $result = $this->connection->getConnection()->fetchAll($contentCategoriesSql);

        $result = array_filter($result, function ($item) use ($rootCategoryId) {
            $pathArray = explode("/", $item['path']);
            $isInPath = false;
            foreach ($pathArray as $id) {
                if ($id == $rootCategoryId) {
                    $isInPath = true;
                }
            }
            return  $isInPath;
        });

        return array_map(function ($item) {
            return $item['entity_id'];
        }, $result);
    }
}
