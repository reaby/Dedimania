Dedimania
=========
Dedimania support for Manialive

Config
------
Add to these lines to your ManiaLive config.ini:

    manialive.plugins[] = 'Reaby\Dedimania';
    ManiaLivePlugins\Reaby\Dedimania\Config.login = "your_server_login"
    ManiaLivePlugins\Reaby\Dedimania\Config.code = "your_dedimania_code"

And you should be going

Additional config parameters
----------------------------

    ManiaLivePlugins\Reaby\Dedimania\Config.disableMessages = false
	ManiaLivePlugins\Reaby\Dedimania\Config.newRecordMessage = '%1$s $0b3claimed the$ff0 %2$s $0b3. Dedimania Record!  time:$ff0%3$s'
	ManiaLivePlugins\Reaby\Dedimania\Config.recordMsg = '%1$s $0b3secured the$ff0 %2$s $0b3. Dedimania Record!  time:$ff0%3$s $0b3$n($ff0 %4$s $0b3)!'
	ManiaLivePlugins\Reaby\Dedimania\Config.upgradeMsg = '$l[http://dedimania.net/tm2stats/?do=donation]Click here to upgrade your dedimania account.$l'
	
Events for Developers
=====================

If you are developer and would like write hooks to the dedimania system, it's easy since the connector uses events and custom callbacks.
See wiki for more info.