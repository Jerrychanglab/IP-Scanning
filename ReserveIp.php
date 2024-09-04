<?php
// 定義結果文件目錄
$reserve_dir = 'fping_reserved/';
$red_light_dir = 'fping_red_lights/';
$results_dir = 'fping_results/';

// 確保目錄存在
if (!file_exists($reserve_dir)) {
    mkdir($reserve_dir, 0777, true);
    chown($reserve_dir, 'apache');
    chgrp($reserve_dir, 'apache');
}

if (!file_exists($red_light_dir)) {
    mkdir($red_light_dir, 0777, true);
    chown($red_light_dir, 'apache');
    chgrp($red_light_dir, 'apache');
}

// 獲取來自前端的參數
$ip = $_GET['ip'] ?? '';
$label = $_GET['label'] ?? '';
$machine = $_GET['machine'] ?? '';
$remove_red_light = isset($_GET['remove_red_light']) ? true : false;
$auto_reserve = isset($_GET['auto_reserve']) ? true : false;  // 用來標識自動預留的請求
$auto_cancel = isset($_GET['auto_cancel']) ? true : false;  // 用來標識自動取消的請求

// 如果 IP 參數為 'iplist'，顯示目前使用的 IP 列表
if ($ip === 'iplist') {
    // 獲取所有有狀態的 IP
    $status_ips = [];

    foreach (glob($results_dir . '*_results.txt') as $result_file) {
        $label = basename($result_file, '_results.txt');
        $content = file($result_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 讀取當前掃描結果中的綠燈 IP
        foreach ($content as $line) {
            if (!preg_match('/^=|^\[/', $line)) {
                $status_ips[] = trim($line); // 確保每行都被正確地修剪
            }
        }

        // 讀取黃燈 IP（預留 IP）
        $reserve_file = $reserve_dir . $label . '_reserved.txt';
        if (file_exists($reserve_file)) {
            $reserved_ips = file($reserve_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($reserved_ips as $entry) {
                list($ip, $machine_name) = explode('|', $entry);
                $status_ips[] = trim($ip); // 確保每個 IP 地址都被正確地修剪
            }
        }

        // 讀取紅燈 IP
        $red_light_file = $red_light_dir . $label . '_red_light.txt';
        if (file_exists($red_light_file)) {
            $red_light_ips = file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($red_light_ips as $red_ip) {
                $status_ips[] = trim($red_ip); // 確保每個 IP 地址都被正確地修剪
            }
        }
    }

    // 移除重複的 IP 地址
    $status_ips = array_unique($status_ips);

    // 過濾掉不必要的符號
    $status_ips = array_filter($status_ips, function($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP); // 使用 PHP 的內建函數過濾並確保 IP 地址的有效性
    });

    // 輸出結果，每行一個 IP
    header('Content-Type: text/plain');
    foreach ($status_ips as $ip) {
        echo $ip . PHP_EOL;
    }
    exit; // 結束腳本執行，避免進一步處理
}

// 以下部分為處理預留和取消預留的邏輯
if ($ip && $label) {
    // 生成對應的預留文件路徑
    $reserve_file = $reserve_dir . $label . '_reserved.txt';
    $red_light_file = $red_light_dir . $label . '_red_light.txt';

    // 讀取現有的預留 IP 和紅燈 IP
    $reserved_ips = file_exists($reserve_file) ? file($reserve_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $red_light_ips = file_exists($red_light_file) ? file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $updated_ips = [];
    $updated_red_lights = [];

    // 自動預留IP邏輯
    if ($auto_reserve) {
        $reserved_ips[] = $ip . '|' . $machine;
        $reserved_ips = array_unique($reserved_ips); // 確保IP不重複
    }

    // 自動取消IP邏輯
    if ($auto_cancel) {
        foreach ($reserved_ips as $entry) {
            list($existing_ip, $existing_machine) = explode('|', $entry);
            if ($existing_ip !== $ip) {
                $updated_ips[] = $entry;
            }
        }
    } else {
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
    if (!empty($updated_ips)) {
        file_put_contents($reserve_file, implode(PHP_EOL, $updated_ips) . PHP_EOL); // 確保文件以換行符結尾
        chown($reserve_file, 'apache');
        chgrp($reserve_file, 'apache');
        chmod($reserve_file, 0644);
    } else {
        if (file_exists($reserve_file)) {
            unlink($reserve_file); // 如果沒有預留 IP，刪除文件
        }
    }

    // 保存更新後的紅燈 IP 到文件
    if (!empty($updated_red_lights)) {
        file_put_contents($red_light_file, implode(PHP_EOL, $updated_red_lights) . PHP_EOL); // 確保文件以換行符結尾
        chown($red_light_file, 'apache');
        chgrp($red_light_file, 'apache');
        chmod($red_light_file, 0644);
    } else {
        if (file_exists($red_light_file)) {
            unlink($red_light_file); // 如果沒有紅燈 IP，刪除文件
        }
    }
}
?>
