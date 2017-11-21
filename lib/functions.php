<?php

$ircbot_socket = null;

function ircbot_main() {
    if (!ircbot_db()) {
        return false;
    }
    
    while(!ircbot_reconnect()) {
        sleep(10);
    }
    
    return true;
}

function ircbot_reconnect() {
    if (!ircbot_init()) {
        return false;
    }
    
    if (!ircbot_loop()) {
        return false;
    }
    
    return false;
}

function ircbot_db() {
    static $db;
    
    if (!$db) {
        if (!($db = mysqli_connect(IRCBOT_MYSQL_HOST, IRCBOT_MYSQL_USER, IRCBOT_MYSQL_PASS))) {
            return false;
        }
        
        if (!mysqli_select_db($db, IRCBOT_MYSQL_DB)) {
            return false;
        }
        
        if (!ircbot_db_schema($db)) {
            return false;
        }
    }
    
    return $db;
}

function ircbot_db_schema(&$db) {
    if (!mysqli_query($db, 'DESCRIBE `log`')) {
        $s = file_get_contents(IRCBOT_LIB . 'schema.sql');
        
        if (!mysqli_query($db, $s)) {
            return false;
        }
    }
    
    return true;
}

function ircbot_init() {
    global $ircbot_socket;
    
    $en = $es = null;
    
    if (!($ircbot_socket = fsockopen(IRCBOT_IRC_SERVER, IRCBOT_IRC_PORT, $en, $es, IRCBOT_IRC_TIMEOUT))) {
        return false;
    }
    
    if (!ircbot_write('NICK ' . IRCBOT_IRC_NICK . IRCBOT_EOL)) {
        return false;
    }
    
    if (!ircbot_write('USER ' . IRCBOT_IRC_USER . ' ' . IRCBOT_IRC_SERVER . ' bla :' . IRCBOT_IRC_REAL . IRCBOT_EOL)) {
        return false;
    }
    
    return true;
}

function ircbot_loop() {
    global $ircbot_socket;
    
    $has_ping = false;
    $joinned = false;
    
    while(!feof($ircbot_socket)) {
        $line = fgets($ircbot_socket, 4096);
        
        if (!empty($line)) {
            if (IRCBOT_DEBUG) {
                echo '<- ' . $line . IRCBOT_EOL;
            }

            if (strstr($line, 'KICK ' . IRCBOT_IRC_CHANNEL . ' ' . IRCBOT_IRC_NICK)) {
                $joinned = false;
            }
            
            if (strstr($line, 'Nickname is already in use')) {
                if (!ircbot_write('NICK ' . IRCBOT_IRC_NICK . rand(0,9) . IRCBOT_EOL)) {
                    return false;
                }
            }
            
            if(strstr($line, 'PING')) {
                $has_ping = true;
                list(, $pong) = explode(':', $line);
                
                if (!ircbot_write('PONG :' . trim($pong) . IRCBOT_EOL)) {
                    return false;
                }
            }

            if ($has_ping && !$joinned) {
                $joinned = true;

                if (!ircbot_write('JOIN :' . IRCBOT_IRC_CHANNEL . IRCBOT_EOL)) {
                    return false;
                }
            }

            $needle = 'PRIVMSG ' . IRCBOT_IRC_CHANNEL;

            if (strstr($line, $needle)) {
                $ls = explode(':', $line);
                array_shift($ls);
                
                $user = array_shift($ls);
                $msg = implode(':', $ls);

                list ($nick) = explode('!', $user);

                if ($nick == IRCBOT_IRC_TARGET) {
                    ircbot_log($msg);
                } elseif (IRCBOT_DEBUG) {
                    echo 'NICK NOT MATCH: ' . $line . ' (' . $user . ', ' . $msg . ', ' . $nick . ')' . IRCBOT_EOL;
                }
            } elseif (IRCBOT_DEBUG) {
                echo 'DID NOT MATCH: ' . $line . ' ' . $needle . IRCBOT_EOL;
            }
        }
        
        usleep(100000);
    }

    return true;
}

function ircbot_write($line) {
    global $ircbot_socket;
    
    if (IRCBOT_DEBUG) {
        echo '-> ' . $line . IRCBOT_EOL;
    }
    
    return fwrite($ircbot_socket, $line);
}

function ircbot_log($message) {
    //$db = ircbot_db();
    $mysqli = new mysqli(IRCBOT_MYSQL_HOST,IRCBOT_MYSQL_USER,IRCBOT_MYSQL_PASS,IRCBOT_MYSQL_DB) or die($mysqli->error);
	$q = 'INSERT INTO `log` VALUES (NULL, "' . $mysqli->real_escape_string(trim(ircbot_strip($message))) . '", NOW())';
    
    $mysqli->query($q);
}

function ircbot_strip($text) {
    $controlCodes = array(
        '/(\x03(?:\d{1,2}(?:,\d{1,2})?)?)/',    // Color code
        '/\x02/',                               // Bold
        '/\x0F/',                               // Escaped
        '/\x16/',                               // Italic
        '/\x1F/'                                // Underline
    );
    
    return preg_replace($controlCodes, '', $text);
}
