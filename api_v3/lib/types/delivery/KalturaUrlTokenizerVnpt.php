<?php
/**
 * @package api
 * @subpackage objects
 */
class KalturaUrlTokenizerVnpt extends KalturaUrlTokenizer {

	/**
	 * @var string
	 */
	public $tokenKey;

	/**
	 * @var int
	 */
	public $expiryTimeFrame;
	
	/**
	 * @var int
	 */
	public $tokenizationFormat;

	private static $map_between_objects = array
	(
			"tokenKey",
			"expiryTimeFrame",
			"tokenizationFormat",
	);
	
	public function getMapBetweenObjects ( )
	{
		return array_merge ( parent::getMapBetweenObjects() , self::$map_between_objects );
	}
	
	public function toObject($dbObject = null, $skip = array())
	{
		if (is_null($dbObject))
			$dbObject = new kVnptUrlTokenizer();

		parent::toObject($dbObject, $skip);
	
		return $dbObject;
	}
}
