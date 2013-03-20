<?php

namespace ManiaLivePlugins\Reaby\Dedimania;

class Config extends \ManiaLib\Utils\Singleton {

    public $login;
    public $code;
    public $disableMessages = false;
    public $newRecordMsg = '%1$s $0b3claimed the$ff0 %2$s $0b3. Dedimania Record!  time:$ff0%3$s';
    public $recordMsg = '%1$s $0b3secured the$ff0 %2$s $0b3. Dedimania Record!  time:$ff0%3$s $0b3$n($ff0 %4$s $0b3)!';
    public $upgradeMsg = '$l[http://dedimania.net/tm2stats/?do=donation]Click here to upgrade your dedimania account.$l';

}

?>
