<?php
declare(strict_types=1);

function game_root(): string {
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $candidates = [];
    $webKey = web_server_key();
    $serverEnvName = 'GAME_ROOT_' . strtoupper($webKey);
    $envRoot = getenv($serverEnvName);
    if (is_string($envRoot) && trim($envRoot) !== '') {
        $candidates[] = trim($envRoot);
    }

    if (!empty($_SERVER[$serverEnvName])) {
        $candidates[] = (string)$_SERVER[$serverEnvName];
    }

    $envRoot = getenv('GAME_ROOT');
    if (is_string($envRoot) && trim($envRoot) !== '') {
        $candidates[] = trim($envRoot);
    }

    if (!empty($_SERVER['GAME_ROOT'])) {
        $candidates[] = (string)$_SERVER['GAME_ROOT'];
    }

    foreach (default_game_roots_by_web($webKey) as $defaultRoot) {
        $candidates[] = $defaultRoot;
    }

    $userProfile = getenv('USERPROFILE');
    if (is_string($userProfile) && trim($userProfile) !== '') {
        $candidates[] = rtrim($userProfile, "\\/") . DIRECTORY_SEPARATOR . 'Music' . DIRECTORY_SEPARATOR . $webKey;
    }

    $candidates[] = 'C:\\Users\\Administrator\\Music' . DIRECTORY_SEPARATOR . $webKey;
    $candidates[] = 'C:\\Users\\datvp\\Music' . DIRECTORY_SEPARATOR . $webKey;

    $existingCandidates = [];
    foreach (array_unique($candidates) as $candidate) {
        $candidate = rtrim($candidate, "\\/");
        if ($candidate !== '' && is_dir($candidate)) {
            $existingCandidates[] = $candidate;
            if (is_file($candidate . DIRECTORY_SEPARATOR . 'Config.properties') && is_writable($candidate)) {
                $root = $candidate;
                return $root;
            }
        }
    }

    foreach ($existingCandidates as $candidate) {
        if (is_writable($candidate)) {
            $root = $candidate;
            return $root;
        }
    }

    foreach ($existingCandidates as $candidate) {
            $root = $candidate;
            return $root;
    }

    $root = rtrim((string)end($candidates), "\\/");
    return $root;
}

function web_server_key(): string {
    $webRoot = realpath(dirname(__DIR__));
    $key = $webRoot !== false ? basename($webRoot) : basename(dirname(__DIR__));
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$key);
    return $key !== '' ? $key : 'sv1';
}

function default_game_roots_by_web(string $webKey): array {
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = $_SERVER['HOME'] ?? '';
    }

    $sdcardRoots = [];
    foreach (['SRC', 'SrcVIP'] as $cand) {
        $sdcardRoots[] = '/sdcard/Download/' . $cand;
        $sdcardRoots[] = '/storage/emulated/0/Download/' . $cand;
    }

    $roots = [
        'sv1' => array_values(array_filter([
            $home !== '' ? $home . '/SRC/SrcVIP' : null,
        ])),
    ];

    if (!isset($roots[$webKey])) {
        $extra = [];

        if ($home !== '') {
            foreach (glob($home . '/SRC/SrcVIP', GLOB_ONLYDIR) ?: [] as $d) {
                $extra[] = $d;
            }

            foreach (glob('/sdcard/Download/*', GLOB_ONLYDIR) ?: [] as $d) {
                $extra[] = $d;
            }
        }

        return array_merge($extra, $sdcardRoots);
    }

    return $roots[$webKey];
}

function game_file_path(string $fileName): string {
    return game_root() . DIRECTORY_SEPARATOR . ltrim($fileName, DIRECTORY_SEPARATOR);
}

function panel_command_file(): string {
    return game_file_path('panel_cmd.txt');
}

function panel_command_targets(): array {
    return [panel_command_file()];
}

function panel_exp_status_file(): string {
    return game_file_path('panel_exp_status.txt');
}

function panel_drop_status_file(): string {
    return game_file_path('panel_drop_status.txt');
}

function panel_admin_mode_status_file(): string {
    return game_file_path('panel_admin_mode_status.txt');
}

function panel_xnxx_status_file(): string {
    return game_file_path('panel_xnxx_status.txt');
}

function panel_boss_persist_status_file(): string {
    return game_file_path('panel_boss_persist_status.txt');
}

function panel_boss_overrides_file(): string {
    return game_file_path('panel_boss_overrides.properties');
}

function panel_boss_status_file(): string {
    $gameStatusFile = game_file_path('boss_status.json');
    if (is_dir(game_root())) {
        return $gameStatusFile;
    }
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'boss_status.json';
}

function panel_config_file(): string {
    return game_file_path('Config.properties');
}

function append_panel_command(string $command): bool {
    $written = false;
    foreach (panel_command_targets() as $commandFile) {
        $dir = dirname($commandFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }
        if (file_put_contents($commandFile, $command . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
            $written = true;
        }
    }
    return $written;
}

function panel_event_status_file(): string { return game_file_path('panel_event_status.json'); }
function get_current_teamv2_event(): array {
    $f = panel_event_status_file();
    if (is_file($f)) { $d = json_decode((string)@file_get_contents($f), true); if (is_array($d)) return $d; }
    return ['event_id' => 0, 'enabled' => false];
}
