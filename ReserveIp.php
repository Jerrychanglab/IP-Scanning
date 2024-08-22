<?php
// 定義結果文件目錄
$reserve_dir = 'fping_reserved/';
$red_light_dir = 'fping_red_lights/';

if (!file_exists($reserve_dir)) {
    mkdir($reserve_dir, 0777, true);
    chown($reserve_dir, 'apache');
    chgrp($reserve_dir, 'apache');
}

if (!file_exists($red_light_dir)) {
    mkdir($red_light_dir, 0777, true);
    chown($reserve_dir, 'apache');
    chgrp($reserve_dir, 'apache');
}

// 獲取來自前端的參數
$ip = $_GET['ip'] ?? '';
$label = $_GET['label'] ?? '';
$machine = $_GET['machine'] ?? '';
$remove_red_light = isset($_GET['remove_red_light']) ? true : false;

if ($ip && $label) {
    // 生成對應的預留文件路徑
    $reserve_file = $reserve_dir . $label . '_reserved.txt';
    $red_light_file = $red_light_dir . $label . '_red_light.txt';

    // 讀取現有的預留 IP 和紅燈 IP
    $reserved_ips = file_exists($reserve_file) ? file($reserve_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $red_light_ips = file_exists($red_light_file) ? file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $updated_ips = [];
    $updated_red_lights = [];

    // 更新預留IP邏輯
    $found = false;
    foreach ($reserved_ips as $entry) {
        list($existing_ip, $existing_machine) = explode('|', $entry);
        if ($existing_ip === $ip) {
            $found = true;
            if (empty($machine)) {
                // 如果機器名稱為空，表示取消預留，不再保留該 IP
                continue;
            } else {
                // 更新機器名稱
                $updated_ips[] = $ip . '|' . $machine;
            }
        } else {
            $updated_ips[] = $entry;
        }
    }

    if (!$found && !empty($machine)) {
        // 添加新預留的 IP 和機器名稱
        $updated_ips[] = $ip . '|' . $machine;
    }

    // 處理紅燈狀態
    foreach ($red_light_ips as $red_ip) {
        if ($red_ip === $ip && $remove_red_light) {
            // 如果用戶選擇移除紅燈狀態，則不保留該 IP
            continue;
        } else {
            $updated_red_lights[] = $red_ip;
        }
    }

    // 保存更新後的預留 IP 到文件
    file_put_contents($reserve_file, implode(PHP_EOL, $updated_ips));
    chown($reserve_file, 'apache');
    chgrp($reserve_file, 'apache');
    chmod($reserve_file, 0644);

    // 保存更新後的紅燈 IP 到文件
    file_put_contents($red_light_file, implode(PHP_EOL, $updated_red_lights));
    chown($reserve_file, 'apache');
    chgrp($reserve_file, 'apache');
    chmod($reserve_file, 0644);
}
?>
