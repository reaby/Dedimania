<?php

namespace ManiaLivePlugins\Reaby\Dedimania\Structures;

class DediRecord extends \DedicatedApi\Structures\AbstractStructure {

    public $login;
    public $time;
    public $nickname;
    public $place = -1;
    public $checkpoints = array();

    public function __construct($login, $nickname, $time, $place = -1, $checkpoints = array()) {
        $this->login = $login;
        $this->time = $time;
        $this->nickname = $nickname;
        $this->place = $place;
        $this->checkpoints = $checkpoints;
    }

}

?>
