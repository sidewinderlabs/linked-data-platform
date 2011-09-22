<?php

interface SearchAdapter {

	// Input methods
	public function getQuery($exclude);
	public function search($query, $start, $rows);

	// Output methods
	public function getNumResults();
	public function getSingleResult();
	public function getResults();
	public function getAggregateResult($group);

}
