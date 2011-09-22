<?php

// load SolrPhpClient library
require_once './lib/Apache/Solr/Service.php';

class SearchAdapterSolr implements SearchAdapter {

	/**
	 * Config
	 */
	private $config = array(
		'host'			=> 'localhost',
		'port'			=> 8983,
		'path'			=> '/solr',
	);

	/**
	 * Members
	 */
	private $solr;
	private $results;

	/**
	 * Initialise connection to Solr
	 */
    public function  __construct() {

		$this->solr = new Apache_Solr_Service(
			$this->config['host'],
			$this->config['port'],
			$this->config['path']
		);

		// Test we can connect to Solr
		if (!$this->solr->ping()) {
			throw new ResponseException('Could not connect to Solr server', Response::INTERNALSERVERERROR);
		}

	}

	/**
	 * Return GET search parameters in Lucene query syntax
	 */
	public function getQuery($exclude) {

		$params = array();

		foreach($_GET as $key => $value) {

			if (in_array($key, $exclude)) {
				continue;
			}

			$params[] = $key . ':' . $value;

		}

		if (count($params)) {
			$query = implode(' AND ', $params);
		} else {
			$query = '*:*';
		}

		return $query;

	}

	/**
	 * Execute search
	 */
	public function search($query, $start, $rows) {

		try {
			$this->results = $this->solr->search($query, $start, $rows);
		} catch (Exception $e) {
			throw new ResponseException('Solr failed with: "' . $e->getMessage() . '"', Response::INTERNALSERVERERROR);
		}

	}

	/**
	 * Get number of search results
	 */
	public function getNumResults() {
		return (int)$this->results->response->numFound;
	}

	/**
	 * Return a single observation
	 */
	public function getSingleResult() {
		return $this->getObservation($this->results->response->docs[0]);
	}

	/**
	 * Return a list of observations
	 */
	public function getResults() {

		$observations = array();
		foreach ($this->results->response->docs as $doc) {
			$observations[] = $this->getObservation($doc);
		}

		return array(
			'results' => $observations
		);

	}

	/**
	 * Return an aggregate result from a grouped query
	 */
	public function getAggregateResult($group) {

		// Check group field exists
		if (!isset($this->results->response->docs[0]->$group)) {
			throw new ResponseException('Unknown group field: ' . $group, Response::BADREQUEST);
		}

		$data = array();

		// Determine group field type
		if ($group == 'value') {

			$type = ObservationResource::GROUP_TYPE_MEASURE;

		} else {

			$type = ObservationResource::GROUP_TYPE_DIMENSION;
			$data['results'] = array();

		}

		foreach ($this->results->response->docs as $doc) {

			switch($type) {

				case ObservationResource::GROUP_TYPE_MEASURE:
					$data['value'] += $doc->value;
					break;

				case ObservationResource::GROUP_TYPE_DIMENSION:
					if (isset($data['results'][$doc->$group])) {
						$data['results'][$doc->$group] += $doc->value;
					} else {
						$data['results'][$doc->$group] = $doc->value;
					}
					break;

			}

		}

		return $data;

	}

	/**
	 * Helper method to create an array suitable for json_encode from
	 * an Apache_Solr_Document object
	 */
	private function getObservation($result) {

		$observation = array();
		foreach ($result as $field => $value) {

			// Ignore field names with underscores as these are assumed to
			// be fields on a separate object that have been flattened for Solr
			// e.g. only return "area" not "area_name", "area_location", etc
			if (strpos($field, '_') === false) {
				$observation[$field] = $value;
			}

		}

		return $observation;

	}

}

