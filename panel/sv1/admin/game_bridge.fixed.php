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
            if (is_file($candidate . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.properties') && is_writable($candidate)) {
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
    // FIX-TEAMV2: uu tien source TeamV2 tren Termux/Android thay vi duong dan Windows cu.
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') {
        $home = $_SERVER['HOME'] ?? '';
    }
    $roots = [
        'sv1' => array_values(array_filter([
            getenv('GAME_ROOT') ?: null,
            $home !== '' ? $home . '/nro_termux/SrcVIP' : null,
            $home !== '' ? $home . '/teamv2_termux/TeamV2' : null,
            '/sdcard/Download/SRC_4',
            '/storage/emulated/0/Download/SRC_4',
            '/sdcard/Download/SRC_4/SRC',
            '/storage/emulated/0/Download/SRC_4/SRC',
            '/sdcard/Download/TeamV2',
            '/sdcard/Download/TeamV2.zip/TeamV2',
            'C:\\Users\\datvp\\Music\\cc2',
            'C:\\Users\\Administrator\\Music\\cc2',
        ])),
    ];
    if (!isset($roots[$webKey])) {
        $extra = [];
        if ($home !== '') {
            foreach (glob($home . '/*/SrcVIP', GLOB_ONLYDIR) ?: [] as $d) $extra[] = $d;
            foreach (glob($home . '/*/TeamV2', GLOB_ONLYDIR) ?: [] as $d) $extra[] = $d;
            foreach (glob('/sdcard/Download/*', GLOB_ONLYDIR) ?: [] as $d) $extra[] = $d;
        }
        return $extra;
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
    return game_file_path('data' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.properties');
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
