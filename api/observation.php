<?php

// load Solr search adapter
require_once './adapters/search.php';
require_once './adapters/search/solr.php';

/**
 * Observation resource
 * @uri /observation
 */
class ObservationResource extends Resource {

	/**
	 * Config
	 */
	private $config = array(
		// Default number of rows returned
		'default_rows'	=> 10,

		// Maximum number of rows returned
		'max_rows'		=> 10000,
	);

	/**
	 * Constants
	 */
	const GROUP_TYPE_MEASURE = 0x01;
	const GROUP_TYPE_DIMENSION = 0x02;

	/**
	 * Members
	 */
	protected $adapter;

	/**
	 * Initialise connection to Solr
	 */
    public function  __construct($parameters) {

		parent::__construct($parameters);

		$this->adapter = new SearchAdapterSolr();

	}

	/**
	 * Handle all GET requests
	 */
    public function get($request) {

		// Build search query
		$query = $this->adapter->getQuery(array('start', 'rows', 'group'));

		// Get field to group on
		$group = (empty($_GET['group'])) ? null : $_GET['group'];

		// Ignore pagination parameters if we're grouping
		if (!is_null($group)) {

			$start = 0;
			$rows = $this->config['max_rows'];

		// Get pagination parameters
		} else {

			$start = $this->getUnsignedParam('start', 0);
			$rows = $this->getUnsignedParam('rows', $this->config['default_rows']);

			// Limit number of rows that can be requested
			if ($rows > $this->config['max_rows']) {
				$rows = $this->config['default_rows'];
			}

		}

		// Run search
		$this->adapter->search($query, $start, $rows);

		/**
		 * Process search results
		 */
		$total = $this->adapter->getNumResults();

		if ($total == 1) {

			// Return single results as a single resource
			$data = $this->adapter->getSingleResult();

		} elseif (!is_null($group) && $total > 0) {

			// Return an aggregate resource if we're grouping
			$data = $this->adapter->getAggregateResult($group);
			$data['total'] = $total;

		} else {

			// Return multiple resources as a list
			$data = $this->adapter->getResults();
			$data['total']	= $total;
			$data['start']	= $start;
			$data['end']	= min($rows, $total);

		}

		// Build response
		$response = new Response($request);
		$response->code = Response::OK;
		$response->addHeader('Content-type', 'application/json');
		$response->body = json_encode($data);

		return $response;

    }

	/**
	 * Helper method to get an unsigned integer GET value
	 * If the value doesn't exist or isn't valid return the default
	 */
	protected function getUnsignedParam($key, $default) {

		if (isset($_GET[$key]) && is_numeric($_GET[$key]) && $_GET[$key] >= 0) {
			return (int)$_GET[$key];
		}

		return $default;

	}

}

