<?php
/**
 * Server status
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

use MyAAC\Cache\Cache;
use MyAAC\Models\Config;
use MyAAC\Models\PlayerOnline;
use MyAAC\Models\Player;

defined('MYAAC') or die('Direct access not allowed!');

$status = [
    'online' => false,
    'players' => 0,
    'playersMax' => 0,
    'lastCheck' => 0,
    'uptime' => '0h 0m',
    'monsters' => 0
];

if (setting('core.status_enabled') === false) {
    return;
}

/** @var array $config */
$status_ip = $config['lua']['ip'];

if (isset($config['lua']['statusProtocolPort'])) {
    $config['lua']['loginPort'] = $config['lua']['statusProtocolPort'];
    $config['lua']['statusPort'] = $config['lua']['statusProtocolPort'];
    $status_port = $config['lua']['statusProtocolPort'];
} elseif (isset($config['lua']['status_port'])) {
    $config['lua']['loginPort'] = $config['lua']['status_port'];
    $config['lua']['statusPort'] = $config['lua']['status_port'];
    $status_port = $config['lua']['status_port'];
}

// IP check
$settingIP = setting('core.status_ip');
if (!empty($settingIP)) {
    $status_ip = $settingIP;
} elseif (empty($status_ip)) {
    $status_ip = '127.0.0.1'; // fallback to localhost
}

// Port check
$status_port = $config['lua']['statusPort'];
$settingPort = setting('core.status_port');
if (!empty($settingPort)) {
    $status_port = $settingPort;
} elseif (empty($status_port)) {
    $status_port = 7171; // fallback
}

$fetch_from_db = true;

/** @var Cache $cache */
if ($cache->enabled()) {
    $tmp = '';
    if ($cache->fetch('status', $tmp)) {
        $status = unserialize($tmp);
        $fetch_from_db = false;
    }
}

if ($fetch_from_db) {
    $status_query = Config::where('name', 'LIKE', '%status%')->get();
    if (!$status_query || !$status_query->count()) {
        foreach ($status as $key => $value) {
            registerDatabaseConfig('status_' . $key, $value);
        }
    } else {
        foreach ($status_query as $tmp) {
            $status[str_replace('status_', '', $tmp->name)] = $tmp->value;
        }
    }
}

if (isset($config['lua']['statustimeout'])) {
    $config['lua']['statusTimeout'] = $config['lua']['statustimeout'];
}

// Get status timeout from server config
$status_timeout = eval('return ' . $config['lua']['statusTimeout'] . ';') / 1000 + 1;
$status_interval = setting('core.status_interval');
if ($status_interval && $status_timeout < $status_interval) {
    $status_timeout = $status_interval;
}

if ($status['lastCheck'] + $status_timeout < time()) {
    updateStatus();
}

function updateStatus() {
    global $db, $cache, $config, $status, $status_ip, $status_port;

    $serverInfo = new OTS_ServerInfo($status_ip, $status_port);
    $serverInfo->setTimeout(setting('core.status_timeout'));

    $serverStatus = $serverInfo->status();

    if (!$serverStatus) {
        $status['online'] = false;
        $status['players'] = 0;
        $status['playersMax'] = 0;
    } else {
        $status['lastCheck'] = time();
        $status['online'] = true;
        $status['players'] = $serverStatus->getOnlinePlayers();
        $status['playersMax'] = $serverStatus->getMaxPlayers();

        // Handle total players (incl. AFK)
        if (setting('core.online_afk')) {
            $status['playersTotal'] = 0;
            if ($db->hasTable('players_online')) {
                $status['playersTotal'] = PlayerOnline::count();
            } else {
                $status['playersTotal'] = Player::online()->count();
            }
        }

        // Format uptime
        $uptimeSeconds = $status['uptime'] = $serverStatus->getUptime();

        $months  = floor($uptimeSeconds / (30 * 24 * 60 * 60));
        $days    = floor(($uptimeSeconds % (30 * 24 * 60 * 60)) / (24 * 60 * 60));
        $hours   = floor(($uptimeSeconds % (24 * 60 * 60)) / (60 * 60));
        $minutes = floor(($uptimeSeconds % (60 * 60)) / 60);

        $uptimeStr = '';

        if ($months > 0) {
            $uptimeStr .= $months . ($months > 1 ? ' months, ' : ' month, ');
        }

        if ($days > 0) {
            $uptimeStr .= $days . ($days > 1 ? ' days, ' : ' day, ');
        }

        $uptimeStr .= "{$hours}h {$minutes}m";
        $status['uptimeReadable'] = $uptimeStr;

        // Other info
        $status['monsters'] = $serverStatus->getMonstersCount();
        $status['motd'] = $serverStatus->getMOTD();

        $status['mapAuthor'] = $serverStatus->getMapAuthor();
        $status['mapName'] = $serverStatus->getMapName();
        $status['mapWidth'] = $serverStatus->getMapWidth();
        $status['mapHeight'] = $serverStatus->getMapHeight();

        $status['server'] = $serverStatus->getServer();
        $status['serverVersion'] = $serverStatus->getServerVersion();
        $status['clientVersion'] = $serverStatus->getClientVersion();
    }

    if ($cache->enabled()) {
        $cache->set('status', serialize($status), 120);
    }

    $tmpVal = null;
    foreach ($status as $key => $value) {
        if (fetchDatabaseConfig('status_' . $key, $tmpVal)) {
            updateDatabaseConfig('status_' . $key, $value);
        } else {
            registerDatabaseConfig('status_' . $key, $value);
        }
    }
}
