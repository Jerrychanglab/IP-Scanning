<?php
// 定義結果文件目錄
$directory = 'fping_results/';
$prev_output_dir = 'fping_prev_results/';
$reserve_dir = 'fping_reserved/';
$red_light_dir = 'fping_red_lights/';

// 初始化表格數據
$table_data = [];
$subnets = [
    "BORDER" => "10.31.32.0/24",
    "MONITOR" => "10.31.33.0/24",
    "ESXI" => "10.31.34.0/24",
    "DB" => "10.31.35.0/24",
    "API" => "10.31.36.0/24",
    "WEB" => "10.31.37.0/24",
    "AUTH" => "10.31.38.0/24",
    "LOG" => "10.31.39.0/24",
    "SHAPE" => "10.31.40.0/24",
    "PDNS" => "10.31.41.0/24"
];

// 讀取當前掃描結果和之前的掃描結果
foreach ($subnets as $label => $subnet) {
    $file = $directory . $label . '_results.txt';
    if (file_exists($file)) {
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } else {
        $content = [];
    }

    $ips_in_use = array_fill(1, 254, "");
    foreach ($content as $line) {
        if (!preg_match('/^=|^\[/', $line)) {
            $ip_parts = explode('.', $line);
            $last_octet = (int) end($ip_parts);
            $ips_in_use[$last_octet] = "已使用";
        }
    }

    $table_data[$label] = $ips_in_use;
}

// 讀取所有預留IP和機器名稱
$reserved_machines = [];
foreach ($subnets as $label => $subnet) {
    $reserve_file = $reserve_dir . $label . '_reserved.txt';
    if (file_exists($reserve_file)) {
        $reserved_ips = file($reserve_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($reserved_ips as $entry) {
            list($ip, $machine_name) = explode('|', $entry);
            $reserved_machines[$ip] = $machine_name;
        }
    }
}

// 讀取已儲存的紅燈狀態
$red_light_ips = [];
foreach ($subnets as $label => $subnet) {
    $red_light_file = $red_light_dir . $label . '_red_light.txt';
    if (file_exists($red_light_file)) {
        $red_light_ips[$label] = file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } else {
        $red_light_ips[$label] = [];
    }
}

// 組合所有數據並返回 JSON
$response = [
    'table_data' => $table_data,
    'reserved_machines' => $reserved_machines,
    'red_light_ips' => $red_light_ips
];

header('Content-Type: application/json');
echo json_encode($response);
?>
