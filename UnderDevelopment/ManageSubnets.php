<?php
// 定義網段配置文件所在的資料夾
$subnets_dir = 'subnets/';
$fping_prev_results_dir = 'fping_prev_results/';
$fping_red_lights_dir = 'fping_red_lights/';
$fping_reserved_dir = 'fping_reserved/';
$fping_results_dir = 'fping_results/';

// 確保資料夾存在
if (!file_exists($subnets_dir)) {
    mkdir($subnets_dir, 0777, true);
    chown($subnets_dir, 'apache');
    chgrp($subnets_dir, 'apache');
}

// 讀取所有現有的網段配置
$subnets = [];
foreach (glob($subnets_dir . '*.php') as $file) {
    $category = basename($file, '.php');
    $subnets[$category] = include $file;
}

// 驗證 IP 網段格式
function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

// 驗證遮罩範圍
function isValidMask($mask) {
    return is_numeric($mask) && $mask >= 1 && $mask <= 32;
}

// 處理新增或修改網段的請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $category = strtoupper($_POST['category']);  // 類別轉換為大寫
    $ip = $_POST['ip'];
    $mask = $_POST['mask'];

    // 驗證輸入的 IP 和遮罩
    if ($action !== 'delete' && (!isValidIP($ip) || !isValidMask($mask))) {
        echo json_encode(['status' => 'error', 'message' => '無效的 IP 或遮罩格式！']);
        exit;
    }

    if ($action === 'add' || $action === 'edit') {
        $subnets[$category] = ['category' => $category, 'ip' => $ip, 'mask' => $mask];
        file_put_contents($subnets_dir . $category . '.php', "<?php\nreturn " . var_export($subnets[$category], true) . ";\n");
    } elseif ($action === 'delete') {
        $filePath = $subnets_dir . $category . '.php';
        if (file_exists($filePath)) {
            unset($subnets[$category]);
            if (unlink($filePath)) {
                // 同時移除相對應的文件
                $files_to_remove = [
                    $fping_prev_results_dir . $category . '_results.txt',
                    $fping_red_lights_dir . $category . '_red_light.txt',
                    $fping_reserved_dir . $category . '_reserved.txt',
                    $fping_results_dir . $category . '_results.txt',
                    $fping_results_dir . $category . '_temp_results.txt'
                ];
                foreach ($files_to_remove as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                echo json_encode(['status' => 'success', 'subnets' => $subnets]);
            } else {
                echo json_encode(['status' => 'error', 'message' => '文件刪除失敗！']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => '找不到該文件！']);
        }
        exit;
    }

    // 返回更新結果給前端
    echo json_encode(['status' => 'success', 'subnets' => $subnets]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>管理掃描網段</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 20px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #007B8F;
            color: white;
        }

        button {
            padding: 10px 15px;
            margin: 5px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background-color: #005f6b;
        }

        /* Modal Styles */
        .modal {
            display: none; 
            position: fixed;
            z-index: 1; 
            padding-top: 100px; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0, 0, 0, 0.4); 
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        input[type="text"] {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            width: 100%; /* Ensure input fields take the full width of their containers */
        }

        /* This is where we define the flex container */
        .flex-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .ip-field {
            flex-grow: 8;
            flex-shrink: 1;
            flex-basis: 0;
        }

        .mask-field {
            flex-grow: 2;
            flex-shrink: 1;
            flex-basis: 0;
        }

        .cancel, .confirm {
            font-size: 16px;
            font-weight: bold;
            padding: 10px 20px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
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
    </style>
    <script>
        function openModal(action, category = '', ip = '', mask = '') {
            document.getElementById('modal').style.display = 'block';
            document.getElementById('modal-category').value = category;
            document.getElementById('modal-action').value = action;
            document.getElementById('modal-ip').value = ip;
            document.getElementById('modal-mask').value = mask;

            if (action === 'add') {
                document.getElementById('category-group').style.display = 'block';
                document.getElementById('modal-category').value = '';
            } else {
                document.getElementById('category-group').style.display = 'none';
            }
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function submitForm() {
            var action = document.getElementById('modal-action').value;
            var category = document.getElementById('modal-category').value || document.getElementById('category').value;
            var ip = document.getElementById('modal-ip').value;
            var mask = document.getElementById('modal-mask').value;

            // 類別自動轉換為大寫
            category = category.toUpperCase();

            if (!category || (action !== 'delete' && (!ip || !mask))) {
                alert('所有字段都是必需的！');
                return;
            }

            // IP 格式驗證
            var ipPattern = /^(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])(\.(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])){3}$/;
            if (action !== 'delete' && !ipPattern.test(ip)) {
                alert('無效的 IP 網段格式！');
                return;
            }

            // 遮罩格式驗證
            if (action !== 'delete' && (isNaN(mask) || mask < 24 || mask > 32)) {
                alert('遮罩目前只開放24 ~ 32的數字！');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 成功後重新加載頁面
                    } else {
                        alert(response.message || '提交失敗，請重試！');
                    }
                }
            };
            xhr.send('action=' + action + '&category=' + encodeURIComponent(category) + '&ip=' + encodeURIComponent(ip) + '&mask=' + encodeURIComponent(mask));
        }

        function addSubnet() {
            openModal('add');
        }

        function editSubnet(category, ip, mask) {
            openModal('edit', category, ip, mask);
        }

        function deleteSubnet(category) {
            if (confirm("確定要刪除類別 " + category + " 的網段嗎？")) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            location.reload(); // 成功後重新加載頁面
                        } else {
                            alert(response.message || '刪除失敗，請重試！');
                        }
                    }
                };
                xhr.send('action=delete&category=' + encodeURIComponent(category));
            }
        }
    </script>
</head>
<body>
    <h1>管理掃描網段</h1>
    <table>
        <tr>
            <th>類別</th>
            <th>網段</th>
            <th>遮罩</th>
            <th>操作</th>
        </tr>
        <?php if (!empty($subnets)) : ?>
            <?php foreach ($subnets as $category => $subnet): ?>
            <tr>
                <td><?php echo htmlspecialchars($category); ?></td>
                <td><?php echo htmlspecialchars($subnet['ip']); ?></td>
                <td><?php echo htmlspecialchars($subnet['mask']); ?></td>
                <td>
                    <button onclick="editSubnet('<?php echo htmlspecialchars($category); ?>', '<?php echo htmlspecialchars($subnet['ip']); ?>', '<?php echo htmlspecialchars($subnet['mask']); ?>')">編輯</button>
                    <button onclick="deleteSubnet('<?php echo htmlspecialchars($category); ?>')">刪除</button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">未添加任何網段</td>
            </tr>
        <?php endif; ?>
        <tr>
            <td colspan="4"><button onclick="addSubnet()">新增網段</button></td>
        </tr>
    </table>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div id="category-group">
                <p>請輸入類別</p>
                <input type="text" id="category" placeholder="類別" required>
            </div>
            <p>請輸入網段與遮罩</p>
            <div class="flex-container">
                <div class="ip-field">
                    <input type="text" id="modal-ip" placeholder="網段" required>
                </div>
                <div class="mask-field">
                    <input type="text" id="modal-mask" placeholder="遮罩" required>
                </div>
            </div>
            <input type="hidden" id="modal-action">
            <input type="hidden" id="modal-category">
            <div style="margin-top: 20px;">
                <button type="button" class="cancel" onclick="closeModal()">取消</button>
                <button type="button" class="confirm" onclick="submitForm()">保存</button>
            </div>
        </div>
    </div>
</body>
</html>
