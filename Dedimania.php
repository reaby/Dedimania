<?php

namespace ManiaLivePlugins\Reaby\Dedimania;

use ManiaLivePlugins\Reaby\Dedimania\Classes\Connection as DediConnection;
use ManiaLivePlugins\Reaby\Dedimania\Structures\Request;
use ManiaLivePlugins\Reaby\Dedimania\Events\Event as DediEvent;
use ManiaLivePlugins\Reaby\Dedimania\Config;
use \ManiaLive\Event\Dispatcher;

class Dedimania extends \ManiaLive\PluginHandler\Plugin implements \ManiaLivePlugins\Reaby\Dedimania\Events\Listener {

    /** @var DediConnection */
    private $dedimania;

    /** @var Config */
    private $config;
    private $records = array();
    private $rankings = array();
    private $vReplay = "";
    private $gReplay = "";
    private $lastRecord;
    private $recordCount = 15;

    public function onInit() {
        $this->setVersion(0.1);
        $this->config = Config::getInstance();
        Dispatcher::register(DediEvent::getClass(), $this);
        $this->setPublicMethod("disableMessages");
        $this->setPublicMethod("enableMessages");
    }

    public function onLoad() {
        $this->enableDedicatedEvents();
        $this->enableApplicationEvents();
        $this->dedimania = DediConnection::getInstance();
    }

    public function disableMessages($pluginId) {
        $this->config->disableMessages = true;
    }

    public function enableMessages($pluginId) {
        $this->config->disableMessages = false;
    }

    public function onReady() {
        $this->dedimania->openSession();
    }

    function checkSession($login) {
        $this->dedimania->checkSession();
    }

    public function onPlayerConnect($login, $isSpectator) {
        $player = $this->storage->getPlayerObject($login);
        $this->dedimania->playerConnect($player, $isSpectator);
    }

    public function onPlayerDisconnect($login) {
        $this->dedimania->playerDisconnect($login);
    }

    public function onBeginMap($map, $warmUp, $matchContinuation) {
        $this->records = array();
        $this->dedimania->getChallengeRecords();
        $this->rankings = array();
        $this->vReplay = "";
        $this->gReplay = "";
    }

    public function onPlayerFinish($playerUid, $login, $time) {
        if ($time == 0)
            return;
        if ($this->storage->currentMap->nbCheckpoints == 0)
            return;

        if (!array_key_exists($login, DediConnection::$players))
            return;


        if (DediConnection::$players[$login]->banned)
            return;

        $player = $this->storage->getPlayerObject($login);
        if (count($this->records) == 0) {
            $this->records[$login] = new Structures\DediRecord($login, $player->nickName, $time);
            $this->reArrage();
            \ManiaLive\Event\Dispatcher::dispatch(new DediEvent(DediEvent::ON_NEW_DEDI_RECORD, $this->records[$login]));
        }

        if (!is_object($this->lastRecord)) {
            echo "lastRecord not set";
            return;
        }

        // so if the time is better than the last entry or the count of records is less than 20...
        if ($this->lastRecord->time > $time || count($this->records) < DediConnection::$serverMaxRank) {
            // if player exists on the list... see if he got better time
            if (array_key_exists($login, $this->records)) {
                if ($this->records[$login]->time > $time) {
                    $oldRecord = $this->records[$login];
                    $this->records[$login] = new Structures\DediRecord($login, $player->nickName, $time);
                    $this->reArrage();
                    if (array_key_exists($login, $this->records)) // have to recheck if the player is still at the dedi array
                        \ManiaLive\Event\Dispatcher::dispatch(new DediEvent(DediEvent::ON_DEDI_RECORD, $this->records[$login], $oldRecord));
                    return;
                }
                // if not then just do a update for the time
            } else {
                $this->records[$login] = new Structures\DediRecord($login, $player->nickName, $time);
                $this->reArrage();
                if (array_key_exists($login, $this->records)) // have to recheck if the player is still at the dedi array
                    \ManiaLive\Event\Dispatcher::dispatch(new DediEvent(DediEvent::ON_NEW_DEDI_RECORD, $this->records[$login]));
                return;
            }
        }
    }

    function reArrage() {
        $this->sortAsc($this->records, "time");

        $i = 0;
        $newrecords = array();
        foreach ($this->records as $record) {
            if (array_key_exists($record->login, $newrecords))
                continue;
            $record->place = ++$i;
            if (array_key_exists($record->login, DediConnection::$players)) {
                if ($record->place < DediConnection::$players[$record->login]->maxRank) {
                    $newrecords[$record->login] = $record;
                }
            } else {
                $newrecords[$record->login] = $record;
            }
        }
        // assign  the new records
        $this->records = array_slice($newrecords, 0, DediConnection::$serverMaxRank);
        // assign the last place
        $this->lastRecord = end($this->records);

        // recreate new records entry for update_records
        $data = array('Records' => array());
        foreach ($this->records as $record) {
            $data['Records'][] = Array("Login" => $record->login, "NickName" => $record->nickname, "Best" => $record->time);
        }

        \ManiaLive\Event\Dispatcher::dispatch(new DediEvent(DediEvent::ON_UPDATE_RECORDS, $data));
    }

    private function sortAsc(&$array, $prop) {
        usort($array, function($a, $b) use ($prop) {
                    return $a->$prop > $b->$prop ? 1 : -1;
                });
    }

    public function onEndMap($rankings, $map, $wasWarmUp, $matchContinuesOnNextMap, $restartMap) {
        $this->dedimania->setChallengeTimes($map, $this->rankings, $this->vReplay, $this->gReplay);
        $this->dedimania->updateServerPlayers($map);
    }

    public function onEndMatch($rankings, $winnerTeamOrMap) {
        $this->rankings = $rankings;

        try {
            if (sizeof($rankings) == 0) {
                $this->vReplay = "";
                $this->gReplay = "";
                return;
            }
            $this->vReplay = $this->connection->getValidationReplay($rankings[0]['Login']);
            $greplay = "";
            $grfile = sprintf('Dedimania/%s.%d.%07d.%s.Replay.Gbx', $this->storage->currentMap->uId, $this->storage->gameInfos->gameMode, $rankings[0]['BestTime'], $rankings[0]['Login']);
            $this->connection->SaveBestGhostsReplay($rankings[0]['Login'], $grfile);
            $this->gReplay = file_get_contents($this->connection->gameDataDirectory() . 'Replays/' . $grfile);
        } catch (\Exception $e) {            
            $this->vReplay = "";
            $this->gReplay = "";
        }
    }

    public function onDedimaniaOpenSession() {
        $players = array();
        foreach ($this->storage->players as $player) {
            if ($player->login != $this->storage->serverLogin)
                $players[] = array($player, false);
        }
        foreach ($this->storage->spectators as $player)
            $players[] = array($player, true);

        $this->dedimania->playerMultiConnect($players);

        $this->dedimania->getChallengeRecords();
        $this->rankings = array();
    }

    public function onDedimaniaGetRecords($data) {
        $this->records = array();
        $this->recordCount = $data['ServerMaxRank'];

        foreach ($data['Records'] as $record) {
            $this->records[$record['Login']] = new Structures\DediRecord($record['Login'], $record['NickName'], $record['Best'], $record['Rank']);
        }
        $this->lastRecord = end($this->records);
    }

    public function onUnload() {
        $this->disableTickerEvent();
        $this->disableDedicatedEvents();
        parent::onUnload();
    }

    public function onDedimaniaUpdateRecords($data) {
        
    }

    /**
     * 
     * @param Structures\DediRecord $record     
     */
    public function onDedimaniaNewRecord($record) {
        try {
            if ($this->config->disableMessages == true)
                return;

            $message = sprintf($this->config->newRecordMsg, \ManiaLib\Utils\Formatting::stripCodes($record->nickname, "wos"), $record->place, \ManiaLive\Utilities\Time::fromTM($record->time));
            $this->connection->chatSendServerMessage($message);
        } catch (\Exception $e) {
            \ManiaLive\Utilities\Console::println("Error: couldn't show dedimania message" . $e->getMessage());
        }
    }

    /**
     * 
     * @param Structures\DediRecord $record
     * @param Structures\DediRecord $oldRecord     
     */
    public function onDedimaniaRecord($record, $oldRecord) {
        try {
            if ($this->config->disableMessages == true)
                return;
            $diff = \ManiaLive\Utilities\Time::fromTM($record->time - $oldRecord->time, true);
            $message = sprintf($this->config->recordMsg, \ManiaLib\Utils\Formatting::stripCodes($record->nickname, "wos"), $record->place, \ManiaLive\Utilities\Time::fromTM($record->time), $diff);
            $this->connection->chatSendServerMessage($message);
        } catch (\Exception $e) {
            \ManiaLive\Utilities\Console::println("Error: couldn't show dedimania message");
            print_r($e);
        }
    }

    public function onDedimaniaPlayerConnect($data) {
        if ($data == null)
            return;

        if ($data['Banned']) {
            return;
        }

        if ($this->config->disableMessages)
            return;

        $player = $this->storage->getPlayerObject($data['Login']);
        $type = '$fffFree';

        if ($data['MaxRank'] > 15) {
            $type = '$ff0Premium$fff';
            $upgrade = false;
        }

        $this->connection->chatSendServerMessage($player->nickName . '$z$s$fff connected with ' . $type . ' dedimania account. $0f0Top' . $data['MaxRank'] . '$fff records enabled.', null);
        if ($upgrade)
            $this->connection->chatSendServerMessage($upgrade, $data['Login']);
    }

    public function onDedimaniaPlayerDisconnect() {
        
    }

}

?>
