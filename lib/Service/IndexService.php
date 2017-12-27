<?php
/**
 * FullNextSearch_ElasticSearch - Index with ElasticSearch
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FullNextSearch_ElasticSearch\Service;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use OCA\FullNextSearch\INextSearchPlatform;
use OCA\FullNextSearch\INextSearchProvider;
use OCA\FullNextSearch\Model\Index;
use OCA\FullNextSearch\Model\IndexDocument;
use OCA\FullNextSearch_ElasticSearch\Exceptions\ConfigurationException;

class IndexService {


	/** @var IndexMappingService */
	private $indexMappingService;

	/** @var MiscService */
	private $miscService;


	/**
	 * IndexService constructor.
	 *
	 * @param IndexMappingService $indexMappingService
	 * @param MiscService $miscService
	 */
	public function __construct(
		IndexMappingService $indexMappingService, MiscService $miscService
	) {
		$this->indexMappingService = $indexMappingService;
		$this->miscService = $miscService;
	}


	/**
	 * @param Client $client
	 *
	 * @throws ConfigurationException
	 */
	public function initializeIndex(Client $client) {
		try {
			if (!$client->indices()
						->exists($this->indexMappingService->generateGlobalMap(false))) {

				$client->indices()
					   ->create($this->indexMappingService->generateGlobalMap());
				$client->ingest()
					   ->putPipeline($this->indexMappingService->generateGlobalIngest());

			}
		} catch (BadRequest400Exception $e) {
			throw new ConfigurationException(
				'Check your user/password and the index assigned to that cloud'
			);
		}
	}


	/**
	 * @param Client $client
	 *
	 * @throws ConfigurationException
	 */
	public function removeIndex(Client $client) {
		try {
			$client->ingest()
				   ->deletePipeline($this->indexMappingService->generateGlobalIngest(false));
		} catch (Missing404Exception $e) {
			/* 404Exception will means that the mapping for that provider does not exist */
		}

		try {
			$client->indices()
				   ->delete($this->indexMappingService->generateGlobalMap(false));
		} catch (Missing404Exception $e) {
			/* 404Exception will means that the mapping for that provider does not exist */
		}
	}


	/**
	 * @param INextSearchPlatform $platform
	 * @param Client $client
	 * @param INextSearchProvider $provider
	 * @param IndexDocument $document
	 *
	 * @return array
	 * @throws ConfigurationException
	 */
	public function indexDocument(
		INextSearchPlatform $platform, Client $client, INextSearchProvider $provider,
		IndexDocument $document
	) {
		$index = $document->getIndex();
		if ($index->isStatus(Index::STATUS_REMOVE_DOCUMENT)) {
			$result =
				$this->indexMappingService->indexDocumentRemove($client, $provider, $document);
		} else if ($index->isStatus(Index::STATUS_INDEX_DONE)) {
			$result = $this->indexMappingService->indexDocumentUpdate(
				$client, $provider, $document, $platform
			);
		} else {
			$result = $this->indexMappingService->indexDocumentNew(
				$client, $provider, $document, $platform
			);
		}


		return $result;
	}


	/**
	 * @param Index $index
	 * @param array $result
	 *
	 * @return Index
	 */
	public function parseIndexResult(Index $index, array $result) {

		if ($index->isStatus(Index::STATUS_REMOVE_DOCUMENT)) {
			$index->setStatus(Index::STATUS_DOCUMENT_REMOVED);

			return $index;
		}

		// TODO: parse result
		$index->setLastIndex();
		$index->setStatus(Index::STATUS_INDEX_DONE, true);

		return $index;
	}


}