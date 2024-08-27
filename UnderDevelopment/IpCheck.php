<?php
// 定義結果文件目錄
$directory = 'fping_results/';
$prev_output_dir = 'fping_prev_results/';
$reserve_dir = 'fping_reserved/';
$red_light_dir = 'fping_red_lights/';

// 確保紅燈狀態目錄存在
if (!file_exists($red_light_dir)) {
    mkdir($red_light_dir, 0777, true);
    chown($red_light_dir, 'apache');
    chgrp($red_light_dir, 'apache');
}

// 獲取目錄下的所有 txt 文件
$files = glob($directory . '*.txt');

// 初始化表格數據
$table_data = [];
$prev_table_data = [];

$subnets = [];
$desired_order = [];

// 定義子網檔案的目錄
$subnets_dir = __DIR__ . '/subnets/';

// 遍歷每個 PHP 子網檔案
foreach (glob($subnets_dir . '*.php') as $file) {
    $data = include($file);
    $category = strtoupper($data['category']); // 將 category 轉為大寫
    $ip = $data['ip'];
    $mask = $data['mask'];

    // 加入子網數組
    $subnets[$category] = "$ip/$mask";

    // 加入到 desired_order 數組中（保持檔案順序）
    $desired_order[] = $category;
}

// 讀取當前掃描結果和之前的掃描結果
foreach ($files as $file) {
    $label = basename($file, '_results.txt');
    $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $prev_content = file_exists($prev_output_dir . '/' . $label . '_results.txt') ? file($prev_output_dir . '/' . $label . '_results.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

    // 初始化IP列表
    $ips_in_use = array_fill(1, 254, "");
    $prev_ips_in_use = array_fill(1, 254, "");
    foreach ($content as $line) {
        if (!preg_match('/^=|^\[/', $line)) {
            $ip_parts = explode('.', $line);
            $last_octet = (int) end($ip_parts);
            $ips_in_use[$last_octet] = "已使用";
        }
    }

    foreach ($prev_content as $line) {
        if (!preg_match('/^=|^\[/', $line)) {
            $ip_parts = explode('.', $line);
            $last_octet = (int) end($ip_parts);
            $prev_ips_in_use[$last_octet] = "已使用";
        }
    }

    $table_data[$label] = $ips_in_use;
    $prev_table_data[$label] = $prev_ips_in_use;

    // 如果IP從紅燈變回綠燈，移除紅燈狀態
    $red_light_file = $red_light_dir . $label . '_red_light.txt';
    if (file_exists($red_light_file)) {
        $red_light_ips = file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $updated_red_light_ips = [];

        foreach ($red_light_ips as $red_ip) {
            if (!in_array($red_ip, $content)) { 
                $updated_red_light_ips[] = $red_ip;
            }
        }

        // 保存更新後的紅燈狀態，如果紅燈已變回綠燈，則移除它
        if (count($updated_red_light_ips) !== count($red_light_ips)) {
            file_put_contents($red_light_file, implode(PHP_EOL, $updated_red_light_ips));
            chown($red_light_file, 'apache');
            chgrp($red_light_file, 'apache');
            chmod($red_light_file, 0644);
        }
    }
}

// 讀取所有預留IP和機器名稱
$reserved_ips = [];
$reserved_machines = [];
foreach ($desired_order as $label) {
    $reserve_file = $reserve_dir . $label . '_reserved.txt';
    if (file_exists($reserve_file)) {
        $reserved_ips[$label] = file($reserve_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($reserved_ips[$label] as $entry) {
            list($ip, $machine_name) = explode('|', $entry);
            $reserved_machines[$ip] = $machine_name;
        }
    } else {
        $reserved_ips[$label] = [];
    }
}

// 讀取已儲存的紅燈狀態
$red_light_ips = [];
foreach ($desired_order as $label) {
    $red_light_file = $red_light_dir . $label . '_red_light.txt';
    if (file_exists($red_light_file)) {
        $red_light_ips[$label] = file($red_light_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    } else {
        $red_light_ips[$label] = [];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>IP 掃描結果</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f5f5f5;
        margin: 20px;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    table {
        width: 100%;
        max-width: 1200px;
        margin: 20px auto;
        border-collapse: collapse;
        background-color: #ffffff;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    th, td {
        padding: 10px;
        text-align: center;
        border: 1px solid #ddd;
        position: relative;
    }

    .sticky-header, .sticky-subheader {
        background-color: #007B8F;
        color: white;
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .sticky-header {
        top: 1;
    }

    .sticky-subheader {
        top: 39px;
    }

    .used {
        color: green;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
    }

    .reserved {
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: bold;
        position: relative;
        cursor: pointer;
    }

    .lost {
        color: red;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
    }

    .reserved .machine-name, .lost .machine-name {
        display: none;
        background-color: #fff;
        padding: 3px;
        border-radius: 5px;
        border: 1px solid #ddd;
        box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.1);
        white-space: nowrap;
        position: absolute;
        top: 90%;
        left: 50%;
        transform: translateX(-50%);
    }

    .reserved:hover .machine-name, .lost:hover .machine-name {
        display: inline;
    }

    .disabled {
        color: gray;
    }

    td:not(.disabled):hover {
        cursor: pointer;
        background-color: #C4E1E1;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tr:nth-child(odd) {
        background-color: #ffffff;
    }

    @media (max-width: 768px) {
        table, th, td {
            font-size: 14px;
        }

        h1 {
            font-size: 24px;
        }
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        transition: opacity 0.3s ease-in-out;
        opacity: 0;
    }

    .modal.show {
        display: block;
        opacity: 1;
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 400px;
        text-align: center;
        border-radius: 10px;
        box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.2);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        transition: color 0.2s;
    }

    .close:hover, .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    .cancel, .confirm {
        font-size: 16px;
        font-weight: bold;
        padding: 10px 20px;
        margin: 10px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
    }

    .cancel {
        background-color: #ccc;
        color: #333;
    }

    .cancel:hover {
        background-color: #bbb;
    }

    .confirm {
        background-color: #007B8F;
        color: white;
    }

    .confirm:hover {
        background-color: #005f6b;
    }

    #machine-name {
        width: calc(100% - 40px);
        padding: 12px;
        border-radius: 5px;
        border: 1px solid #ccc;
        font-size: 1.2em;
    }

    .snmp-result {
        display: none;
        margin-top: 5px;
        font-size: 12px;
        color: blue;
    }

    /* 新增遮罩层的样式 */
    #overlay {
        position: fixed;
        display: none;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        cursor: not-allowed;
    }
    </style>

    <script>
        function updateTable() {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "GetScanResults.php", true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var data = JSON.parse(xhr.responseText);
                    var tableData = data.table_data;
                    var reservedMachines = data.reserved_machines;
                    var redLightIps = data.red_light_ips;

                    // 更新表格
                    <?php foreach ($desired_order as $label): ?>
                        for (var i = 1; i <= 254; i++) {
                            var subnetIp = "<?php echo explode('/', $subnets[$label])[0]; ?>";
                            var subnetBase = subnetIp.split('.').slice(0, 3).join('.');
                            var ip = subnetBase + "." + i;
                            var cell = document.getElementById(ip + "_<?php echo $label; ?>");

                            if (cell) {
                                var isUsed = tableData['<?php echo $label; ?>'][i] === "已使用";
                                var isReserved = reservedMachines[ip] !== undefined;
                                var isRedLight = redLightIps['<?php echo $label; ?>'].includes(ip);
                                var bothReservedAndUsed = isReserved && isUsed;
                                var bothReservedAndLost = isReserved && isRedLight;
                                
                                if (bothReservedAndUsed) {
                                    cell.className = "reserved used";
                                    cell.innerHTML = '🟡🟢 <span class="machine-name">' + reservedMachines[ip] + '</span>';
                                } else if (bothReservedAndLost) {
                                    cell.className = "reserved lost";
                                    cell.innerHTML = '🟡🔴 <span class="machine-name">' + reservedMachines[ip] + '</span>';
                                } else if (isReserved) {
                                    cell.className = "reserved";
                                    cell.innerHTML = '🟡 <span class="machine-name">' + reservedMachines[ip] + '</span>';
                                } else if (isUsed) {
                                    cell.className = "used disabled";
                                    cell.innerHTML = '🟢';
                                } else if (isRedLight) {
                                    cell.className = "lost";
                                    cell.innerHTML = '🔴';
                                } else {
                                    cell.className = "";
                                    cell.innerHTML = '';
                                }
                            }
                        }
                    <?php endforeach; ?>
                }
            };
            xhr.send();
        }

        // 設置定期更新，每10秒更新一次
        setInterval(updateTable, 5000);
        // 頁面載入時立即更新一次
        window.onload = updateTable;

        function performSNMPwalk(ip) {
            console.log("performSNMPwalk called for IP: " + ip);

            // 顯示遮罩層
            document.getElementById('overlay').style.display = 'block';

            var xhr = new XMLHttpRequest();
            xhr.open("GET", "SnmpWalk.php?ip=" + ip, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var result = xhr.responseText;
                    console.log("SNMPwalk result: " + result);

                    // 彈出一個新的窗口顯示機器名稱
                    var resultModal = document.createElement("div");
                    resultModal.className = "modal show";
                    resultModal.style.zIndex = "10000"; 
                    resultModal.innerHTML = `
                        <div class="modal-content">
                            <span class="close" onclick="this.parentElement.parentElement.remove(); document.getElementById('overlay').style.display = 'none';">&times;</span>
                            <p>SNMP掃描結果</p>
                            <p>${result}</p>
                            <button class="confirm" onclick="this.parentElement.parentElement.remove(); document.getElementById('overlay').style.display = 'none';">關閉</button>
                        </div>
                    `;

                    document.body.appendChild(resultModal); // 將新窗口添加到頁面中
                } else if (xhr.readyState == 4) {
                    console.error("SNMPwalk request failed with status: " + xhr.status);
                    document.getElementById('overlay').style.display = 'none'; // 如果請求失敗，移除遮罩層
                }
            };
            xhr.send();
        }

function reserveIP(label, ip) {
    var element = document.getElementById(ip + "_" + label);
    var isUsed = element.classList.contains('used');
    var isReserved = element.classList.contains('reserved');
    var isLost = element.classList.contains('lost');

    // 如果同時存在綠燈（used）和黃燈（reserved），執行取消註解的操作
    if (isUsed && isReserved) {
        console.log("綠燈和黃燈同時存在，執行取消註解操作...");

        // 顯示取消註解的確認彈窗
        var modal = document.getElementById("myModal");
        var modalText = document.getElementById("modal-text");
        var machineNameInput = document.getElementById("machine-name");
        machineNameInput.value = "";  // 清空輸入框的值
        machineNameInput.style.display = "none";

        modalText.innerHTML = "確定要取消預留 IP " + ip + " 嗎？";
        modal.classList.add('show');

        var confirmBtn = document.getElementsByClassName("confirm")[0];
        confirmBtn.onclick = function() {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "ReserveIp.php?label=" + label + "&ip=" + ip + "&remove_reservation=true", true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    updateTable(); // 更新表格
                }
            };
            xhr.send();
            modal.classList.remove('show');
        };

        var closeBtn = document.getElementsByClassName("close")[0];
        var cancelBtn = document.getElementsByClassName("cancel")[0];
        closeBtn.onclick = cancelBtn.onclick = function() {
            modal.classList.remove('show');
        };

        return; // 阻止其他操作
    }

    // 如果只有綠燈存在，執行 SNMP 掃描
    if (isUsed && !isReserved) {
        console.log("IP is used, performing SNMPwalk...");
        var modal = document.getElementById("myModal");
        var modalText = document.getElementById("modal-text");
        var machineNameInput = document.getElementById("machine-name");
        machineNameInput.value = "";  // 清空輸入框的值
        machineNameInput.style.display = "none";

        modalText.innerHTML = "您要對IP:" + ip + " 進行SNMP掃描嗎？";
        modal.classList.add('show');

        var confirmBtn = document.getElementsByClassName("confirm")[0];
        confirmBtn.onclick = function() {
            modal.classList.remove('show');
            performSNMPwalk(ip); // 執行SNMP掃描
        };

        var closeBtn = document.getElementsByClassName("close")[0];
        var cancelBtn = document.getElementsByClassName("cancel")[0];
        closeBtn.onclick = cancelBtn.onclick = function() {
            modal.classList.remove('show');
        };

        return; // 阻止後續的預留或取消紅燈操作
    }

    // 如果存在紅燈（lost），則取消預留狀態
    if (isLost) {
        console.log("紅燈存在，執行取消操作...");
        var modal = document.getElementById("myModal");
        var modalText = document.getElementById("modal-text");
        var machineNameInput = document.getElementById("machine-name");
        machineNameInput.value = "";  // 清空輸入框的值
        machineNameInput.style.display = "none";

        modalText.innerHTML = "確定取消紅燈狀態 IP " + ip + " 嗎？";
        modal.classList.add('show');

        var confirmBtn = document.getElementsByClassName("confirm")[0];
        confirmBtn.onclick = function() {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "ReserveIp.php?label=" + label + "&ip=" + ip + "&remove_red_light=true", true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    updateTable(); // 更新表格
                }
            };
            xhr.send();
            modal.classList.remove('show');
        };

        var closeBtn = document.getElementsByClassName("close")[0];
        var cancelBtn = document.getElementsByClassName("cancel")[0];
        closeBtn.onclick = cancelBtn.onclick = function() {
            modal.classList.remove('show');
        };

        return; // 阻止其他操作
    }

    // 處理黃燈（reserved）的情況
    if (isReserved && !isUsed) {
        console.log("執行預留 IP 操作...");
        var modal = document.getElementById("myModal");
        var modalText = document.getElementById("modal-text");
        var machineNameInput = document.getElementById("machine-name");
        machineNameInput.value = "";  // 清空輸入框的值
        machineNameInput.style.display = "none";

        modalText.innerHTML = "確定取消預留 IP " + ip + " 嗎？";
        modal.classList.add('show');

        var confirmBtn = document.getElementsByClassName("confirm")[0];
        confirmBtn.onclick = function() {
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "ReserveIp.php?label=" + label + "&ip=" + ip + "&remove_reservation=true", true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    updateTable(); // 更新表格
                }
            };
            xhr.send();
            modal.classList.remove('show');
        };

        var closeBtn = document.getElementsByClassName("close")[0];
        var cancelBtn = document.getElementsByClassName("cancel")[0];
        closeBtn.onclick = cancelBtn.onclick = function() {
            modal.classList.remove('show');
        };

        return; // 阻止其他操作
    }

    // 如果既沒有綠燈也沒有紅燈，則處理預留IP
    if (!isUsed && !isLost) {
        console.log("執行預留 IP 操作...");
        var modal = document.getElementById("myModal");
        var modalText = document.getElementById("modal-text");
        var machineNameInput = document.getElementById("machine-name");
        machineNameInput.value = "";  // 清空輸入框的值
        machineNameInput.style.display = "block";

        modalText.innerHTML = "輸入主機名稱[預留] IP: " + ip;
        modal.classList.add('show');

        var confirmBtn = document.getElementsByClassName("confirm")[0];
        confirmBtn.onclick = function() {
            var machineName = machineNameInput.value;
            if (!machineName) {
                alert("必須輸入機器名稱才能預留 IP。");
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("GET", "ReserveIp.php?label=" + label + "&ip=" + ip + "&machine=" + encodeURIComponent(machineName), true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    updateTable(); // 更新表格
                }
            };
            xhr.send();
            modal.classList.remove('show');
        };

        var closeBtn = document.getElementsByClassName("close")[0];
        var cancelBtn = document.getElementsByClassName("cancel")[0];
        closeBtn.onclick = cancelBtn.onclick = function() {
            modal.classList.remove('show');
        };

        return; // 阻止其他操作
    }
}


    </script>
</head>
<body>
    <h1>NTP Site 內網IP 掃描</h1>
    
    <!-- 遮罩層 -->
    <div id="overlay"></div>
    
    <table>
        <tr>
            <th rowspan="2" class="sticky-header"></th>
            <?php foreach ($desired_order as $label): ?>
            <th class="sticky-header"><?php echo htmlspecialchars($label); ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($desired_order as $label): ?>
            <td class="sticky-subheader"><?php echo htmlspecialchars($subnets[$label]); ?></td>
            <?php endforeach; ?>
        </tr>
        <?php for ($i = 1; $i <= 254; $i++): ?>
        <tr>
            <td><?php echo $i; ?></td>
            <?php foreach ($desired_order as $label): ?>
            <?php
                $subnet_ip = explode('/', $subnets[$label])[0];
                $subnet_base = implode('.', array_slice(explode('.', $subnet_ip), 0, 3)); // 取前三個部分
                $ip = $subnet_base . "." . $i;
                $is_used = isset($table_data[$label][$i]) && $table_data[$label][$i] === "已使用";
                $was_used = isset($prev_table_data[$label][$i]) && $prev_table_data[$label][$i] === "已使用";
                $is_reserved = array_key_exists($ip, $reserved_machines);
                $both_reserved_and_used = $is_reserved && $is_used;
                $both_reserved_and_lost = $is_reserved && !$is_used && $was_used;
                $lost = !$is_used && $was_used;

                $is_red_light = in_array($ip, $red_light_ips[$label]) && !$is_used;

            ?>
            <td id="<?php echo $ip . "_" . $label; ?>" 
                onclick="reserveIP('<?php echo $label; ?>', '<?php echo $ip; ?>')" 
                class="<?php echo $both_reserved_and_used ? 'reserved used' : ($both_reserved_and_lost ? 'reserved lost' : ($is_red_light ? 'lost' : ($is_used ? 'used disabled' : ($is_reserved ? 'reserved' : '')))); ?>">
            <?php 
            if ($both_reserved_and_used) {
                echo '🟡🟢 <span class="machine-name">' . htmlspecialchars($reserved_machines[$ip]) . '</span>';
            } elseif ($both_reserved_and_lost) {
                echo '🟡🔴 <span class="machine-name">' . htmlspecialchars($reserved_machines[$ip]) . '</span>';
            } elseif ($is_reserved) {
                echo '🟡 <span class="machine-name">' . htmlspecialchars($reserved_machines[$ip]) . '</span>';
            } elseif ($is_used) {
                echo '🟢';
            } elseif ($is_red_light) {
                echo '🔴';
            }
            ?>
            <div id="<?php echo $ip . '_snmp_result'; ?>" class="snmp-result"></div> <!-- 用於顯示 SNMP 結果 -->
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
    </table>

    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <p id="modal-text"></p>
            <input type="text" id="machine-name" placeholder="輸入機器名稱">
            <br><br>
            <button class="cancel">取消</button>
            <button class="confirm">確定</button>
        </div>
    </div>
</body>
</html>
