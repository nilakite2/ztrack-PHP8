<?php
Aseco::addChatCommand('ztrack', 'Shows help for zTrack-Plugin');
Aseco::registerEvent('onPlayerInfoChanged', 'zt_onPlayerInfoChanged'); 
Aseco::registerEvent('onNewChallenge', 'zt_onNewChallenge'); 
Aseco::registerEvent('onCheckpoint', 'zt_onCheckpoint');
Aseco::registerEvent('onPlayerConnect', 'zt_onPlayerConnect');
Aseco::registerEvent('onPlayerDisconnect', 'zt_onPlayerDisconnect');
Aseco::registerEvent('onEndRace', 'zt_onEndRace');

// Declare global variables
global $zt_array;

// Initialize $zt_array
$zt_array = array();

function chat_ztrack($aseco, $command) {
    global $dedi_db, $zt_array;

    $zt_author = $command['author'];
    $args = explode(' ', $command['params']);
    $subcommand = strtolower(array_shift($args)); // Extract the subcommand

    switch ($subcommand) {
case 'dedi':
    // Handle dedi command
    if (!empty($args)) {
        $param = array_shift($args);
        if (is_numeric($param)) {
            // Process numeric parameter
            $dediIndex = intval($param) - 1; // Adjust index for array
            if (isset($dedi_db['Challenge']['Records'][$dediIndex])) {
                // Valid dedirecord, perform actions
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: CP-tracking is now comparing to {#highlite}Dedirecord ' . ($dediIndex + 1)), $zt_author->login);
                $zt_array[$zt_author->login]['Mode'] = 'Dedi';
                $zt_array[$zt_author->login]['Rec'] = $dediIndex;
                if ($aseco->isSpectator($zt_author)) {
                    zt_SendML($aseco, $zt_author->login, true);
                } else {
                    zt_SendMLSelf($aseco, $zt_author->login, true);
                }
            } else {
                // Invalid dedirecord index, display message
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: {#highlite}Dedirecord ' . ($dediIndex + 1) . ' {#message}does not exist'), $zt_author->login);
            }
            break;
        }
    }

    // Invalid parameter or missing, display message
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: Please select a dedirecord'), $zt_author->login);
    break;

        case 'local':
    // Handle local command
    if (!empty($args)) {
        $param = array_shift($args);
        if (is_numeric($param)) {
            // Process numeric parameter
            $localIndex = intval($param) - 1; // Adjust index for array
            if (isset($aseco->server->records->record_list[$localIndex])) {
                // Valid local record, perform actions
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: CP-tracking is now comparing to {#highlite}local record ' . ($localIndex + 1)), $zt_author->login);
                $zt_array[$zt_author->login]['Mode'] = 'Local';
                $zt_array[$zt_author->login]['Rec'] = $localIndex;
                if ($aseco->isSpectator($zt_author)) {
                    zt_SendML($aseco, $zt_author->login, true);
                } else {
                    zt_SendMLSelf($aseco, $zt_author->login, true);
                }
            } else {
                // Invalid local record index, display message
                $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: {#highlite}Local record ' . ($localIndex + 1) . '{#message} does not exist'), $zt_author->login);
            }
            break;
        }
    }

    // Invalid parameter or missing, display message
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: Please select a local record'), $zt_author->login);
    break;

        case 'off':
            // Handle off command
            // ...
            break;

        default:
            // Invalid command or no subcommand provided, display help message
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: Type {#highlite}/ztrack local <nr> {#message}or {#highlite}/ztrack dedi <nr> {#message}to compare the current racing time of yourself or the person you\'re speccing. Use {#highlite}/ztrack off {#message}to disable.'), $zt_author->login);
            break;
    }
}

// Function to handle the 'onNewChallenge' event
function zt_onNewChallenge($aseco) {
    global $zt_array;
    // Reset $zt_array on new challenge
    $zt_array = array();
}

// Function to handle the 'onEndRace' event
function zt_onEndRace($aseco) {
    // Send an empty manialink page to clear any previous display
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<manialink id="19861111">';
    $xml .= '</manialink>';
    $aseco->client->query("SendDisplayManialinkPage", $xml, 0, false);
}

// Function to handle the 'onPlayerDisconnect' event
function zt_onPlayerDisconnect($aseco, $player) {
    global $zt_array;
    // Remove player from $zt_array on disconnect
    if (isset($zt_array[$player->login])) {
        unset($zt_array[$player->login]);
    }
}

function zt_onPlayerConnect($aseco, $player) {
    // Display a message to the connecting player about zTrack
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}>> {#message}This server is running zTrack, type {#highlite}/ztrack {#message}for more information'), $player->login);

    // Send a default manialink to the connecting player
    zt_SendML($aseco, $player->login, false);
}

// Function to handle the 'onPlayerInfoChanged' event
function zt_onPlayerInfoChanged($aseco, $changes)
{
    global $zt_array;

    $zt_status = $changes['SpectatorStatus'];
    $login = $changes['Login'];

    if ($zt_status != 0) {
        $zt_player_id = floor($zt_status / 10000);

        if (floor($zt_status / 1000) % 2 == 0) {
            $zt_array[$login]['Target'] = zt_IDtoLogin($aseco, $zt_player_id);

            $mode = $zt_array[$login]['Mode'] ?? '';
            zt_SendML($aseco, $login, !empty($mode));
        } else {
            $zt_array[$login]['Mode'] = '';
            $zt_array[$login]['Target'] = $login; // Set 'Target' to the current player when leaving spectator mode
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#message}[zTrack]: CP-tracking disabled'), $login);
            zt_SendML($aseco, $login, false);
        }
    } elseif ($zt_status == 0) {
        $zt_array[$login]['Target'] = $login;

        $mode = $zt_array[$login]['Mode'] ?? '';
        zt_SendMLSelf($aseco, $login, !empty($mode));
    }
}

// Function to handle the 'onCheckpoint' event
function zt_onCheckpoint($aseco, $command)
{
    global $zt_array, $cpll_array, $dedi_db;

    foreach ($zt_array as $login => $info) {
        if (!empty($info['Target']) && $info['Target'] == $command[1]) {
            if ($info['Mode'] == 'Dedi') {
                $dedi_rec = $dedi_db['Challenge']['Records'][$info['Rec']]['Checks'];
                $local_time = $cpll_array[$info['Target']]['time'];
                $dedi_time = $dedi_rec[$cpll_array[$info['Target']]['cp'] - 1];

                $zt_delta = $local_time - $dedi_time;

                $zt_delta = $login == $info['Target'] ? $zt_delta : -1 * $zt_delta;
                zt_SendML($aseco, $login, $zt_delta);
            } elseif ($info['Mode'] == 'Local') {
                $local_time = $cpll_array[$info['Target']]['time'];
                $record_time = $aseco->server->records->record_list[$info['Rec']]->checks[$cpll_array[$info['Target']]['cp'] - 1];

                $zt_delta = $local_time - $record_time;

                $zt_delta = $login == $info['Target'] ? $zt_delta : -1 * $zt_delta;
                zt_SendML($aseco, $login, $zt_delta);
            }
        } else {
            // Handle the case when 'Target' is empty or does not match the checkpoint target
        }
    }
}

// Function to convert player ID to login
function zt_IDtoLogin($aseco, $id) {
    foreach ($aseco->server->players->player_list as $key => $player) {
        if ($player->pid == $id) {
            return $aseco->server->players->player_list[$key]->login;
        }
    }
    return '';
}

// Function to send manialink to players
function zt_SendML($aseco, $login, $time) {
    global $zt_array;

    $mode

 = $zt_array[$login]['Mode'] ?? '';

    if (!empty($mode)) {
        if ($login == $zt_array[$login]['Target']) {
            zt_SendMLSelf($aseco, $login, $time);
        } else {
            zt_SendML($aseco, $zt_array[$login]['Target'], $time);
        }
    }
    
    // Rest of your existing code for sending manialink...
}

// Function to send manialink to the connecting player
function zt_SendMLSelf($aseco, $login, $time) {
    global $zt_array;

    if (is_numeric($time)) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<manialink id="19861111">';
        $xml .= '<frame posn="0 -38.7 1">';
        $xml .= '<label scale="0.35" posn="0 0 1" halign="center" valign="center" style="TextRaceMessage" text="'.$zt_array[$login]['Mode'].' '.($zt_array[$login]['Rec']+1).'"/>';
        $xml .= '<label scale="0.5" posn="0 -1.9 1" halign="center" valign="center" style="TextRaceChrono" text="'.zt_formatTime($time).'"/>';
        $xml .= '</frame>';
        $xml .= '</manialink>';
    } else {
        if ($time) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<manialink id="19861111">';
            $xml .= '<frame posn="0 -38.7 1">';
            $xml .= '<label scale="0.35" posn="0 0 1" halign="center" valign="center" style="TextRaceMessage" text="'.$zt_array[$login]['Mode'].' '.($zt_array[$login]['Rec']+1).'"/>';
            $xml .= '<label scale="0.5" posn="0 -1.9 1" halign="center" valign="center" style="TextRaceChrono" text="--:--.--"/>';
            $xml .= '</frame>';
            $xml .= '</manialink>';
        } else {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<manialink id="19861111">';
            $xml .= '</manialink>';
        }
    }
    $aseco->client->query("SendDisplayManialinkPageToLogin", $login, $xml, 0, false);
}

// Function to format time
function zt_formatTime($ms) {
    $str = '$s$f00+';
    if ($ms <= 0) {
        $ms = $ms * (-1);
        $str = '$s$00f-';
    }
    $sec = floor($ms / 1000);
    $hun = ($ms - ($sec * 1000)) / 10;
    $min = floor($sec / 60);
    $_sec = $sec % 60;
    $time = sprintf('%02d:%02d.%02d', $min, $_sec, $hun);
    return $str.$time;
}
?>
