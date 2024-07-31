<?php

class User extends Controller {
	function getKey(): void {
		//
		$this->errorAndDie( 401 );
	}
}