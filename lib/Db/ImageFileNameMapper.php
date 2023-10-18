<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Text2ImageHelper\Db;

use DateTime;
use OCA\Text2ImageHelper\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ImageFileNameMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'text2image_helper_file_name', ImageFileName::class);
	}

	/**
	 * @param int $imageId
	 * @return array|Entity
	 * @throws Exception
	 */
	public function getImageFileNamesOfImage(int $imageId): array
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('image_id', $qb->createNamedParameter($imageId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $imageId
	 * @param int $fileNameId
	 * @return ImageFileName
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function getImageFileNameOfImageId(int $imageId): ImageFileName
	{
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('image_id', $qb->createNamedParameter($imageId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $imageId
	 * @param string $fileName
	 * @return ImageFileName
	 * @throws Exception
	 */
	public function createImageFileName(int $imageId, string $fileName): ImageFileName
	{
		$imageFileName = new ImageFileName();
		$imageFileName->setImageId($imageId);
		$imageFileName->setFileName($fileName);
		return $this->insert($imageFileName);
	}

	/**
	 * @param int $imageId
	 * @return void
	 * @throws Exception
	 */
	public function deleteImageFileNames(int $imageId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('text2image_helper_file_name')
			->where(
				$qb->expr()->eq('image_id', $qb->createNamedParameter($imageId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * @param int $maxAge
	 * @return array # list of file names
	 * @throws Exception
	 */
	public function cleanupFileNames(int $maxAge = Application::DEFAULT_MAX_IMAGE_IDLE_TIME): array
	{
		$ts = (new DateTime())->getTimestamp();
		$maxTimestamp = $ts - $maxAge;

		$qb = $this->db->getQueryBuilder();

        $qb->select('id')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_INT))
            );

        $fileNames = $this->findEntities($qb);
        $fileNames = array_map(function($fileName) {
            return $fileName->getFileName();
        }, $fileNames);

        # Delete the database entries
        $qb->resetQueryParts();
        $qb->delete('text2image_helper_file_name')
            ->where(
                $qb->expr()->lt('timestamp', $qb->createNamedParameter($maxTimestamp, IQueryBuilder::PARAM_INT))
            );
        $qb->executeStatement();
        $qb->resetQueryParts();

		return $fileNames;
	}
}