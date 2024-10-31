<?php 
/**
 * @package  EitagcrPlugin
 */
namespace SementesCasGcrs\Api\Callbacks;
use DateTime;
use DateTimeZone;

class CycleCallbacks
{

	public function create_date_from_any_format( $date ) {
		$dt = DateTime::createFromFormat('d/m/Y H:i', $date, new DateTimeZone( 'America/Sao_Paulo' ));		
		if (!$dt || $dt->format('d/m/Y H:i') !== $date) {
			$dt = DateTime::createFromFormat('Y/m/d H:i', $date, new DateTimeZone( 'America/Sao_Paulo' ));
		}
		return $dt;
	}
	
}