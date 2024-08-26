<?php
if (isset($_GET['ip'])) {
    $ip = $_GET['ip'];

    // 執行 SNMPwalk 命令來獲取機器名稱
    $output = shell_exec("snmpwalk -v 2c -c cyanyellowgreen168 $ip SNMPv2-MIB::sysName");

        // 处理输出结果，提取并返回机器名称
    if ($output) {
        $output = explode('=', $output, 2);  // 分割字符串，提取等号后面的内容
        if (isset($output[1])) {
            $machine_name = trim($output[1]);  // 移除多余的空格或换行
            $machine_name = str_replace('STRING: ', '', $machine_name);  // 移除 'STRING: ' 前缀
            echo "$ip ➜ $machine_name";  // 输出机器名称
        } else {
            echo "No machine name returned.";
        }
    } else {
	    echo "請[確認SNMP是否啟動]或[Community是否配置正確]";
    }
} else {
    echo "IP address not provided.";
}
?>
