<?php

/**
 * @package plugins.beacon
 * @subpackage model
 */

class kBeacon
{
	const BEACONS_QUEUE_NAME					= 'beacons';
	const BEACONS_EXCHANGE_NAME					= 'beacon_exchange';
	
	const FIELD_ACTION 							= '_action';
	const FIELD_INDEX							= '_index';
	const FIELD_TYPE							= '_type';
	const FIELD_DOCUMENT_ID 					= '_id';
	const FIELD_DOCUMENT_TTL 					= 'ttl';
	
	const FIELD_ACTION_VALUE					= 'index';
	const FIELD_INDEX_VALUE						= 'beaconindex';
	const FIELD_TYPE_VALUE_STATS				= 'State';
	const FIELD_TYPE_VALUE_LOG					= 'Log';
	
	const FIELD_RELATED_OBJECT_TYPE				= 'relatedObjectType';
	const FIELD_EVENT_TYPE						= 'eventType';
	const FIELD_OBJECT_ID						= 'objectId';
	const FIELD_PRIVATE_DATA					= 'privateData';
	const FIELD_PARTNER_ID						= 'partnerId';
	
	protected $relatedObjectType;
	protected $eventType;
	protected $objectId;
	protected $privateData;
	protected $partnerId;

	public function __construct()
	{
		$this->setPartnerId(kCurrentContext::getCurrentPartnerId());
	}
	
	public function setRelatedObjectType($relatedObjectType)	{ $this->relatedObjectType = $relatedObjectType; }
	public function setEventType($eventType)					{ $this->eventType = $eventType; }
	public function setObjectId($objectId)						{ $this->objectId = $objectId; }	
	public function setPrivateData($privateData)				{ $this->privateData = $privateData; }
	public function setPartnerId($partnerId)					{ $this->partnerId = $partnerId; }
	
	public function getRelatedObjectType()						{ return $this->relatedObjectType; }
	public function getEventType()								{ return $this->eventType; }
	public function getObjectId()								{ return $this->objectId; }
	public function getPrivateData()							{ return $this->privateData; }
	public function getPartnerId()								{ return $this->partnerId; }
	
	public function index($shouldLog = false, $ttl = 3600)
	{
		// get instance of activated queue provider to send message
		$constructorArgs = array();
		$constructorArgs['exchangeName'] = self::BEACONS_EXCHANGE_NAME;
		
		/* @var $queueProvider RabbitMQProvider */
		$queueProvider = QueueProvider::getInstance(null, $constructorArgs);
		
		//Create base object for index
		$indexBaseObject = $this->createIndexBaseObject();
		
		//Modify base object to index to State 
		$stateIndexObjectJson = $this->getIndexObjectForState($indexBaseObject);
		$queueProvider->send(self::BEACONS_QUEUE_NAME, $stateIndexObjectJson);
		
		//Sent to log index of requested
		if($shouldLog)
		{
			$logIndexObjectJson = $this->getIndexObjectForLog($indexBaseObject, $ttl);
			$queueProvider->send(self::BEACONS_QUEUE_NAME, $logIndexObjectJson);
		}
	}
	
	public function getIndexObjectForState($indexObject)
	{
		$indexObject[self::FIELD_TYPE] = self::FIELD_TYPE_VALUE_STATS;
		return json_encode($indexObject);
	}
	
	public function getIndexObjectForLog($indexObject, $ttl)
	{
		$indexObject[self::FIELD_TYPE] = self::FIELD_TYPE_VALUE_LOG;
		$indexObject[self::FIELD_DOCUMENT_TTL] = $ttl . "S";
		return json_encode($indexObject);
	}
	
	private function createIndexBaseObject()
	{
		$indexObject = array();
		
		//Set Action Name and Index Name and calculated docuemtn id
		$indexObject[self::FIELD_ACTION] = self::FIELD_ACTION_VALUE;
		$indexObject[self::FIELD_INDEX] = self::FIELD_INDEX_VALUE;
		$indexObject[self::FIELD_DOCUMENT_ID] = md5($this->relatedObjectType.'_'. $this->eventType.'_'.$this->objectId);
		
		//Set values provided in input
		$indexObject[self::FIELD_RELATED_OBJECT_TYPE] = $this->relatedObjectType;
		$indexObject[self::FIELD_EVENT_TYPE] = $this->eventType;
		$indexObject[self::FIELD_OBJECT_ID] = $this->objectId;
		$indexObject[self::FIELD_PRIVATE_DATA] = $this->privateData;
		$indexObject[self::FIELD_PARTNER_ID] = $this->partnerId;
		
		return $indexObject;
	}
}
