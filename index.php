<?php
require 'vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

// 🔑 Constants for tuning
const API_ID = 1234;   
const API_HASH = '234nsldfklk';
$target = -10023423; 

$settings = (new Settings) ->setAppInfo((new AppInfo) ->setApiId(API_ID) ->setApiHash(API_HASH)) ->setLogger((new Logger) ->setLevel(1));

$sessionFile = 'session.madeline';
$resumeFile  = 'resume.json';

$GLOBALS['MadelineProto'] = null;
$GLOBALS['downloaded'] = []; // track downloaded IDs

function startMadeline($sessionFile, $settings) {
    $MadelineProto = new API($sessionFile, $settings);
    $MadelineProto->start();
    $GLOBALS['MadelineProto'] = $MadelineProto;
    return $MadelineProto;
}

function resetSession($sessionFile, $settings) {
    echo "Resetting session...\n";
    if (file_exists($sessionFile)) unlink($sessionFile);
    return startMadeline($sessionFile, $settings);
}

function getMadeline() {
    return $GLOBALS['MadelineProto'];
}

function safeDownload($message, $dir) {
    $MadelineProto = getMadeline();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $expected = $message['media']['document']['size'] ?? null;
    $label = $message['id'];

    echo "Starting download for message ID $label...\n";

    $retries = 3;
    while ($retries > 0) {
        try {
            $tempFile = $MadelineProto->downloadToDir($message, $dir);

            if ($tempFile === null || !file_exists($tempFile)) {
                throw new \Exception("Download failed: no file returned");
            }

            $actual = filesize($tempFile);
            if ($expected === null || $expected == $actual) {
                echo "Downloaded verified file: ".basename($tempFile)."\n";
                return $tempFile;
            } else {
                echo "Size mismatch (expected $expected, got $actual). Retrying...\n";
                unlink($tempFile);
            }
        } catch (\Exception $e) {
            echo "Download error for $label: ".$e->getMessage()."\n";
            if (strpos($e->getMessage(), 'SESSION') !== false) {
                resetSession($GLOBALS['sessionFile'], $GLOBALS['settings']);
            }
            if (strpos($e->getMessage(), 'cancelled') !== false) {
                echo "Retrying cancelled download...\n";
            }
        }

        $retries--;
        sleep(2);
    }

    throw new \Exception("Failed to download $label after retries.");
}

// Detect and resolve peer 
function resolvePeer($MadelineProto, $target) 
{ 
    if (is_string($target)) { 
        if (strpos($target, '@') === 0) { 
            // Username 
            $info = $MadelineProto->getFullInfo($target); return $info['id']; 
        } elseif (strpos($target, 'https://t.me/') === 0) { 
            // Invite link 
            return $MadelineProto->joinChat($target); 
        } 
    } if (is_int($target)) { 
        // Numeric ID (supergroup/channel) 
        $info = $MadelineProto->getFullInfo($target); 
        // print_r($info);exit;
        return $info['id']; 
    } 
    throw new \Exception("Unsupported peer format: $target"); 
} 

// ---- Startup ----
try {
    $MadelineProto = startMadeline($sessionFile, $settings);
    $MadelineProto->messages->getDialogs(); 
} catch (\Exception $e) {
    $MadelineProto = resetSession($sessionFile, $settings);
}

// ---- CONFIG ----
$offset_id = file_exists($resumeFile) ? json_decode(file_get_contents($resumeFile), true)['last_id'] ?? 0 : 0;
$limit = 50; // batch size

while (true) {
    try {
        $messages = getMadeline()->messages->getHistory([
            'peer' => $target,
            'offset_id' => $offset_id,
            'limit' => $limit,
            'hash' => 0,
        ]);

        if (empty($messages['messages'])) {
            echo "No more messages.\n";
            break;
        }

        foreach ($messages['messages'] as $message) {
            if (isset($message['media']) && !in_array($message['id'], $GLOBALS['downloaded'])) {
                try {
                    safeDownload($message, __DIR__.'/download/');
                    $GLOBALS['downloaded'][] = $message['id'];
                } catch (\Exception $e) {
                    echo "Download error: ".$e->getMessage()."\n";
                }
            }
        }

        // Advance offset once per batch (last message ID)
        $last = end($messages['messages']);
        $offset_id = $last['id'];

        // Save resume state
        file_put_contents($resumeFile, json_encode(['last_id' => $offset_id]));

        // Small delay to avoid flood-wait
        sleep(3);

    } catch (\Exception $e) {
        echo "History fetch error: ".$e->getMessage()."\n";
        if (strpos($e->getMessage(), 'SESSION') !== false) {
            resetSession($sessionFile, $settings);
        }
        sleep(5);
    }
}
