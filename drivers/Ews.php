<?php
namespace FreePBX\modules\Calendar\drivers;
use FreePBX\modules\Calendar\drivers\Ews\Calendar as EWSCalendar;
use FreePBX\modules\Calendar\IcalParser\IcalRangedParser;
use Ramsey\Uuid\Uuid;
class Ews extends Base {
	public $driver = 'Ews';

	/**
	 * Get Information about this driver
	 * @method getInfo
	 * @return array  array of information
	 */
	public function getInfo() {
		return array(
			"name" => _("Remote Outlook Calendar")
		);
	}

	/**
	 * Update calendar by uuid
	 * @method updateCalendar
	 * @param  string         $id   The uuid to update
	 * @param  array         $data Array of data about this calendar
	 * @return boolean               true or false
	 */
	public function updateCalendar($id,$data) {
		if(empty($id)) {
			throw new \Exception("Calendar ID is empty");
		}
		if(!class_exists('SoapClient')) {
			return false;
		}
		$calendar = array(
			"name" => $data['name'],
			"description" => $data['description'],
			"type" => "ews",
			"email" => $data['email'],
			"version" => $data['version'],
			"url" => $data['url'],
			"username" => $data['username'],
			"password" => $data['password'],
			"calendars" => !empty($data['calendars']) ? $data['calendars'] : array(),
			"next" => !empty($data['next']) ? $data['next'] : 300
		);
		$this->calendar->setConfig($id,$calendar,'calendars');
		$calendar['id'] = $id;
		return $this->processCalendar($calendar);
	}

	/**
	 * Get the "Add" display
	 * @method getAddDisplay
	 * @return string              HTML to display
	 */
	public function getAddDisplay() {
		if(!class_exists('SoapClient')) {
			return _("You are missing the PHP SoapClient library. Please install to continue");
		}
		return load_view(dirname(__DIR__)."/views/remote_ews_settings.php",array('action' => 'add', 'calendars' => array(), 'data' => array('next' => 86400)));
	}

	/**
	 * Get the "update" display
	 * @method getEditDisplay
	 * @param  array         $data Array of calendar information
	 * @return string               HTML to display
	 */
	public function getEditDisplay($data) {
		if(!class_exists('SoapClient')) {
			return _("You are missing the PHP SoapClient library. Please install to continue");
		}
		$server = $data['url'];
		$username = $data['username'];
		$password = $data['password'];
		$version = constant('\jamesiarmes\PhpEws\Client::'.$data['version']);
		$ews = new EWSCalendar($server, $username, $password, $version);
		$calendars = array();
		foreach($ews->getAllCalendars() as $calendar) {
			$id = $calendar['id'];
			$calendars[$id] = array(
				"id" => $id,
				"name" => $calendar['name'],
				"selected" => in_array($id,$data['calendars'])
			);
		}
		return load_view(dirname(__DIR__)."/views/remote_ews_settings.php",array('action' => 'edit', 'data' => $data, 'calendars' => $calendars));
	}

	/**
	 * Process Calendar (Updating)
	 * @method processCalendar
	 * @param  array          $calendar Array of calendar information
	 * @return boolean                    true or false
	 */
	public function processCalendar($calendar) {
		if(!class_exists('SoapClient')) {
			return false;
		}
		$server = $calendar['url'];
		$username = $calendar['username'];
		$password = $calendar['password'];
		$version = constant('\jamesiarmes\PhpEws\Client::'.$calendar['version']);
		$ews = new EWSCalendar($server, $username, $password, $version);
		$cals = $ews->getAllCalendars();
		foreach($calendar['calendars'] as $c) {
			if(isset($cals[$c])) {
				$events = $ews->getAllEventsByCalendarID($c);
				$cal = new IcalRangedParser();
				$cal->setStartRange(new \DateTime());
				$end = new \DateTime();
				$end->add(new \DateInterval('P2M'));
				$cal->setEndRange($end);
				$finalical = $ews->formatiCal($events);
				$cal->parseString($finalical);
				$this->calendar->processiCalEvents($calendar['id'], $cal, $finalical); //will ids clash? they shouldnt????
				$this->saveiCal($calendar['id'],$finalical);
			}
		}
		return true;
	}
}
